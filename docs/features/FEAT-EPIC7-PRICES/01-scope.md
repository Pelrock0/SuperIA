# Scope Analysis: FEAT-EPIC7-PRICES

## Feature Request

Implement HU-701 (Ver estimación de precio total de una lista) and HU-702 (Confirmar precio real tras la compra) from `docs/Superia_HU_v3.md` § Épica 7.

**HU-701**: 4-layer price estimation pipeline per item in a shopping list:
1. **Layer 1**: User's own `producto_historial.precio_real` — most accurate, always consulted first
2. **Layer 2**: Static average-price dataset — table generated with Claude ONCE, updated monthly
3. **Layer 3**: Open Food Facts API — public free source for product identification
4. **Layer 4**: Claude API real-time fallback — only for products not found in layers 1-3

Price displayed as range ("35€ — 45€"), auto-recalculates on item add/remove, per-item breakdown accessible.

**HU-702**: After completing a list, user can optionally input the real price paid (total or per-item). This feeds Layer 1 for future estimates.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 20–30 hours |
| Confidence | Low |

## Justification

**HIGH** with **low confidence** because:

- **First external non-Claude API**: Open Food Facts (OFF) introduces a new HTTP client dependency, SSRF surface, rate limiting, error handling for a third-party service the project has no experience with.
- **4-layer pipeline is architecturally complex**: each layer has a different data source, latency profile, and fallback behavior. The orchestration logic (try L1 → fallback L2 → fallback L3 → fallback L4) is a chain-of-responsibility pattern with per-item resolution.
- **Static price catalog**: new table + seeder command (similar to `ProductoCatalogo` but with price ranges). Monthly refresh job OR manual command. Generation via Claude (another API call pattern, batch-style like `SeedProductCatalog`).
- **Per-item price calculation on list changes**: "auto-recalculates on add/remove" implies either backend computation on every list mutation (latency concern) or frontend-cached prices with periodic refresh.
- **Price range display**: min-max calculation across layers with different confidence levels. How to combine a precise Layer 1 price ($1.20) with a vague Layer 4 estimate ($1.00-$2.00)?
- **HU-702 feedback loop**: new UI for per-item price entry on a completed list. Touches existing `list_items` and `producto_historial` models.
- **Open Food Facts API uncertainty**: OFF is primarily a nutrition/ingredients database, NOT a price database. Spanish price data is crowdsourced and extremely sparse. The HU says "para identificar productos y categorías" — this suggests OFF is for product matching, not for getting prices. This ambiguity must be resolved before design.

**Existing schema advantages**:
- `producto_historial.precio_real` DECIMAL(8,2) nullable — Layer 1 storage ready (column exists, always null today)
- `list_items.estimated_price` DECIMAL(8,2) nullable — per-item estimate storage ready (column exists, never populated)

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | **High** | 4-layer pipeline is the most complex orchestration in the project. OFF API reliability/data quality unknown. Price range calculation logic is non-trivial. Real-time recalculation performance is a concern for large lists. |
| Data | **High** | Layer 2 static catalog: generating price ranges via Claude for ~250+ products is expensive and the accuracy is questionable (Claude has no access to real Spanish supermarket prices). Layer 1 starts empty (no user has ever entered a price). OFF Spanish price data is sparse-to-nonexistent. |
| Security | Medium | OFF API → outbound HTTP to a third-party. Must enforce URL allowlisting (only `*.openfoodfacts.org`). Claude Layer 4 follows existing guardrails. HU-702 accepts user-submitted prices — input validation for monetary values. |
| Performance | Medium | Per-item price calculation on every list mutation. For a 25-item list with 4-layer resolution, worst case is 25 × (DB lookup + DB lookup + HTTP call + Claude call). Must be async/cached. |
| Operational | Medium | OFF API downtime → layer 3 unavailable. Claude outage → layer 4 unavailable. Static catalog needs monthly refresh. |

## Affected Areas

### Backend
- `app/Services/PriceEstimationService.php` (NEW) — 4-layer pipeline orchestrator
- `app/Support/Ai/ClaudeClientInterface.php` — new method `estimatePrice`
- `app/Support/OpenFoodFacts/` (NEW) — OFF API client wrapper
- `app/Models/PrecioCatalogo.php` (NEW) — static price catalog model
- `database/migrations/` — `precios_catalogo` table
- `app/Console/Commands/SeedPriceCatalog.php` (NEW) — generate price catalog via Claude
- `app/Http/Controllers/PriceEstimationController.php` (NEW) — endpoints
- `app/Http/Controllers/ListItemController.php` — modification to return prices
- `config/ai.php` — price estimation config section

### Frontend
- `resources/js/pages/ListDetailPage.jsx` — price indicator, breakdown toggle
- `resources/js/components/PriceBreakdown.jsx` (NEW) — per-item prices
- `resources/js/components/ConfirmPriceModal.jsx` (NEW) — HU-702 price entry
- Stitch screens: need to check if price-related screens exist

### Tests
- Service tests for each layer + fallback chain
- OFF API client tests (HTTP mock)
- Controller tests
- Frontend tests
- Price calculation accuracy tests

## Resolved Decisions (S1, 2026-04-12)

All 14 open questions resolved. **Scope restricted to Phase A (Layers 1+2 only)**. Phase B (OFF API + Claude real-time Layer 4) deferred to a future feature.

| # | Decision | Source |
|---|----------|--------|
| 1 | **OFF API**: option (c) — **skip entirely for V1**. Static catalog covers the use case. Defer to Phase B. | User |
| 2 | **Layer 2 data**: option (c) — **extend `producto_catalogo`** with `precio_min`/`precio_max` columns. Populate via `SeedProductCatalogPrices` one-time Claude batch command. | User |
| 3 | **Price range**: option (a) — **exact when Layer 1**, range when Layer 2 estimate. | User |
| 4 | **Recalculation**: option (c) — **batch on demand** via "Recalcular precios" button. Store in `list_items.estimated_price`. Not on every item mutation. | User |
| 5 | **Layer 4 rate limit**: option (a) — **separate 10/day**. Deferred to Phase B (no Layer 4 in Phase A). | User |
| 6 | **HU-702 UX**: option (c) — **progressive**: total first, per-item optional expandable. | User |
| 7 | **HU-702 trigger**: option (a) — **100% items purchased**. Non-blocking prompt. Consistent with HU-306 "limpiar comprados" flow. | User |
| 8 | **Feed back Layer 1**: option (b) — **per-item only** feeds `producto_historial.precio_real`. Total-only logged but not distributed. | User |
| 9 | **Monthly refresh**: option (a) — **manual command** `php artisan prices:refresh-catalog` for V1. | User |
| 10 | **Currency**: **EUR hardcoded**. | User |
| 11 | **Display**: option (c) — **summary bar + expandable per-item breakdown**. | User |
| 12 | **Stitch screen**: no dedicated price screen exists. Follow existing `ListDetailPage` patterns. No MCP fetch needed. | User |
| 13 | **Migration**: extend `producto_catalogo` with 2 nullable columns. No new table. | User |
| 14 | **Scope split**: **Phase A accepted** (Layers 1+2). Phase B (Layer 3 OFF + Layer 4 Claude) is a separate future feature. | User |

> All open questions RESOLVED. No TBDs remain. Feature scoped to Phase A.

## Open Questions (historical — superseded by Resolved Decisions above)

> Per `core.md` § 9 — TBDs must be resolved before advancing past S2 (PRD).

1. **Open Food Facts API purpose**: the HU says "para identificar productos y categorías". Does Layer 3:
   - (a) **Get prices from OFF** — OFF has a `/cgi/search.pl` endpoint that can return price_tags, but Spanish price data is extremely sparse (<5% coverage).
   - (b) **Match product identity only** — use OFF to confirm the product exists and get its canonical category, then look up the category average from Layer 2.
   - (c) **Skip OFF entirely for V1** — Layer 2 (static catalog) already has product names and categories from the existing `producto_catalogo` table. OFF adds complexity and latency for marginal benefit.
   - Recommend **(c)**: defer OFF to V2. The static catalog already covers ~250 products with categories. Adding an external HTTP dependency for sparse data is not worth the complexity in V1.

2. **Layer 2 price data source**: where do the price ranges come from?
   - (a) **Claude generates price ranges** once via a batch command (like `SeedProductCatalog`). Claude has approximate knowledge of Spanish supermarket prices. Accuracy: ±30%.
   - (b) **Manual curation** — import a CSV of average prices from INE (Instituto Nacional de Estadística) or similar.
   - (c) **Add a `precio_min`/`precio_max` column to the existing `producto_catalogo` table** instead of a new table.
   - Recommend **(c)**: extend the existing `producto_catalogo` (250+ products) with two price columns. Use Claude to populate them via a one-time command. Avoid a new table.

3. **Price range calculation**: how to compute the range from multiple layers?
   - (a) Layer 1 is a point value (exact price user paid). Layer 2 is a range (min-max). Layer 4 is an estimate. Use whichever layer resolves first, and display its format.
   - (b) Always show a range, even for Layer 1 (pad ±10% around the exact price to account for price changes over time).
   - Recommend **(a)**: exact prices when available, ranges when estimated.

4. **Recalculation trigger**: "auto-recalculates on add/remove"
   - (a) **Backend: recalculate on every item mutation** (POST/PUT/DELETE item). Store result in `list_items.estimated_price`. Return total in list response. Adds latency to item operations.
   - (b) **Frontend: fetch prices separately** via a `GET /api/lists/{id}/prices` endpoint. Cached per session. Refresh on explicit action or polling. Decoupled from item mutations.
   - (c) **Hybrid**: backend stores per-item prices (from a batch-calculate endpoint), frontend aggregates the total. Recalculate only on user request (button "Recalcular precios"), not on every item mutation.
   - Recommend **(c)**: batch-calculate on demand. Real-time per-mutation is too costly (25 items × 4 layers × network). A "Recalcular precios" button is clearer UX.

5. **Layer 4 rate limit**: HU says 10 Claude calls/day for price estimation. Is this:
   - (a) Separate per-operation cap (like generation's 5/day)?
   - (b) Shared with the existing pool?
   - Recommend **(a)**: separate, per the HU. 10/day via `AiUsageTracker::canUseOperation`.

6. **HU-702 price entry UX**: "precio total real o desglosado por ítem"
   - (a) **Simple**: one text input for total price only. No per-item breakdown.
   - (b) **Full**: input for each item (N inputs). Complex UI for 25-item lists.
   - (c) **Progressive**: start with total, optionally expand to per-item.
   - Recommend **(c)**: total first, per-item is optional expandable.

7. **When does HU-702 trigger?**: "Al completar una lista"
   - (a) When all items are marked as purchased (100% complete).
   - (b) When the user archives the list.
   - (c) When the user manually triggers a "Ya he comprado" action.
   - Recommend **(b)**: on archive, show a non-blocking prompt "¿Cuánto pagaste?"

8. **Feed back into Layer 1**: when the user enters a real price:
   - (a) Update `producto_historial.precio_real` for each matching row.
   - (b) Only if the user enters per-item prices (not total).
   - (c) If total only, distribute proportionally based on estimated weights.
   - Recommend **(b)**: per-item prices feed Layer 1 directly. Total-only is informational (logged but not used for per-item Layer 1 training).

9. **Monthly refresh of Layer 2**: manual command or scheduled job?
   - (a) Manual: `php artisan prices:refresh-catalog`
   - (b) Scheduled: monthly cron
   - Recommend **(a)**: manual for V1. Monthly cron adds operational burden for a dataset that changes slowly.

10. **Currency**: always EUR? Hardcoded or configurable?
    - Recommend: hardcoded EUR for V1 (Spanish market only).

11. **Price display in list detail**: where exactly?
    - (a) A summary bar at the top/bottom of the list ("Estimación: 35€ — 45€").
    - (b) Per-item prices visible inline (in each item row).
    - (c) Both: summary bar + expandable per-item breakdown.
    - Recommend **(c)**: matches HU-701 AC-5 ("desglose accesible pulsando sobre el total").

12. **Stitch screen**: does a price-related screen exist in Stitch? Need to check. If not, follow existing patterns.

13. **`producto_catalogo` table extension**: the existing table has `nombre`, `categoria`, `unidad_tipica`, `cantidad_tipica`. Adding `precio_min`, `precio_max` requires a migration. Is that acceptable vs. a separate table?

14. **Scope split recommendation**: this is a 20-30h feature. Consider splitting:
    - **Phase A** (V1): Layers 1+2 only (personal history + static catalog). No external API. No Claude real-time. Simpler, faster to deliver.
    - **Phase B** (V2): Add Layer 3 (OFF) + Layer 4 (Claude fallback). External API complexity isolated.
    - Does the user want the full 4-layer pipeline in one feature, or is a phased approach acceptable?

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

**Required next step**: STEP 2 — PRD. The 14 open questions must be resolved first. Question #14 (scope split) is the most strategic — it determines whether this is a 12h feature (layers 1+2) or a 30h feature (all 4 layers).

## Transition

- Gate: S1 PENDING (awaiting user approval)
- Next Step: STEP 2 — PRD Writing
- Required Artifacts for Next Step: `01-scope.md`
