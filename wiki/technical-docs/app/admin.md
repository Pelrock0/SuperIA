# Technical Docs — Admin Dashboard

**Keywords:** admin, Backpack, metrics, users, deactivate, is_active, Telescope, AI usage

## Overview

Backend-only admin panel using Backpack CRUD + Tabler theme. Accessible at `/admin/`. Zero React frontend involvement.

## Access Control

- Route: `/admin/*` protected by `CheckIfAdmin` middleware
- Roles required: `admin` or `superadmin` (Spatie permissions)
- Telescope: gated additionally by `superadmin` role

## Dashboard (`/admin/dashboard`)

`AdminMetricsService::getMetrics()` returns 6 aggregates:

| Metric | Query |
|--------|-------|
| total_users | `COUNT(*) FROM users` |
| active_users_7d | Users with `producto_historial` activity in last 7 days |
| lists_today | `COUNT shopping_lists WHERE created_at >= today` |
| ai_usage_today | `COUNT ai_usage_log WHERE date = today` |
| ai_cost_month | `SUM estimated_cost_usd WHERE month = current` |
| waitlist_pending | `COUNT waitlist_entries WHERE status = 'pending'` |

## User Management (`/admin/user`)

Extended `UserCrudController` (Backpack). Added capabilities:
- View: lists_count, ai_usage_30d, plan, is_active status
- Edit: toggle `is_active`, set `plan`, set `ai_daily_limit_override`

### `is_active` Flag

When `is_active = false`:
- User cannot login (checked in `AuthService::login()` after password validation)
- Error message is generic (no enumeration of reason)
- User data preserved (not deleted)
- Can be re-activated by admin (toggle back to true)

### Per-User AI Limit Override

```php
$quota = $user->ai_daily_limit_override ?? config('ai.daily_quota');
```

`ai_daily_limit_override = null` → uses global config default.
Set to specific integer to override per-user.

## AI Usage Log (`/admin/ai-usage-log`)

Read-only Backpack CRUD on `AiUsageLog` model.
- Filters: date range, operation type, user
- Footer: SUM of `estimated_cost_usd` for filtered results
- Fields: user, operation, status, date, estimated_cost_usd, input_tokens, output_tokens

## Telescope (`/telescope`)

Dev monitoring and query inspector.
- Gated: Backpack middleware + Spatie `superadmin` role
- Shows: queries, jobs, logs, exceptions, requests
- Disabled in production unless explicitly enabled

## Backpack CRUD Notes

- Uses trait-based architecture (NOT class extension) — never add `#[\Override]` to Backpack controllers
- Factory in tests must set `is_active => true` (DB defaults don't propagate to factories)
- Admin routes are under Backpack guard (separate from `api` guard for JWT users)
