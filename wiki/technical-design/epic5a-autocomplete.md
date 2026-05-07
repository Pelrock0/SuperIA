# Technical Design — FEAT-EPIC5A-AUTOCOMPLETE

## Architecture

`ProductSuggestionService` orchestrates a 3-layer search. `app/Support/Ai/*` namespace provides reusable AI guardrails (7 classes, all independently testable).

## Data Flow

```
GET /api/suggestions?q=le[&include_ai=1]
  → ProductSuggestionService::suggest(user, q, includeAi)

Layer 1 — Personal history (weighted):
  SELECT producto_nombre, weighted_score FROM producto_historial
  WHERE user_id = ? AND producto_nombre LIKE 'le%'
  ORDER BY
    CASE WHEN fecha_compra > NOW()-30d THEN 2.0
         WHEN fecha_compra > NOW()-90d THEN 1.0
         ELSE 0.3 END DESC
  LIMIT 5

Layer 2a — User's past list items:
  SELECT DISTINCT li.name FROM list_items li
  JOIN shopping_lists sl ON sl.id = li.shopping_list_id
  WHERE sl.user_id = ? AND li.name LIKE 'le%'
  LIMIT 5

Layer 2b — Product catalog:
  SELECT nombre FROM producto_catalogo
  WHERE nombre LIKE 'le%'
  LIMIT 5

Dedup:
  Merge results, case-insensitive, cap total at 5

Layer 3 (opt-in, only if results < 3):
  BudgetCap::canSpend(estimated_cost)
  → AiUsageTracker::canUse(user, 20 free/day)
  → CircuitBreaker::allow() [3 failures → 60s cooldown]
  → PromptSanitizer::clean(q)
  → HistoryAnonymizer::topProducts(user, 20 names only)  ← no PII
  → ClaudeClient::suggest(cleaned_q, anon_context)
  → AiUsageTracker::record(user, tokens, cost)
  → Return AI suggestions merged into result

Return: { suggestions: [{name, source}], ai_fallback_used, ai_limit_reached }
```

## AI Support Layer (`app/Support/Ai/`)

| Class | Responsibility |
|-------|---------------|
| `ClaudeClient` | HTTP calls to Anthropic API (no SDK); returns typed response |
| `FakeClaudeClient` | Test double; captures prompt for assertion |
| `BudgetCap` | Global monthly spend cap ($50 USD default); DB-backed |
| `AiUsageTracker` | Per-user daily quota (20 free / 50 premium); DB-backed |
| `CircuitBreaker` | 3 failures → 60s open; cache-backed (Redis/file) |
| `PromptSanitizer` | Strips injection patterns (13 regex), enforces char limit |
| `HistoryAnonymizer` | Returns `string[]` product names only (no PII) |

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| LIKE prefix (not FULLTEXT) | <20ms target; FULLTEXT has higher overhead for prefix-only |
| Recency weighting via SQL CASE | Tunable without migration; 3 tiers cover most patterns |
| HTTP client (not Anthropic SDK) | Avoids SDK version coupling; lighter dependency |
| Budget cap race condition accepted | Parallel calls could overshoot by pennies; cost acceptable |

## Gotchas

- `users.plan` column doesn't exist yet → all users treated as Free (quota = 20/day)
- FULLTEXT migration retained for future multi-word search (currently unused)
- Prompt sanitizer is pattern-based (not semantic); novel injection patterns could bypass it
- BudgetCap and AiUsageTracker both use DB writes; don't call from read-heavy loops
