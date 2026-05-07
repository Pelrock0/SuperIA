# Architecture — Async Jobs & Scheduled Commands

## Queue Jobs

| Job | Queue | Triggered By | Retry | Purpose |
|-----|-------|-------------|-------|---------|
| `VerificationMail` | default | `RegistrationService::register()` | Laravel default | Send email verification link after registration |
| `ResetMail` | default | `AuthService::forgotPassword()` | Laravel default | Send password reset link |
| `DeletionMail` | default | `AccountDeletionService::delete()` | Laravel default | Send account deletion confirmation |
| `WeeklySummaryMail` | default | `WeeklySummaryService::dispatchEmailFor()` | Laravel default | Send weekly AI summary email |
| `AdminWaitlistNotificationMail` | default | `WaitlistService::register()` | Laravel default | Notify admins of new waitlist signup |
| `InferItemCategoryJob` | default | `ListItemService::create()` (when category null) | Laravel default | Async AI category inference for new items |

## Scheduled Commands (Laravel Scheduler)

| Command | Schedule | Purpose |
|---------|----------|---------|
| `dispatch:weekly-summary` | Mondays 08:00 Europe/Madrid | Generate + dispatch weekly AI summary emails |
| `accounts:delete-expired` | Daily | Hard-delete users with `scheduled_hard_delete_at` in past |
| `ai:reset-daily-usage` | Daily midnight | Reset per-user AI daily quota counters |
| `cleanup:expired-collaborator-data` | Hourly | Delete stale heartbeat sessions (>5min), anonymous logs >30d |
| `cleanup:dismissed-suggestions` | Daily | Prune expired rows from `ai_dismissed_suggestions` |
| `prices:seed-catalog` | (manual, one-time) | Seed price ranges into `producto_catalogo` via Claude Haiku |

## Notes

- Queue connection: `sync` in testing (synchronous, no real queue)
- Queue connection: configured via `.env QUEUE_CONNECTION` in production
- All mail jobs dispatch primitives only (not Eloquent models) → safe queue serialization
- `InferItemCategoryJob`: dispatched only when category is null AND catalog lookup returned null
- `cleanup:expired-collaborator-data` is idempotent; safe to run multiple times
- `accounts:delete-expired` does a hard delete (permanent); scheduled 30 days after soft-delete
