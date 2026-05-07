# Technical Design — FEAT-EPIC7-PRICES

## Architecture

`PriceEstimationService` implements a layered pipeline. Phase A has 4 layers (personal history, exact catalog, fuzzy catalog, cache+Claude). Phase B (OFF/external APIs) deferred.

## Data Flow

```
Estimate prices for list (POST /api/lists/{list}/estimate-prices):
  → PriceEstimationService::estimateForList(user, list)
    For each item in list:
      1. Layer 1 — Personal history:
         SELECT precio_real FROM producto_historial
         WHERE user_id = ? AND LOWER(producto_nombre) = LOWER(item.name)
         ORDER BY fecha_compra DESC LIMIT 1
         → Return as point estimate (no range; user's own price)

      2. Layer 2 — Exact catalog:
         SELECT precio_min, precio_max FROM producto_catalogo
         WHERE LOWER(nombre) = LOWER(item.name)
         → Return range

      3. Layer 3a — Fuzzy catalog:
         OR query: each word > 2 chars in item.name
         SELECT precio_min, precio_max WHERE ... LIKE '%word%' OR ...
         → Best partial match

      4. Layer 3b — Cache lookup:
         SELECT * FROM price_cache
         WHERE LOWER(input_name) = LOWER(item.name) AND expires_at > now()

      5. Layer 3c — Claude (if within 50/user/day throttle):
         → ClaudeClient::estimatePrice(item.name)
         → INSERT price_cache { input_name, precio_min, precio_max, expires_at: +30d }

    Persist midpoint: UPDATE list_items SET estimated_price = (min+max)/2
    Return: { total_min, total_max, items: [{name, min, max, source}] }

Confirm prices (POST /api/lists/{list}/confirm-prices):
  If per-item prices provided:
    → For each item: INSERT producto_historial { precio_real: actual_price }
    → UPDATE list_items SET estimated_price = actual_price
  If total-only:
    → Informational log only (no per-item distribution)

Catalog seeding (prices:seed-catalog):
  One-time command, re-runnable (idempotent via UPSERT)
  Sends 250 products in batches to Claude Haiku (~$0.02 total)
  → UPDATE producto_catalogo SET precio_min, precio_max
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Layer 1 returns point estimate (no range) | Personal price is exact; ranges add false uncertainty |
| On-demand recalculation | Avoids per-mutation latency (N queries × each mutation) |
| 30-day cache for Claude responses | Claude results don't change daily; reduces cost |
| Total-only confirm doesn't distribute to items | Distributing total to items would create artificial data |

## Gotchas

- Product name matching via `LOWER()` comparison — exact only (V1 limitation)
- Unit conversion: items in grams/mL divided by 1000 before price multiplication
- Per-item resolution: 2-5 queries × N items — acceptable for N≤25
- Phase B (OFF + real Claude real-time) deferred by explicit user decision
