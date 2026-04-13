# Scope Analysis: FEAT-EPIC10-ADMIN

## Feature Request

Implement HU-1001 (Panel de administración general), HU-1002 (Gestionar usuarios), HU-1003 (Monitorizar consumo IA) and HU-1004 (Ver logs de errores) from `docs/Superia_HU_v3.md` § Épica 10.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 18–24 hours |
| Confidence | Medium |

## Justification

**HIGH** because 4 HUs touching admin panel, role-based access, user management, AI monitoring, and error logs. However, significant infra already exists:

- **Backpack CRUD** is installed and active (`AdminController`, `UserCrudController`, `WaitlistEntryCrudController`, `CheckIfAdmin` middleware)
- **Spatie HasRoles** on User model — role management ready
- **Laravel Telescope** installed — error/log viewer already available at `/telescope`
- **`ai_usage_log` table** — AI consumption data already stored per operation/user/date
- **Stitch screen** "Panel de Administración - Superia" exists (desktop, 2560px)

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | Backpack CRUD pattern established. Queries are aggregate (COUNT, SUM) on existing tables. |
| Data | Low | No new tables. Reads from existing `users`, `shopping_lists`, `ai_usage_log`, `waitlist_entries`. |
| Security | Medium | Admin endpoints must enforce superadmin role. No user data exposed beyond what admin needs. |
| Performance | Low | Dashboard metrics are aggregate queries. Paginated tables for users/logs. |
| Operational | Low | Telescope already handles logs. Admin panel is Backpack (tested framework). |

## Affected Areas

### Backend (Backpack)
- `app/Http/Controllers/Admin/AdminController.php` — dashboard metrics widget
- `app/Http/Controllers/Admin/UserCrudController.php` — extend with AI consumption, plan management
- `app/Http/Controllers/Admin/AiUsageCrudController.php` (NEW) — AI consumption table
- `app/Services/AdminMetricsService.php` (NEW) — aggregate queries for dashboard

### Frontend
- HU-1001 mentions Stitch "AdminDashboard.jsx" but Backpack uses Blade. Decision needed: React or Blade?

## Resolved Decisions (S1, 2026-04-12)

| # | Decision | Source |
|---|----------|--------|
| 1 | **Backpack only**. No React admin. Stitch admin screen irrelevant. | User |
| 2 | **Telescope for logs**. Restrict to superadmin. Link from Backpack dashboard. No custom CRUD. | User |
| 3 | **Superadmin role**: check Spatie table, create seeder if missing. | User |
| 4 | **Per-user AI limit**: `users.ai_daily_limit_override` nullable integer. Null = global default. | User |
| 5 | **Deactivation**: new `is_active` boolean (NOT soft-delete). User can't login but data preserved. Admin reactivates. | User |
| 6 | **All 4 HUs** in one feature. Backpack CRUD is fast. | User |
| 7 | **No Stitch screen**. Backpack theme. No MCP fetch. | User |

> All resolved.

## Open Questions (historical)

1. **Backpack (Blade) vs React for admin dashboard?**
   - (a) **Backpack only** — all 4 HUs as Backpack CRUD/widgets. Consistent with existing admin. No React admin page.
   - (b) **React** — new React admin page at `/app/admin`. Requires role check in React router. Stitch screen available.
   - (c) **Hybrid** — Backpack for CRUD (HU-1002, HU-1003), React dashboard widget for HU-1001.
   - Recommend **(a)**: Backpack is the existing admin framework, handles auth/roles natively, has widgets for dashboard metrics. Building a parallel React admin is scope creep.

2. **HU-1004 error logs**: Telescope already provides this. Expose Telescope at `/telescope` for admins or build custom?
   - (a) **Telescope** — already installed, fully featured, just ensure access is restricted to superadmin.
   - (b) **Custom** — new Backpack CRUD for error logs.
   - Recommend **(a)**: Telescope is purpose-built for this. Adding a link from Backpack dashboard to Telescope.

3. **"Superadmin" role**: does it exist in the DB?
   - Need to check Spatie `roles` table. If not, create a seeder.

4. **HU-1003 "ajustar límite diario individual"**: this requires a per-user override column on `users` (e.g., `ai_daily_limit_override`). New migration.

5. **HU-1002 "desactivar/activar cuenta"**: soft-delete already exists. "Desactivar" = soft-delete? Or a separate `is_active` flag?

6. **Scope split**: 4 HUs is a lot. Split?
   - (a) All 4 in one feature.
   - (b) HU-1001+1002 first, HU-1003+1004 later.
   - Recommend **(a)** if using Backpack — the framework handles CRUD fast.

7. **Stitch screen**: exists for admin. Fetch via MCP? If Backpack, the Stitch design is irrelevant (Backpack has its own theme).

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1 PENDING
- Next Step: STEP 2 — PRD Writing
