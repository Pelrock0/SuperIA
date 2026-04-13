# Technical Design: FEAT-EPIC9-HISTORY

## Overview

Dos servicios read-heavy sin dependencias externas: (1) `ListHistoryService` que pagina listas archivadas con precio total computado (confirmed > estimated sum) y clona listas para la función "duplicar"; (2) `StatsService` que agrega `producto_historial` + `shopping_lists` para estadísticas de gasto y consumo. Frontend: `HistoryPage` con cards + recharts bar/pie charts. Zero migraciones, zero tablas nuevas — toda la data ya existe.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Services | History pagination + price computation + duplicate, Stats aggregation | `ListHistoryService` (NEW), `StatsService` (NEW) |
| Controllers | Thin: paginate, duplicate, stats | `HistoryController` (NEW), `StatsController` (NEW) |
| Frontend | History cards + duplicate button + recharts charts | `HistoryPage.jsx` (NEW), `StatsSection.jsx` (NEW) |

### Data Flow

#### History (GET /api/history)
```
1. HistoryController::index → ListHistoryService::getHistory($user, $page)
2. Query: ShoppingList WHERE user_id=user AND status='archived' ORDER BY updated_at DESC PAGINATE(20)
3. For each list, compute price_total:
   a. Check if a confirmed total exists (Log::info entry from PriceEstimationService::recordTotalPrice — OR a simpler approach: sum confirmed per-item prices from producto_historial)
   b. If no confirmed → SUM(list_items.estimated_price) WHERE shopping_list_id=list.id
   c. Return {price_total, price_source: 'confirmed'|'estimated'|null}
4. Return paginated collection with price data
```

Note: for the "confirmed total" source, the simplest approach is: if ANY `list_items.estimated_price` was overwritten by `recordItemPrices` (HU-702), the item has a real price. We can detect this by checking if `producto_historial.precio_real IS NOT NULL` for any of the list's products. But this is complex. **Simpler V1 approach**: just use SUM(`list_items.estimated_price`) always. If the user confirmed per-item prices via HU-702, those were written to `estimated_price` too (PriceEstimationService::recordItemPrices does `$item->update(['estimated_price' => $price])`). So the sum already reflects confirmed prices when available. `price_source` = 'estimated' always for V1 (the field exists for future use when we track confirmed totals explicitly).

#### Duplicate (POST /api/lists/{list}/duplicate)
```
1. Verify ownership + list is archived
2. ListHistoryService::duplicate($user, $list)
3. ShoppingListService::create($user, {name: "Copia de {list.name}", emoji: list.emoji, category: null})
   → throws OverflowException if freemium cap
4. Clone each item: list.items → new_list.items (name, quantity, unit, category; is_purchased=false, position preserved)
5. Return new list
```

#### Stats (GET /api/stats)
```
1. StatsController::index → StatsService::getStats($user)
2. total_lists_completed = ShoppingList::where(user_id, status=archived)->count()
3. has_enough_data = total >= 3
4. monthly_spend (last 6 months):
   SELECT DATE_FORMAT(updated_at, '%Y-%m') as month, SUM(sub.total) as total
   FROM shopping_lists sl
   JOIN (SELECT shopping_list_id, SUM(estimated_price) as total FROM list_items GROUP BY shopping_list_id) sub ON sub.shopping_list_id = sl.id
   WHERE sl.user_id = ? AND sl.status = 'archived' AND sl.updated_at >= NOW() - INTERVAL 6 MONTH
   GROUP BY month ORDER BY month
5. top_categories: SELECT categoria, COUNT(*) as count FROM producto_historial WHERE user_id = ? GROUP BY categoria ORDER BY count DESC LIMIT 5
6. top_products: SELECT producto_nombre, COUNT(*) as count FROM producto_historial WHERE user_id = ? GROUP BY producto_nombre ORDER BY count DESC LIMIT 10
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| `getHistory` | None (read-only) | — |
| `duplicate` | Reuses `ShoppingListService::create` transaction for list + freemium. Item cloning outside (same pattern as Epic 5C/6). | Max ~25 item inserts, validated data. |
| `getStats` | None (read-only) | — |

## Data Model

### New Tables — None.
### Migrations — None.

### API Changes

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/history` | GET | `auth:api` | Paginated archived lists with price totals |
| `/api/lists/{list}/duplicate` | POST | `auth:api` + ownership | Clone archived list as new active list |
| `/api/stats` | GET | `auth:api` | Aggregated spending + consumption statistics |

### npm dependency

Add `recharts` to `package.json`:
```bash
npm install recharts
```

## Performance

- **History**: 1 paginated query + N subqueries for price SUM (N=20 per page). Could optimize with a JOIN or subquery in the main query. For V1, N=20 × 1 SUM query = 20 indexed queries ≈ <50ms.
- **Stats monthly_spend**: 1 aggregate query with JOIN, bounded by 6 months + user_id index. Fast.
- **Stats top_categories/products**: 2 GROUP BY queries on `producto_historial` indexed by `user_id`. Fast.
- **Duplicate**: same as Epic 5C/6 item creation pattern.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| **SUM(estimated_price) as price_total** | Simple, already reflects confirmed prices from HU-702 | Can't distinguish confirmed vs estimated in V1 | **Selected** — price_source='estimated' always, real distinction deferred |
| **recharts for charts** | Polished, React-native, lightweight | 40KB gzipped dependency | **Selected** per user decision — premium feel |
| **Pure CSS bars** | Zero dependency | Looks basic | Rejected per user decision |
| **Stats on HistoryPage (not separate page)** | One page, context-relevant | Page gets long | **Selected** — stats section at top, history list below |

## Implementation Notes

### S4 execution order
1. `ListHistoryService` (getHistory + duplicate)
2. `StatsService` (getStats)
3. `HistoryController` + `StatsController` + routes
4. Backend tests
5. Run backend suite
6. `npm install recharts`
7. Fetch Stitch historial screen via MCP
8. `HistoryPage.jsx` + `StatsSection.jsx`
9. Route in app.jsx + link on DashboardPage
10. Frontend tests
11. Run frontend suite

### Frontend work identified
YES — S4-BOTH. `has_ui_changes = YES`.

## Transition
- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation (S4-BOTH)
