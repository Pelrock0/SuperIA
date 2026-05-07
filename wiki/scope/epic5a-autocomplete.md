# FEAT-EPIC5A-AUTOCOMPLETE — AI Autocomplete Pipeline

**Complexity:** HIGH (40-50h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-5A-1 | Autocomplete from personal purchase history (weighted by recency) | Layer 1 |
| HU-5A-2 | Autocomplete from product catalog (~250 Spanish products) | Layer 2 |
| HU-5A-3 | AI fallback (Claude) when local results <3, gated by budget+quota+circuit breaker | Layer 3 |
| HU-5A-4 | Purchase history management: view weighted list, forget individual products | Implemented |

## Complexity Classification

- AI guardrails infrastructure: HIGH — BudgetCap, AiUsageTracker, CircuitBreaker (reused by all AI epics)
- Suggestion pipeline: MEDIUM — 3-layer search with dedup
- Frontend: MEDIUM — combobox, dual debounce, history management UI

## Key Dependencies

- `app/Support/Ai/*` namespace (7 reusable classes, foundation for all AI epics)
- `producto_historial` (Layer 1 source)
- `producto_catalogo` seeder (~250 products)
- ClaudeClient (HTTP, no SDK)

## Design Decisions

- LIKE prefix (not FULLTEXT) for <20ms AC-10 performance target
- Recency weighting: 30d=2.0x, 90d=1.0x, older=0.3x (SQL CASE)
- AI gate order: BudgetCap → AiUsageTracker → CircuitBreaker → call
- Budget cap: DB-backed, persists across deploys; race condition accepts pennies overshoot
- Circuit breaker: cache-backed (Redis/file), 3 failures → 60s cooldown
- HistoryAnonymizer strips PII before Claude prompt
- Dual debounce: 150ms (layers 1+2), 2000ms (layer 3)
- `?include_ai=1` query param required for AI fallback (opt-in)

## Deviations

- FULLTEXT migration retained (future multi-word), but LIKE used for V1

## Review Findings

- PII never reaches Claude (unit test asserts absence in FakeClaudeClient payload)
- Cross-user isolation: history and quota both scoped by user_id
- 394 backend + 186 frontend tests passing
