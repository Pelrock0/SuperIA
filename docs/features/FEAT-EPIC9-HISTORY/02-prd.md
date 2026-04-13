# PRD: FEAT-EPIC9-HISTORY - Historial de listas + estadísticas (HU-901 + HU-902)

## Business Objective

Convertir los datos acumulados de 8 epics en información visible para el usuario. Hasta ahora los datos (listas archivadas, precios, historial de productos) existen en la base de datos pero el usuario solo ve su dashboard de listas activas. Epic 9 abre dos ventanas: (1) un historial navegable de compras pasadas con la capacidad de duplicar listas y (2) estadísticas de gasto y consumo que ayudan al usuario a entender sus patrones.

## Problem Statement

- **Listas archivadas son poco visibles**: están en la sección inferior del dashboard como ciudadanas de segunda clase. No hay una vista dedicada para explorarlas.
- **No hay forma de reutilizar una lista pasada**: si el usuario hizo una "Cena de Navidad" el año pasado, tiene que recrearla desde cero.
- **Los datos de gasto no se visualizan**: Epic 7 calculó precios estimados y confirmados, pero no hay ningún resumen visual de cuánto gasta el usuario al mes.
- **No hay insight sobre hábitos**: el producto_historial tiene meses de datos pero el usuario solo los ve en el autocompletado.

## Scope

### In Scope

#### Backend — HU-901
- **Endpoint** `GET /api/history` (auth) — returns archived lists paginated (20/page), ordered by `updated_at DESC`. Each entry: id, name, emoji, items_total, items_completed, created_at, updated_at, price_total (confirmed > estimated sum).
- **Endpoint** `POST /api/lists/{list}/duplicate` (auth + ownership) — clones an archived list as a new active list. Name: "Copia de {original}". Items cloned with name, quantity, unit, category. No purchased state, no estimated_price. Subject to freemium 3-list limit. Returns the new list.
- **Service** `App\Services\ListHistoryService` (NEW): `getHistory(User, page)`, `duplicate(User, ShoppingList)`.
- **Price total calculation**: for each archived list, compute `confirmed_total` (from HU-702 log if exists) OR `estimated_total` (SUM of `list_items.estimated_price`). Return whichever is available, with a `price_source` field ('confirmed'|'estimated'|null).

#### Backend — HU-902
- **Endpoint** `GET /api/stats` (auth) — returns:
  - `monthly_spend`: array of {month: 'YYYY-MM', total: float} for last 6 months. Source: archived lists' price totals.
  - `top_categories`: array of {category: string, count: int, percentage: float} top 5 from `producto_historial`.
  - `top_products`: array of {name: string, count: int} top 10 from `producto_historial`.
  - `total_lists_completed`: int (archived lists count).
  - `has_enough_data`: boolean (true if ≥3 archived lists).
- **Service** `App\Services\StatsService` (NEW): aggregate queries on `shopping_lists`, `list_items`, `producto_historial`.

#### Frontend — HU-901
- **Page** `HistoryPage.jsx` at `/app/historial`: paginated list of archived lists. Each card shows name, date, items count, price total (with source indicator). Click → navigate to `ListDetailPage` (already works). "Duplicar" button per card.
- **DashboardPage**: add "Ver historial" link to navigate to `/app/historial`.
- **Stitch screen** "Historial" fetched via MCP in S4.

#### Frontend — HU-902
- **StatsSection** integrated into `HistoryPage` (top section) or as a separate tab/section.
- **Monthly spend bar chart** using `recharts` `<BarChart>`.
- **Top 5 categories** using `recharts` `<PieChart>` or horizontal percentage bars.
- **Top 10 products** as a simple ranked list.
- **Minimum data gate**: if `has_enough_data` is false, show "Completa al menos 3 listas para ver estadísticas."

### Out of Scope
- **Search/filter on history** — simple chronological list for V1.
- **Export history to CSV/PDF** — future feature.
- **Compare months** — just show the chart, no comparison UX.
- **Real-time stats updates** — refresh on page load only.
- **Custom date range for stats** — last 6 months hardcoded.
- **Stats for admin** — this is per-user stats. Admin stats are Epic 10.

## Acceptance Criteria

### AC-1: History endpoint returns archived lists paginated
- **Given**: user has 25 archived lists
- **When**: `GET /api/history?page=1`
- **Then**: returns 20 lists ordered by updated_at DESC, with pagination meta.

### AC-2: Each history entry includes price total
- **Given**: archived list with estimated prices summing to 35.50€
- **When**: history is fetched
- **Then**: entry includes `price_total: 35.50, price_source: 'estimated'`.

### AC-3: Confirmed price takes precedence
- **Given**: archived list with confirmed total 42.00€ (from HU-702) and estimated sum 35.50€
- **When**: history is fetched
- **Then**: entry includes `price_total: 42.00, price_source: 'confirmed'`.

### AC-4: Duplicate creates clean copy
- **Given**: archived list "Cena Navidad" with 10 items, user has <3 active lists
- **When**: `POST /api/lists/{id}/duplicate`
- **Then**: new active list "Copia de Cena Navidad" created with 10 items (same name, quantity, unit, category; is_purchased=false, estimated_price=null). HTTP 201.

### AC-5: Duplicate respects freemium limit
- **Given**: user has 3 active lists
- **When**: duplicate attempted
- **Then**: HTTP 403 FREEMIUM_LIMIT.

### AC-6: Duplicate requires ownership
- **Given**: another user's list
- **When**: intruder calls duplicate
- **Then**: HTTP 404.

### AC-7: Stats endpoint returns aggregated data
- **Given**: user with 5 archived lists, purchase history across 3 months
- **When**: `GET /api/stats`
- **Then**: response includes monthly_spend (6 entries), top_categories (up to 5), top_products (up to 10), total_lists_completed, has_enough_data: true.

### AC-8: Stats shows "not enough data" when <3 lists
- **Given**: user with 2 archived lists
- **When**: `GET /api/stats`
- **Then**: `has_enough_data: false`. Arrays may be empty or partial.

### AC-9: HistoryPage renders list cards with price
- **Given**: user navigates to `/app/historial`
- **When**: page loads
- **Then**: archived lists shown as cards with name, date, items count, price total. "Duplicar" button visible.

### AC-10: Duplicate button creates list and navigates
- **Given**: user clicks "Duplicar" on a history card
- **When**: the API succeeds
- **Then**: new list created, user redirected to the new list's detail page.

### AC-11: Stats section shows charts when enough data
- **Given**: user has ≥3 archived lists
- **When**: HistoryPage loads
- **Then**: bar chart (monthly spend) and category breakdown visible.

### AC-12: Stats section shows message when not enough data
- **Given**: user has <3 archived lists
- **When**: HistoryPage loads
- **Then**: "Completa al menos 3 listas para ver estadísticas."

### AC-13: All endpoints require auth
### AC-14: 100% backend test coverage
### AC-15: Frontend tests for HistoryPage + StatsSection

## UX Decision

- **UX Designer Required**: NO
- **UX Artifacts**: Stitch screen "Historial" exists. Fetch in S4.
- **S5-UX will run**: new page + charts.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Stats queries slow on large producto_historial | Performance | GROUP BY with index (user_id). LIMIT results. Acceptable for V1 user count. |
| recharts bundle size | Performance | recharts is ~40KB gzipped. Acceptable. Code-split if needed. |
| Price total null for old lists (pre-Epic 7) | Data | Show "sin datos" when price_total is null. |
| Duplicate of a list with 25 items = 25 inserts | Performance | Same pattern as convertToList/confirmAsNewList. Acceptable. |

## Open Questions
None.

## Transition
- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
