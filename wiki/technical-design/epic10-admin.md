# Technical Design — FEAT-EPIC10-ADMIN

## Architecture

Backpack CRUD admin (Blade + Tabler theme). Zero React involvement. `AdminMetricsService` provides dashboard aggregates. Auth integration: `is_active` flag checked in `AuthService`.

## Data Flow

```
Admin dashboard (GET /admin/dashboard):
  → AdminMetricsService::getMetrics()
    1. total_users: COUNT(*) FROM users
    2. active_users_7d: COUNT WHERE last action in producto_historial > 7d ago
    3. lists_today: COUNT shopping_lists WHERE created_at >= today
    4. ai_usage_today: COUNT ai_usage_log WHERE date = today
    5. ai_cost_month: SUM estimated_cost_usd FROM ai_usage_log WHERE month = current
    6. waitlist_pending: COUNT waitlist_entries WHERE status = 'pending'
  → Blade view with Bootstrap cards

User CRUD (/admin/user):
  Extends Backpack\CRUD\UserCrudController
  Added columns: lists_count, ai_usage_30d, plan, is_active
  Added operations: toggle is_active, set plan, set ai_daily_limit_override

is_active enforcement:
  AuthService::login()
    → Check is_active after Hash::check() AND before JWT issuance
    → Return generic error (no enumeration of reason)

Per-user AI limit override:
  AiUsageTracker::canUse(user, operation)
    → quota = user.ai_daily_limit_override ?? config('ai.daily_quota')
    → check COUNT ai_usage_log WHERE user_id = ? AND date = today AND operation = ?

AI usage log CRUD (/admin/ai-usage-log):
  Read-only Backpack CRUD on AiUsageLog model
  Filters: date range, operation type, user
  Footer row: SUM of estimated_cost_usd

Telescope (/telescope):
  TelescopeServiceProvider gates by Backpack middleware
  PLUS Spatie role check: user.hasRole('superadmin')
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Backpack-only admin | Pre-installed; framework handles CRUD boilerplate; no React needed |
| `is_active` on users (not soft-delete) | Reversible; user data preserved for GDPR export |
| `ai_daily_limit_override` nullable | Null coalesces to global config (clean default) |
| `is_active` checked after password | Prevent timing oracle (attacker can't distinguish wrong-password vs deactivated) |

## Gotchas

- Backpack uses traits (not class extension) → `#[\Override]` PHP attribute must NOT be used in Backpack CRUD controllers (removed during review)
- UserFactory must set `is_active => true` explicitly — DB defaults don't propagate to factory-created models in tests
- Telescope URL is accessible at `/telescope` — not under `/admin/` prefix — gated only by service provider logic
