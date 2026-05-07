# Technical Design — FEAT-EPIC3-ITEMS

## Architecture

`ListItemService` handles all item mutations with atomic counter sync. `producto_historial` is append-only.

## Data Flow

```
Add item:
  POST /api/lists/{list}/items { name, quantity, unit, category, estimated_price }
  → ListItemService::create()
    → DB::transaction {
        Validate ownership: item.list.user_id == auth.user_id
        INSERT list_items { shopping_list_id, name, quantity, unit, category, is_purchased=false }
        Optionally: InferItemCategoryJob::dispatch() if category null
        syncCounters(list):
          total = COUNT(*) WHERE shopping_list_id = ?
          completed = COUNT(*) WHERE shopping_list_id = ? AND is_purchased = true
          UPDATE shopping_lists SET items_total=total, items_completed=completed
      }

Toggle purchased:
  PATCH /api/lists/{list}/items/{item}/toggle
  → DB::transaction {
      UPDATE list_items SET is_purchased = !is_purchased
      IF newly purchased:
        INSERT producto_historial {
          user_id, producto_nombre, categoria, cantidad, unidad,
          precio_real (null initially), fecha_compra, lista_id
        }
      syncCounters(list)
    }

Delete item:
  DELETE /api/lists/{list}/items/{item}
  → DELETE list_items WHERE id = ?   ← backend deletes immediately
  → (Frontend holds item data 5s for undo re-creation)
  → syncCounters(list)

Clear completed:
  DELETE /api/lists/{list}/items/completed
  → DELETE WHERE shopping_list_id = ? AND is_purchased = true
  → syncCounters(list)
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| `syncCounters` via COUNT (not ±1) | Prevents desync if exceptions occur mid-operation |
| Undo frontend-only | Simpler than soft-delete + cleanup job; undo is 5s local state |
| `producto_historial` append-only | History must never be edited; RGPD delete is separate hard-delete |
| `producto_historial` SET NULL on list deletion | History survives list deletion (feeds future AI) |
| Route order: `/completed` before `/{item}` | Prevents "completed" segment being captured as item ID |

## Gotchas

- Category inference: `CategoryInferenceService` checks catalog synchronously; if null, dispatches `InferItemCategoryJob` async (AI fallback)
- Frontend undo stores item data client-side; if user closes tab within 5s, undo is lost (accepted)
- `precio_real` is null at toggle time; populated later via `PriceEstimationService::recordItemPrices()` (Epic 7)
