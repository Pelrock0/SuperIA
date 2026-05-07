# Technical Docs — Weekly Summary

**Keywords:** summary, email, cron, weekly, unsubscribe, eligibility, dispatch, Monday

## Overview

`WeeklySummaryService` generates AI-powered weekly shopping summaries. Dispatched every Monday 08:00 Europe/Madrid via Laravel scheduler.

## Eligibility Criteria

A user receives a weekly summary if:
1. Email is verified (`email_verified_at IS NOT NULL`)
2. Account is active (`is_active = true`)
3. Has purchase activity in last 60 days (proxy for `last_login_at` which doesn't exist)
4. Has purchase history spanning ≥3 distinct ISO weeks

## Generation Flow

```
php artisan dispatch:weekly-summary  (Monday 08:00 Madrid)
→ WeeklySummaryService::eligibleUsers() → stream
→ For each user (try/catch, never abort):
    week_start = currentWeekStart()  ← ISO Monday in Europe/Madrid timezone
    INSERT weekly_summaries { user_id, week_start_date, status='pending' }
      ON DUPLICATE KEY → re-query (idempotent)
    BudgetCap + AiUsageTracker + CircuitBreaker checks
    Build context: last 4 weeks products + active list items + current month
    → ClaudeClient::generateWeeklySummary(context)
    → UPDATE status='generated', payload_json, claude_cost_usd
    Re-read user.weekly_summary_email_opt_in  ← avoids stale state
    IF opted_in: Mail::to(user)->queue(WeeklySummaryMail)
    UPDATE status='dispatched', dispatched_at
```

## API Endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /api/weekly-summary/latest` | Current week summary (null if not yet generated) |
| `POST /api/weekly-summary/dismiss` | Dismiss in-app banner for current week |
| `POST /api/weekly-summary/{id}/convert-to-list` | Create new list from summary (freemium-gated) |
| `POST /api/settings/weekly-summary-email` | Toggle email opt-in |

## Unsubscribe

Email includes a signed unsubscribe link:
```
GET /unsubscribe/weekly-summary/{user}
→ Validates signed URL (30-day TTL)
→ UPDATE users SET weekly_summary_email_opt_in = false
```

Stateless — no unsubscribe tokens table. `URL::temporarySignedRoute()` provides the security.

## Opt-in Defaults

| Surface | Default | GDPR classification |
|---------|---------|---------------------|
| Email | `false` (off) | Marketing communication |
| In-app banner | `true` (on) | Product feature |

## Key Behaviors

- Kill switch: don't run the command (no in-service toggle)
- Idempotency: `UNIQUE(user_id, week_start_date)` prevents double generation
- Failure isolation: per-user try/catch; one failure doesn't abort the batch
- `convert-to-list` propagates freemium `OverflowException` to frontend (409)
- Re-reads opt-in flag before dispatch (user could unsubscribe between generation and dispatch)
