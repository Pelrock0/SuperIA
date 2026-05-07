# Technical Design — FEAT-EPIC5C-SUMMARY

## Architecture

`WeeklySummaryService` is a single service orchestrating eligibility → generation → persistence → dispatch. Cron-triggered via Laravel scheduler.

## Data Flow

```
Cron: Mondays 08:00 Europe/Madrid
  → WeeklySummaryService::eligibleUsers()
    SELECT users WHERE:
      email_verified_at IS NOT NULL
      AND is_active = true
      AND EXISTS (
        SELECT 1 FROM producto_historial
        WHERE user_id = users.id
        AND fecha_compra > NOW() - 60 days  ← activity proxy
      )
      AND (
        SELECT COUNT(DISTINCT ISO_WEEK(fecha_compra))
        FROM producto_historial WHERE user_id = users.id
      ) >= 3

  For each eligible user (try/catch, never abort):
    → generateForUser(user):
        week_start = currentWeekStart()
        Try INSERT weekly_summaries { user_id, week_start_date, status='pending' }
          ON DUPLICATE KEY: re-query (idempotency)
        Check BudgetCap + AiUsageTracker + CircuitBreaker
        Build context: last 4 weeks product names (sanitized) + active list items + month
        → ClaudeClient::generateWeeklySummary(context)
        → UPDATE weekly_summaries SET payload_json=..., status='generated', claude_cost_usd=...

    → dispatchEmailFor(summary):
        RE-READ user.weekly_summary_email_opt_in (avoids stale state)
        IF opted_in: Mail::to(user)->queue(WeeklySummaryMail)
        UPDATE status = 'dispatched', dispatched_at = now()

In-app endpoints:
  GET /api/weekly-summary/latest → latest for current week (or null)
  POST /api/weekly-summary/dismiss → { dismissed_at: now() }
  POST /api/weekly-summary/{id}/convert-to-list → ShoppingListService::create() (freemium-gated)

Unsubscribe:
  GET /unsubscribe/weekly-summary/{user}  (web route, signed 30d TTL)
  → UPDATE users SET weekly_summary_email_opt_in = false
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| UNIQUE(user_id, week_start_date) | Source of truth for idempotency; race-safe without SELECT+INSERT |
| Per-user try/catch | One user's Claude failure doesn't abort the entire batch |
| Re-read opt-in before dispatch | User could unsubscribe between generation and dispatch (async gap) |
| Activity-based eligibility (not login-based) | `last_login_at` column doesn't exist in schema |
| 30-day signed URL (stateless) | No unsubscribe tokens table needed |

## Gotchas

- `convertToList()` propagates `OverflowException` from `ShoppingListService` if freemium limit reached
- Week boundary is ISO Monday 00:00 in `config('ai.timezone')` (default `Europe/Madrid`)
- Kill switch is at command level (don't run the command), not inside the service
- Cron frequency: the command itself is idempotent; running it twice in a week is safe
