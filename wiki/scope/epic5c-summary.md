# FEAT-EPIC5C-SUMMARY — Weekly Summary Emails

**Complexity:** HIGH (14-20h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-505 | Weekly intelligent summary: Mondays 08:00 Madrid, Claude generates 4-week summary, email opt-in, in-app banner, convert-to-list, ≥3 weeks gate | Implemented |

## Complexity Classification

- Cron + eligibility: MEDIUM — activity-based proxy (no last_login_at column)
- Claude generation: LOW (reuses Epic 5A guardrails)
- Email dispatch + unsubscribe: MEDIUM — signed URL, GDPR compliant

## Key Dependencies

- Laravel scheduler (already active)
- Epic 5A/5B AI guardrails
- `weekly_summaries` table with UNIQUE(`user_id`, `week_start_date`)
- Signed unsubscribe URL (30-day TTL, stateless)

## Design Decisions

- Eligibility: email verified + purchase activity in last 60 days + ≥3 weeks history
- Email opt-in defaults to OFF (GDPR marketing classification)
- In-app banner defaults to ON
- Idempotency: UNIQUE constraint is source of truth (no double-sends)
- Per-user try/catch — loop never aborts on individual failure
- Unsubscribe: `URL::temporarySignedRoute()` 30-day TTL, no DB state

## Deviations

- `last_login_at` column doesn't exist → used `producto_historial` activity as proxy
- Frontend Stitch screen deferred (MCP unavailable during implementation)

## Review Findings

- Item inserts outside transaction (low risk, non-blocking)
- `historyByWeek` consolidable to 1 query (optimization, non-blocking)
- 523 backend tests passing (62 new)
