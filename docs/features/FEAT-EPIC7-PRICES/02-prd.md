# PRD: FEAT-EPIC7-PRICES - Estimación de precio total (Phase A: Layers 1+2)

## Business Objective

Dar al usuario una respuesta a "¿cuánto me va a costar esta compra?" antes de ir al supermercado. Es la primera feature que convierte a Superia de un gestor de listas en un **planificador de presupuesto**. El valor está en reducir la incertidumbre económica: un usuario que sabe que "esta lista costará entre 35€ y 45€" puede decidir si ajustar antes de salir de casa.

Phase A entrega las dos capas de precio más fiables y simples: (1) historial personal del usuario (precio real pagado previamente) y (2) catálogo estático de precios medios españoles. Sin APIs externas, sin Claude real-time. Phase B (OFF + Claude fallback) se implementará cuando Phase A esté validada.

## Problem Statement

- **El usuario no sabe cuánto va a gastar**: hoy puede crear una lista de 20 items sin ninguna indicación de coste. Solo lo descubre en la caja del supermercado.
- **`list_items.estimated_price` existe pero siempre es null**: el schema está preparado pero nunca se usa. Los 6 epics anteriores no tocan precios.
- **`producto_historial.precio_real` existe pero siempre es null**: el campo se creó en Epic 3 pero ningún flujo permite al usuario introducir el precio que pagó.
- **`producto_catalogo` no tiene precios**: la tabla tiene 250+ productos con nombre/categoría/unidad pero sin referencia de precio.

## Scope

### In Scope (Phase A: Layers 1+2)

#### Backend
- **Migración**: añadir `precio_min` DECIMAL(8,2) nullable y `precio_max` DECIMAL(8,2) nullable a `producto_catalogo`.
- **Console command**: `php artisan prices:seed-catalog` — genera precios min/max para todos los productos en `producto_catalogo` via Claude batch. Similar a `SeedProductCatalog`. One-time, re-runnable (UPDATE existing rows).
- **Console command alias**: `php artisan prices:refresh-catalog` — alias del anterior para claridad semántica (same implementation).
- **Service**: `App\Services\PriceEstimationService` con métodos:
  - `estimateForItem(User $user, ListItem $item): ?PriceEstimate` — resuelve Layer 1 (historial personal) o Layer 2 (catálogo). Returns DTO con `min`, `max`, `source` ('history'|'catalog'|null).
  - `estimateForList(User $user, ShoppingList $list): ListPriceEstimate` — agrega per-item en un total con rango. Returns DTO con `total_min`, `total_max`, `items[]`, `resolved_count`, `unresolved_count`.
  - `recordRealPrice(User $user, ListItem $item, float $price): void` — escribe `producto_historial.precio_real` para el row más reciente del producto del usuario. HU-702.
  - `recordTotalPrice(User $user, ShoppingList $list, float $total): void` — log informativo, no distribuye a Layer 1.
- **DTOs**: `App\Support\Price\PriceEstimate` (min, max, source, product_name) y `App\Support\Price\ListPriceEstimate` (total_min, total_max, items, resolved_count, unresolved_count).
- **Layer 1 query**: `SELECT precio_real FROM producto_historial WHERE user_id = ? AND LOWER(producto_nombre) = LOWER(?) AND precio_real IS NOT NULL ORDER BY fecha_compra DESC LIMIT 1`. Most recent real price for this user + product name.
- **Layer 2 query**: `SELECT precio_min, precio_max FROM producto_catalogo WHERE LOWER(nombre) = LOWER(?)`. Static catalog lookup.
- **Endpoints**:
  - `POST /api/lists/{list}/estimate-prices` (auth + ownership) — triggers batch estimation, updates `list_items.estimated_price` per item, returns `ListPriceEstimate`.
  - `POST /api/lists/{list}/confirm-prices` (auth + ownership) — HU-702: body `{total: float, items: [{item_id, price}]}`. Records real prices.
- **FormRequests**: `EstimatePricesRequest` (empty body, list ownership), `ConfirmPricesRequest` (total required float, items optional array with item_id + price).
- **Config**: `config/ai.php` → `prices` section: `enabled`, `catalog_model` (Claude model for batch seed), `max_tokens_seed`.

#### Frontend
- **ListDetailPage.jsx** modifications:
  - Summary price bar at bottom: "Estimación: 35,00€ — 45,00€" (or "Sin datos de precio" if no items resolved)
  - "Recalcular precios" button that calls the estimate endpoint
  - Expandable per-item breakdown: tap on price bar → list of items with individual estimated_price
  - Per-item price inline (small text below item name): "~1,20€" or "1,00€ — 1,50€"
- **ConfirmPriceModal.jsx** (NEW): triggered when 100% items purchased. Non-blocking prompt "¿Cuánto pagaste?" → total input → optional per-item expansion. Dismiss = no action.
- **Tests**: vitest for price bar, breakdown, modal, API interactions

### Out of Scope (Phase B — future feature)

- **Open Food Facts API integration (Layer 3)** — deferred per decision #1
- **Claude real-time price estimation (Layer 4)** — deferred per decision #14
- **Automatic recalculation on every item mutation** — user triggers manually per decision #4
- **Price history charts / trends** — future analytics feature
- **Multi-currency support** — EUR only per decision #10
- **Price alerts ("this item got more expensive")** — future feature
- **Scheduled monthly catalog refresh job** — manual command per decision #9
- **Distributing total price to per-item when user only enters total** — per decision #8

## Acceptance Criteria

### AC-1: Migration adds price columns to producto_catalogo
- **Given**: the existing `producto_catalogo` table
- **When**: migration runs
- **Then**: `precio_min` and `precio_max` DECIMAL(8,2) nullable columns are added. Existing rows unaffected (null by default). Migration is reversible.

### AC-2: Seed command populates price ranges via Claude
- **Given**: `producto_catalogo` has ~250 products without prices
- **When**: `php artisan prices:seed-catalog` runs
- **Then**: Claude generates price ranges (EUR) for each product. Rows are updated with `precio_min` and `precio_max`. Command is idempotent (re-run overwrites). Output shows count of updated rows.

### AC-3: Layer 1 resolves from personal history
- **Given**: user A has a `producto_historial` row with `producto_nombre='Leche entera'` and `precio_real=1.15`
- **When**: `PriceEstimationService::estimateForItem` is called for item "Leche entera" for user A
- **Then**: returns `PriceEstimate{min: 1.15, max: 1.15, source: 'history'}`. Exact price, no range.

### AC-4: Layer 2 resolves from catalog when Layer 1 misses
- **Given**: user B has no price history for "Tomates" but `producto_catalogo` has `nombre='Tomates', precio_min=1.50, precio_max=2.80`
- **When**: `PriceEstimationService::estimateForItem` for "Tomates" for user B
- **Then**: returns `PriceEstimate{min: 1.50, max: 2.80, source: 'catalog'}`.

### AC-5: Null returned when both layers miss
- **Given**: an item "Salsa especial casera" not in user history and not in catalog
- **When**: estimation is attempted
- **Then**: returns null. The item is counted as `unresolved` in the list total.

### AC-6: Estimate endpoint returns list-level price range
- **Given**: a list with 3 items: "Leche" (Layer 1: 1.15€), "Tomates" (Layer 2: 1.50-2.80€), "Salsa rara" (no price)
- **When**: user calls `POST /api/lists/{id}/estimate-prices`
- **Then**: response includes `total_min: 2.65, total_max: 3.95, resolved_count: 2, unresolved_count: 1` and per-item breakdown. Each resolved item's `estimated_price` is written to `list_items.estimated_price`.

### AC-7: Estimate endpoint requires ownership
- **Given**: user A's list
- **When**: user B calls the estimate endpoint for that list
- **Then**: HTTP 404.

### AC-8: Price summary bar visible on ListDetailPage
- **Given**: a list with at least one item that has an `estimated_price` set
- **When**: the user views the list detail
- **Then**: a price summary bar shows "Estimación: X,XX€ — Y,YY€". If no items have prices, shows "Sin datos de precio". A "Recalcular precios" button is available.

### AC-9: Per-item breakdown expandable
- **Given**: the price summary bar is showing a total
- **When**: the user taps on the price bar
- **Then**: it expands to show each item's estimated price: "Leche: 1,15€" or "Tomates: 1,50€ — 2,80€" or "Salsa rara: sin datos".

### AC-10: Confirm price prompt on 100% purchased
- **Given**: all items in a list are marked as purchased (`is_purchased = true`)
- **When**: the last item is toggled to purchased
- **Then**: a non-blocking modal appears: "¿Cuánto pagaste?" with a total input and optional per-item breakdown. User can dismiss without action.

### AC-11: Confirm total price logs but does not distribute
- **Given**: user enters total price 42.50€ without per-item breakdown
- **When**: the confirm endpoint processes the request
- **Then**: the total is logged (in the response or a log entry) but NO `producto_historial.precio_real` rows are updated.

### AC-12: Confirm per-item prices feed Layer 1
- **Given**: user enters price 1.20€ for "Leche entera"
- **When**: the confirm endpoint processes per-item prices
- **Then**: `producto_historial` is queried for the most recent row where `user_id = user, producto_nombre = 'Leche entera'`, and `precio_real` is updated to 1.20.

### AC-13: Confirm price is optional and non-blocking
- **Given**: all items are purchased
- **When**: the modal appears
- **Then**: the user can dismiss it (X or "Ahora no") and the list remains unchanged. No price data is lost; the user can still use the list normally.

### AC-14: Quantity multiplied into estimate
- **Given**: item "Leche" with quantity 3, catalog price 0.90€ — 1.20€
- **When**: estimation is calculated
- **Then**: per-item estimate is 2.70€ — 3.60€ (quantity × unit price range).

### AC-15: All endpoints require auth
- **Given**: unauthenticated request
- **When**: any new endpoint is called
- **Then**: HTTP 401.

### AC-16: 100% backend test coverage
- **Given**: the backend test suite
- **When**: `php artisan test` runs
- **Then**: PriceEstimationService (Layer 1, Layer 2, fallback, list aggregation, confirm prices), seed command, endpoints — all covered.

### AC-17: Frontend tests
- **Given**: vitest suite
- **When**: `npm test` runs
- **Then**: price summary bar, breakdown, confirm modal, recalculate button — all tested.

## UX Decision

- **UX Designer Required**: **NO**
- **UX Artifacts**: no Stitch screen for prices (confirmed in S1 decision #12). Follow existing `ListDetailPage` patterns.
- **Basic UX Notes**:
  - Price summary bar: sticky footer or inline section below the items list. Shows "Estimación: X€ — Y€" in muted text. Tap to expand breakdown.
  - "Recalcular precios" button: small, secondary style, below the price bar.
  - Per-item price: small muted text below item name in the breakdown view.
  - Confirm modal: appears once when 100% purchased. Total input + optional per-item accordion. Dismiss with X. Non-blocking.
  - All prices formatted: `XX,XX€` (comma decimal, EUR symbol after).
- **S5-UX will run**: ListDetailPage modifications + new modal = UI changes.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Claude-generated price catalog is inaccurate (±30-50%) | Quality | Disclaimer "Estimación" is always visible. Phase B will add real data sources (OFF, user feedback). V1 users understand it's approximate. |
| Seed command is expensive (250 products × Claude call) | Cost | Batch the products in groups of ~50 per Claude call (5 calls total). Use Haiku model for cost efficiency. |
| `producto_catalogo` name matching is case-sensitive | Technical | Use `LOWER()` comparison. Products with different names (e.g., "Leche" vs "Leche entera") won't match Layer 2 — accepted limitation for V1. |
| HU-702 "100% purchased" trigger fires too often | UX | The modal is non-blocking and dismissable. Shows once per list (track via a flag or dismiss state). |
| `estimated_price` column overwritten on recalculate | Data | By design — the button replaces the old estimates. Layer 1 data (user history) takes precedence and improves over time. |
| Layer 1 returns stale price (user paid 1.20€ 6 months ago, actual price is now 1.50€) | Quality | Acceptable for V1. Layer 1 is always the most relevant to the user ("I paid this before"). Phase B Claude can supplement with current estimates. |

## Assumptions

- `producto_catalogo` has ~250 products. Seeding prices for all of them is 5 Claude calls (50 per batch).
- `producto_historial.precio_real` can be updated via a simple UPDATE query — the column exists and is nullable.
- `list_items.estimated_price` can be written via `$item->update(['estimated_price' => $value])` — the column exists.
- EUR comma-decimal format is handled by frontend formatting only (backend stores as float).
- The confirm modal appears once per "all purchased" transition — not on every page load.

## Open Questions

None. All 14 from S1 resolved.

## Approval

- [ ] PRD approved by [user] on [date]

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
- Required Artifacts for Next Step: `01-scope.md`, `02-prd.md`
