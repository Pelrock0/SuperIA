# Technical Design: FEAT-EPIC7-PRICES (Phase A: Layers 1+2)

## Overview

Estimación de precios con 2 capas: (1) historial personal del usuario (`producto_historial.precio_real`, ya existe como columna nullable) y (2) catálogo estático de precios medios (`producto_catalogo.precio_min/max`, nuevas columnas). La resolución es per-item con fallback: Layer 1 → Layer 2 → null. El trigger de recálculo es on-demand (botón "Recalcular precios"), no automático. Los precios estimados se persisten en `list_items.estimated_price` (ya existe, nullable). HU-702 (confirmar precio real) alimenta Layer 1 vía UPDATE de `producto_historial.precio_real`.

Zero nuevas tablas. Zero APIs externas. 1 migración (2 columnas nullable en tabla existente). 1 seed command (batch Claude para popular precio_min/max). 1 service con 4 métodos públicos. 2 endpoints. Frontend: price bar en ListDetailPage + ConfirmPriceModal.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | DTOs de precio, validación monetaria | `App\Support\Price\PriceEstimate`, `App\Support\Price\ListPriceEstimate` (NEW) |
| Services | Pipeline de resolución 2-layer, aggregation, confirm prices | `App\Services\PriceEstimationService` (NEW) |
| Infrastructure | Claude batch seed, existing DB columns | `App\Console\Commands\SeedProductCatalogPrices` (NEW), `config/ai.php` extension |
| Controllers/API | Thin: estimate + confirm | `App\Http\Controllers\PriceEstimationController` (NEW) |
| Frontend | Price bar, breakdown, confirm modal | Modifications to `ListDetailPage.jsx`, `ConfirmPriceModal.jsx` (NEW) |

### Data Flow

#### Estimate prices (POST /api/lists/{list}/estimate-prices)

```
1. User clicks "Recalcular precios" on ListDetailPage
2. PriceEstimationController::estimate validates ownership
3. Controller calls PriceEstimationService::estimateForList($user, $list)
4. Service iterates list->items():
   For each item:
     a. Layer 1: query producto_historial WHERE user_id=user AND LOWER(producto_nombre)=LOWER(item.name) AND precio_real IS NOT NULL ORDER BY fecha_compra DESC LIMIT 1
        - Found → PriceEstimate{min: price, max: price, source: 'history'}
     b. If Layer 1 miss → Layer 2: query producto_catalogo WHERE LOWER(nombre)=LOWER(item.name) AND precio_min IS NOT NULL
        - Found → PriceEstimate{min: precio_min, max: precio_max, source: 'catalog'}
     c. If both miss → null (unresolved)
     d. If quantity present → multiply min/max by quantity
     e. Update list_items.estimated_price = (min+max)/2 (midpoint for storage)
5. Aggregate: sum all resolved min → total_min, sum all resolved max → total_max
6. Return ListPriceEstimate{total_min, total_max, items[], resolved_count, unresolved_count}
```

#### Confirm real prices (POST /api/lists/{list}/confirm-prices)

```
1. User enters total and/or per-item prices in ConfirmPriceModal
2. PriceEstimationController::confirmPrices validates
3. If per-item prices provided:
   For each {item_id, price}:
     a. Verify item belongs to $list
     b. Find most recent producto_historial row WHERE user_id=user AND LOWER(producto_nombre)=LOWER(item.name)
     c. UPDATE precio_real = $price
     d. Also update list_items.estimated_price = $price (replace estimate with real)
4. Total is logged (no distribution to per-item)
5. Return 200 {data: {updated_count: N}}
```

#### Seed catalog prices (php artisan prices:seed-catalog)

```
1. Load all producto_catalogo rows (SELECT nombre, categoria)
2. Chunk into batches of 50
3. For each batch:
   a. Build prompt: "For each Spanish supermarket product, estimate the typical price range in EUR (min and max) in a Spanish supermarket in 2025."
   b. Call ClaudeClient::estimateCatalogPrices($products)
   c. Parse JSON response: [{nombre, precio_min, precio_max}]
   d. UPDATE producto_catalogo SET precio_min=X, precio_max=Y WHERE nombre=Z
4. Log: "Updated N products with price ranges"
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| `estimateForList` | No explicit transaction. Per-item UPDATEs to `list_items.estimated_price` are individual. | Each item is independent. Partial failure (e.g., DB error on item 15 of 20) leaves items 1-14 with updated prices. Acceptable — the user can retry. |
| `confirmPrices` per-item | Wrap all per-item UPDATEs in a single `DB::transaction`. | All-or-nothing: either all real prices are recorded or none. Prevents partial Layer 1 population. |
| `SeedProductCatalogPrices` | Per-batch: each batch's UPDATEs in a transaction. | If one batch fails, previous batches are committed. The command is re-runnable. |

## Data Model

### New Tables
None.

### Modified Tables

| Table | Change | Field | Type | Default |
|-------|--------|-------|------|---------|
| `producto_catalogo` | Add column | `precio_min` | DECIMAL(8,2) | NULL |
| `producto_catalogo` | Add column | `precio_max` | DECIMAL(8,2) | NULL |

Both nullable, no backfill required. `ALGORITHM=INSTANT` on MySQL 8.

### Migrations

1. **`2026_04_14_100002_add_price_columns_to_producto_catalogo_table.php`**
   - `up()`: add `precio_min` + `precio_max`
   - `down()`: drop both columns

### API Changes

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/lists/{list}/estimate-prices` | POST | `auth:api` + ownership | Batch-estimate prices for all items, persist to `estimated_price`, return aggregated total |
| `/api/lists/{list}/confirm-prices` | POST | `auth:api` + ownership | Record real prices (total + optional per-item). Per-item feeds Layer 1. |

### DTOs

```php
// App\Support\Price\PriceEstimate
class PriceEstimate {
    public function __construct(
        public readonly string $productName,
        public readonly float $min,
        public readonly float $max,
        public readonly string $source, // 'history' | 'catalog'
    ) {}
}

// App\Support\Price\ListPriceEstimate
class ListPriceEstimate {
    public function __construct(
        public readonly float $totalMin,
        public readonly float $totalMax,
        /** @var array<int, array{item_id: int, name: string, min: ?float, max: ?float, source: ?string}> */
        public readonly array $items,
        public readonly int $resolvedCount,
        public readonly int $unresolvedCount,
    ) {}
}
```

### ClaudeClientInterface Extension

```php
/**
 * Estimate price ranges for a batch of Spanish supermarket products.
 *
 * @param  array<int, array{nombre: string, categoria: ?string}>  $products
 * @return array{
 *     prices: array<int, array{nombre: string, precio_min: float, precio_max: float}>,
 *     estimated_cost_usd: float,
 * }
 */
public function estimateCatalogPrices(array $products): array;
```

System prompt for price seeding:

```
Eres un experto en precios de supermercados espanoles. Para cada producto, estima el rango de precio tipico en EUR (minimo y maximo) que un consumidor encontraria en un supermercado medio en Espana en 2025.
Devuelve un array JSON estricto con un objeto por producto: nombre (exactamente como se proporciona), precio_min (float, en EUR), precio_max (float, en EUR).
Reglas:
- Precios en EUR, sin simbolo.
- Rango realista: el minimo es el precio mas bajo en supermercados economicos (Mercadona, Dia), el maximo es el precio en supermercados premium (El Corte Ingles).
- Si no conoces el precio exacto, estima basandote en la categoria del producto.
Responde SOLO con el array JSON.
```

### Config Changes

`config/ai.php` — add:

```php
'prices' => [
    'seed_model' => env('AI_PRICES_SEED_MODEL', 'claude-haiku-4-5-20251001'),
    'seed_max_tokens' => 4000,
    'seed_batch_size' => 50,
],
```

### ProductoCatalogo Model Update

Add `precio_min` and `precio_max` to fillable + casts.

## Performance

- **Estimate for list**: N items × 2 DB queries per item (Layer 1 + Layer 2). For 25 items: 50 queries max. All are indexed lookups (user_id + producto_nombre, nombre). Total: <100ms.
- **Layer 1 query** uses existing index `(user_id, lista_id)` on `producto_historial` — the `producto_nombre` filter is a scan within the user's rows but bounded by the index prefix. Acceptable.
- **Layer 2 query** on `producto_catalogo.nombre` — ~250 rows, full scan is instant.
- **No N+1**: the service iterates `$list->items` (already loaded) and does 2 indexed queries per item. Could be optimized to batch-load Layer 2 prices for all items in one query, but the current approach is simpler and <100ms total is under any UX threshold.

### Future optimization (Phase B)
When Layer 3/4 are added (HTTP calls), the per-item resolution should switch to batch: collect all unresolved items after L1+L2, batch-query OFF API, batch-prompt Claude for remainder.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| **Extend producto_catalogo (decision #2)** | No new table, reuse existing product data | Couples catalog and pricing | **Selected** — simpler, 250 rows, same lifecycle |
| Separate precios_catalogo table | Decoupled | Extra JOIN, extra model, more migration | Rejected |
| **On-demand recalculate (decision #4)** | No latency on item mutations, clear UX | User must click a button | **Selected** — real-time per-mutation is 50+ queries per item change |
| Real-time per-mutation | Instant prices | 50+ queries per item add/delete, latency on every mutation | Rejected |
| **Midpoint for estimated_price storage** | Single value fits the existing column | Loses the range info in DB (range only in API response) | **Selected** — the range is computed on-the-fly from the 2 layers, the stored value is just for display cache |
| **Haiku for price seeding (not Sonnet)** | Cheaper (5 batch calls × Haiku ≈ $0.02 total) | Slightly less accurate | **Selected** — price ranges don't need Sonnet quality |
| **100% purchased trigger for HU-702 (decision #7)** | Natural moment to ask | Fires on the last toggle, could be jarring | **Selected per user decision** — non-blocking modal mitigates |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Claude-generated prices are wildly inaccurate | Medium | Medium | Disclaimer "Estimación" always shown. User can confirm real prices (HU-702) which overwrites estimates via Layer 1. |
| Product name matching fails (user writes "Leche" but catalog has "Leche entera") | Medium | High | LOWER() comparison helps. Exact match is V1 limitation. Phase B Claude can do fuzzy matching. |
| Seed command costs money | Low | Low | ~250 products ÷ 50/batch = 5 Claude calls × Haiku ≈ $0.02. Negligible. |
| Per-item resolution too slow for large lists | Low | Low | 25 items × 2 queries = 50 indexed lookups ≈ <100ms. |
| ConfirmPriceModal appears at wrong moment (user toggles last item accidentally) | Low | Low | Modal is dismissable. No data loss on dismiss. |
| `estimated_price` midpoint loses range precision | Low | Always | Range is recomputed from source on next estimate. The stored value is a cache, not the source of truth. |

## Open Questions

None.

## Implementation Notes

### Suggested S4 execution order

1. Migration (2 columns on producto_catalogo)
2. Update ProductoCatalogo model (fillable + casts)
3. ClaudeClientInterface + ClaudeClient + FakeClaudeClient (estimateCatalogPrices)
4. SeedProductCatalogPrices command
5. Run seed command to populate prices
6. DTOs (PriceEstimate, ListPriceEstimate)
7. PriceEstimationService (4 methods)
8. FormRequests + Controller + Routes
9. Backend tests
10. Run full backend suite
11. Frontend: price bar in ListDetailPage + ConfirmPriceModal
12. Frontend tests
13. Run full frontend suite

### Frontend work identified
YES — ListDetailPage modifications + new modal. S4-BOTH. `has_ui_changes = YES`.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation (S4-BOTH)
- Required Artifacts: `01-scope.md`, `02-prd.md`, `03-technical-design.md`
