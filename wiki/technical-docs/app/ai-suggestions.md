# Technical Docs — AI Suggestions

**Keywords:** autocomplete, suggestions, Claude, AI, budget, quota, circuit breaker, complements, replenishment

## Autocomplete (`GET /api/suggestions?q=X`)

Three-layer search. Returns `{ suggestions: [{name, source}], ai_fallback_used, ai_limit_reached }`.

```
Layer 1 — Personal history (weighted by recency):
  producto_historial WHERE user_id=? AND name LIKE 'X%'
  Weights: 30d=2.0x, 90d=1.0x, older=0.3x
  Source: 'history'

Layer 2a — User's past list items:
  list_items JOIN shopping_lists WHERE user_id=? AND name LIKE 'X%'
  DISTINCT results
  Source: 'list'

Layer 2b — Product catalog (~250 products):
  producto_catalogo WHERE nombre LIKE 'X%'
  Source: 'catalog'

Dedup: case-insensitive, merge, cap at 5

Layer 3 — Claude AI (only if result count < 3 AND ?include_ai=1):
  BudgetCap → AiUsageTracker → CircuitBreaker → PromptSanitizer → HistoryAnonymizer
  → ClaudeClient::suggest(cleaned_q, anon_history)
  Source: 'ai'
```

## Complementary Suggestions (`GET /api/suggestions/complements?product=X&list_id=Y`)

Co-occurrence from purchase history, AI fallback for new users.

```
IF user has < 5 completed lists: skip to AI fallback
ELSE:
  SELECT co-occurring products (co_ratio ≥ 60%, LIMIT 50 pre-filter)
  Filter: exclude products already in list Y
  Return max 2 suggestions

AI fallback: same quota gates as autocomplete
```

## Replenishment Alerts (`GET /api/dashboard/replenishment`)

Cached 5min per user. Returns max 3 suggestions.

```
Frequency algorithm:
  avg_days_between = avg(days_between purchases) from producto_historial
  urgency_ratio = days_since_last / (avg_days * 0.8)
  IF urgency_ratio > 1.0 → suggest (needs replenishing)

Exclusions: already in active list, silenced, dismissed (<24h)
```

Actions:
- `POST /api/replenishment/accept { product, list_id }` → adds item + invalidates cache
- `POST /api/replenishment/ignore { product }` → dismisses 24h
- `POST /api/replenishment/silence { product }` → permanent silence

## AI Guardrail Stack

| Guard | Limit | When Exceeded |
|-------|-------|--------------|
| BudgetCap | $50/month global | Returns `budget_exceeded` status |
| AiUsageTracker | 20 req/day per user | Returns `quota_exceeded` status |
| CircuitBreaker | 3 failures → 60s | Returns `circuit_open` status |

All AI calls: `PromptSanitizer::clean(input)` before sending. `HistoryAnonymizer` strips PII (product names only, no user data).

## Claude Client Notes

- HTTP client (not Anthropic SDK) — lighter dependency
- Returns token counts (`input_tokens`, `output_tokens`) logged to `ai_usage_log`
- Default model: `claude-haiku-4-5-20251001` (fast, cheap for suggestions)
- Timeout: `config('ai.timeout_seconds')` seconds
