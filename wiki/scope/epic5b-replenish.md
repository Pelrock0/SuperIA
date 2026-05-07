# FEAT-EPIC5B-REPLENISH — Replenishment Alerts + Complementary Suggestions

**Complexity:** HIGH (35-45h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-503 | Dashboard banner: suggest products bought before (frequency+recency), exclude active lists/silenced, max 3, accept/ignore/silence | Implemented |
| HU-504 | Inline complementary suggestions: co-occurrence ≥60%, Claude fallback for new users, max 2 | Implemented |

## Complexity Classification

- Replenishment algorithm: MEDIUM — SQL aggregate + PHP filter
- Co-occurrence: MEDIUM — 2-step SQL query with ratio threshold
- AI fallback integration: LOW (reuses Epic 5A guardrails)

## Key Dependencies

- Epic 5A AI guardrails (BudgetCap, AiUsageTracker, CircuitBreaker) — breaking change: shared quota
- 2 new tables: `user_silenced_products`, `ai_dismissed_suggestions`
- Cache: 5min TTL on replenishment endpoint

## Design Decisions

- Replenishment frequency: `avg_days_between × 0.8` factor (configurable)
- Co-occurrence minimum ratio: 60% (configurable)
- AiUsageTracker quota refactored to shared pool across all AI operations (1 Epic 5A test rewritten)
- Complement chip auto-hides after 30s (non-blocking UX)
- Cache invalidation explicit on every accept/ignore/silence action
- Co-occurrence SQL capped at 50 rows pre-filter (DoS protection)

## Deviations

- Shared AI quota refactor was a breaking change to Epic 5A (mitigated: only 1 test rewritten)

## Review Findings

- Cleanup of SAML transitive CVE removed HIGH-severity vulnerability
- Non-blocking follow-ups: extract excluded-products helper, add SQL LIMIT to co-occurrence
- 681 tests passing
