# Technical Design — FEAT-EPIC9-HISTORY

## Architecture

`ListHistoryService` for pagination + duplicate. `StatsService` for aggregations. Frontend uses recharts for visualization.

## Data Flow

```
History (GET /api/history?page=1):
  → ListHistoryService::paginate(user, page=1, perPage=20)
    SELECT shopping_lists WHERE user_id = ? AND status = 'archived'
    ORDER BY updated_at DESC
    PAGINATE(20)
    For each list:
      SELECT SUM(estimated_price) as price_total FROM list_items WHERE shopping_list_id = ?
      SELECT COUNT(*) as items_count FROM list_items WHERE shopping_list_id = ?
    Return { data: [...], total, current_page, last_page }

Duplicate list (POST /api/lists/{list}/duplicate):
  → ListHistoryService::duplicate(user, list)
    → ShoppingListService::create(user, { name, emoji, category }) ← freemium check
    → INSERT list_items SELECT name, quantity, unit, category
       FROM list_items WHERE shopping_list_id = source_id
       (WITHOUT is_purchased, estimated_price — clean slate)
    → syncCounters(new_list)
    → return new_list

Statistics (GET /api/stats):
  → StatsService::forUser(user)
    gate: IF archived_lists_count < 3: return { has_enough_data: false }

    monthly_spend (last 6 months):
      SELECT year, month, SUM(estimated_price) FROM list_items
      JOIN shopping_lists ON ...
      WHERE status='archived' AND updated_at > NOW()-6months
      GROUP BY year, month

    top_categories (last 6 months):
      SELECT category, SUM(estimated_price) FROM list_items ...
      GROUP BY category ORDER BY sum DESC LIMIT 5

    top_products:
      SELECT producto_nombre, COUNT(*) as times_bought FROM producto_historial
      WHERE user_id = ? AND fecha_compra > NOW()-6months
      GROUP BY producto_nombre ORDER BY count DESC LIMIT 10
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Per-list SUM subquery (not JOIN) | Simpler; N=20/page; acceptable latency |
| Price precedence: confirmed > estimated | `estimated_price` is updated with real price on HU-702 confirm |
| Duplicate without prices/purchased | Clean slate; user re-checks items on next use |
| ≥3 lists gate | Prevents misleading statistics from sparse data |

## Gotchas

- Pre-existing flaky `AuthServiceTest` (LoginAttempt count issue) — unrelated to this feature, pre-dates it
- recharts requires `width` prop or parent container with explicit width (flexbox auto-width doesn't work in all browsers)
- Statistics 6-month span hardcoded in query (V1); make configurable in V2
