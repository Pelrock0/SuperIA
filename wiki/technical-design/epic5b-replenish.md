# Technical Design — FEAT-EPIC5B-REPLENISH

## Architecture

`ReplenishmentSuggestionService` uses SQL aggregate queries + PHP filtering. `ComplementarySuggestionService` uses a 2-step co-occurrence query with AI fallback. Both reuse Epic 5A guardrails.

## Data Flow

```
Replenishment (GET /api/dashboard/replenishment):
  Cache::remember(user_id, 5min) {
    1. Get active list product names (exclusion list)
    2. Get silenced products (UserSilencedProduct)
    3. Get dismissed products (AiDismissedSuggestion, 24h window)
    4. SELECT product_stats FROM producto_historial:
       - avg_days_between_purchases
       - days_since_last_purchase
       - purchase_count
       WHERE user_id = ? AND purchase_count >= MIN_OCCURRENCES(3)
    5. Filter PHP: urgency_ratio = days_since_last / (avg_days * 0.8)
       Keep if urgency_ratio > 1.0 AND not in exclusions
    6. Sort by urgency_ratio DESC, take 3
  }

Accept replenishment (POST /api/replenishment/accept):
  → ListItemService::create(target_list, product)
  → invalidateCache(user)

Silence (POST /api/replenishment/silence):
  → INSERT user_silenced_products { user_id, producto_nombre }
  → invalidateCache(user)

Ignore (POST /api/replenishment/ignore):
  → UPSERT ai_dismissed_suggestions { user_id, producto_nombre, dismissed_until: now+24h }
  → invalidateCache(user)

Complementary suggestions (GET /api/suggestions/complements?product=X&list_id=Y):
  Step 1 — Check if ≥5 completed lists exist:
    IF < 5: go to AI fallback
  Step 2 — Co-occurrence query:
    SELECT product_b, co_count, ratio FROM (
      SELECT ph2.producto_nombre,
             COUNT(*) as co_count,
             COUNT(*) / base_count as co_ratio
      FROM producto_historial ph1
      JOIN producto_historial ph2 ON ph1.lista_id = ph2.lista_id
        AND ph2.producto_nombre != ph1.producto_nombre
        AND ph1.producto_nombre = ?
      WHERE ph1.user_id = ?
      LIMIT 50    ← DoS protection
    ) WHERE co_ratio >= 0.6
    ORDER BY co_ratio DESC
    LIMIT 2
  Filter: exclude products already in list Y (case-insensitive)
  Step 3 — AI fallback (if co-occurrence < 2 results):
    Quota checks → PromptSanitizer → Claude → parse → return
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| `avg_days × 0.8` factor | Trigger slightly before expected need (configurable) |
| Co-occurrence LIMIT 50 | Prevents full table scan on users with large purchase history |
| Cache 5min on replenishment | Expensive aggregation; user actions invalidate explicitly |
| Shared AI quota pool | All AI operations draw from same 20/day limit (Epic 5A refactor) |

## Gotchas

- `ai_dismissed_suggestions` table grows unboundedly → cleanup command added after review
- Co-occurrence query has no SQL LIMIT on outer result (capped in PHP at 2) — acceptable for V1
- Complement chip is async best-effort: dispatched after item create, does not block
