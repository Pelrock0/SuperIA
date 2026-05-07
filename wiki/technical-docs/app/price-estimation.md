# Technical Docs — Price Estimation

**Keywords:** prices, estimation, catalog, history, Claude, layers, precio

## Overview

`PriceEstimationService` implements a 4-layer price pipeline. Phase A only (Phase B with external APIs deferred).

## Trigger

On-demand via button ("Recalcular precios") — not triggered automatically on item mutations.

```
POST /api/lists/{list}/estimate-prices
→ PriceEstimationService::estimateForList(user, list)
→ Returns { total_min, total_max, items: [{name, min, max, source}] }
```

## Layer Pipeline (per item)

| Layer | Query | Source Tag | Returns |
|-------|-------|-----------|---------|
| 1 | `SELECT precio_real FROM producto_historial WHERE user_id=? AND LOWER(nombre)=LOWER(?)` | `history` | Point estimate (user's last paid price) |
| 2 | `SELECT precio_min, precio_max FROM producto_catalogo WHERE LOWER(nombre)=LOWER(?)` | `catalog` | Range |
| 3a | Fuzzy: OR query on each word > 2 chars in item name | `catalog_fuzzy` | Range (best partial match) |
| 3b | `SELECT * FROM price_cache WHERE LOWER(input_name)=LOWER(?) AND expires_at>now()` | `cache` | Range |
| 3c | Claude Haiku → INSERT price_cache (30d TTL) | `ai` | Range |

Layer 1 short-circuits if found. Each layer tried sequentially.

## Price Display

- Summary bar: `Estimación: {total_min}€ – {total_max}€`
- Per-item: expandable breakdown
- Stored: midpoint = `(min + max) / 2` → `list_items.estimated_price`

## Confirming Real Prices

```
POST /api/lists/{list}/confirm-prices { items: [{item_id, price}]? total? }
```

- Per-item prices → INSERT `producto_historial.precio_real` + UPDATE `list_items.estimated_price`
- Total-only → informational log only (not distributed to items)

## Catalog Seeding

```bash
php artisan prices:seed-catalog
```

One-time command, idempotent (UPSERT). Sends 250 products in batches to Claude Haiku (~$0.02 total). Re-runnable safely.

## Rate Limits

- Claude layer throttled: 50 calls/user/day (configurable)
- Cached results avoid Claude on subsequent runs (30-day cache)

## Gotchas

- Name matching via `LOWER()` — exact match only (V1). Fuzzy layer catches word subsets but not typos.
- Unit conversion: items in grams/mL divided by 1000 before price multiplication (e.g., 500g → 0.5 units)
- Phase B (OFF price lookups + real-time Claude per item) explicitly deferred — don't implement without user approval
