# Technical Docs — List Items

**Keywords:** items, toggle, purchase, history, categories, counter, undo, position

## Overview

`ListItemService` handles all item mutations with atomic counter sync. Purchase events write to `producto_historial` (append-only).

## Item Fields

| Field | Type | Notes |
|-------|------|-------|
| `name` | string(80) | Required |
| `quantity` | float | Optional |
| `unit` | enum(ItemUnit) | Optional |
| `category` | enum(ProductCategory) | Optional; auto-inferred on create |
| `estimated_price` | decimal | Optional; set by PriceEstimationService |
| `is_purchased` | bool | Toggleable |
| `position` | int | Display order |

## 10 Product Categories

`Frutas y verduras`, `Carnes y pescados`, `Lácteos y huevos`, `Panadería`, `Bebidas`, `Congelados`, `Limpieza y hogar`, `Higiene y salud`, `Mascotas`, `Otros`

Items without category displayed under `Otros`.

## Counter Sync

Every mutation calls `syncCounters(list)`:
```sql
SELECT COUNT(*) as total FROM list_items WHERE shopping_list_id = ?
SELECT COUNT(*) as completed FROM list_items WHERE shopping_list_id = ? AND is_purchased = 1
UPDATE shopping_lists SET items_total = total, items_completed = completed
```

Two COUNT queries per mutation — avoids desync if exceptions occur mid-operation.

## Auto-Categorization

On item create, if `category` is null:
1. `CategoryInferenceService::inferFromCatalog(name)` → exact lookup in `producto_catalogo`
2. If found: set category
3. If not found: dispatch `InferItemCategoryJob` (async AI inference)
4. Category remains null until job completes

## Toggle Purchased

```
PATCH /api/lists/{list}/items/{item}/toggle
→ DB::transaction {
    UPDATE list_items SET is_purchased = !is_purchased
    IF newly purchased:
      INSERT producto_historial {
        user_id (owner or anonymous attribution),
        producto_nombre, categoria, cantidad, unidad,
        precio_real = null,  ← populated later via HU-702
        fecha_compra = now(),
        lista_id
      }
    syncCounters(list)
  }
```

## Delete & Undo

- Backend: DELETE immediately (no soft-delete)
- Frontend: stores item data in memory for 5s → shows undo snackbar → re-creates if clicked
- If user navigates away within 5s: undo lost (accepted)

## Increment Quantity (Duplicate Action)

```
PATCH /api/lists/{list}/items/{item}/increment-quantity
→ UPDATE list_items SET quantity = COALESCE(quantity, 0) + 1
→ syncCounters(list)
```

Used when user selects "Incrementar cantidad" instead of adding a duplicate item.

## Route Order Note

`DELETE /api/lists/{list}/items/completed` must be registered **before** `/{item}` routes — otherwise "completed" is captured as an item ID.
