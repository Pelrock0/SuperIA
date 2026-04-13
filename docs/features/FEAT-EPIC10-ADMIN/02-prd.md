# PRD: FEAT-EPIC10-ADMIN - Panel de administración SaaS (HU-1001 a HU-1004)

## Business Objective

Dar al administrador visibilidad total sobre el estado del sistema: usuarios, consumo IA, costes, y salud operacional. Sin este panel, el admin opera a ciegas (solo tiene acceso a la DB directa o a Telescope raw). Con Epic 10, todas las métricas clave están en un dashboard Backpack con widgets, y la gestión de usuarios/IA es via CRUD operaciones.

## Problem Statement

- **No hay dashboard de métricas**: el admin no ve usuarios totales, activos, costes IA, ni listas creadas sin consultar la DB.
- **Gestión de usuarios limitada**: el CRUD de usuarios existente (`UserCrudController`) es básico — no muestra consumo IA, no permite desactivar cuentas, no gestiona planes.
- **Consumo IA no visible para admin**: `ai_usage_log` tiene datos pero no hay vista para el admin.
- **Error logs dispersos**: Telescope existe pero no está integrado en el flujo admin.

## Scope

### In Scope

#### HU-1001: Dashboard de métricas
- **Backpack dashboard widgets** (Blade, no React):
  - Widget 1: Usuarios totales + activos últimos 7 días
  - Widget 2: Listas creadas hoy + total
  - Widget 3: Llamadas Claude API hoy + este mes + coste estimado
  - Widget 4: Usuarios en waitlist pendientes de invitar
  - Widget 5: Link a Telescope
- **Service**: `AdminMetricsService` con queries agregadas sobre `users`, `shopping_lists`, `ai_usage_log`, `waitlist_entries`.
- **Dashboard view**: override de `vendor/backpack/theme-tabler/resources/views/dashboard.blade.php` con los widgets.

#### HU-1002: Gestión de usuarios
- **Extend `UserCrudController`**:
  - Columnas: name, email, created_at, last_login (from producto_historial proxy), lists count, AI usage count, plan, is_active
  - Filtros: search by name/email, filter by plan, filter by is_active
  - Actions: activate/deactivate (toggle is_active), change plan (dropdown)
- **Migration**: add `is_active` BOOLEAN DEFAULT true + `ai_daily_limit_override` INT nullable to `users`
- **Auth middleware**: check `is_active` on login — if false, return error "Cuenta desactivada"

#### HU-1003: Monitorización consumo IA
- **New `AiUsageCrudController`**: CRUD over `ai_usage_log`
  - Columns: user email, operation, status, date, estimated_cost_usd
  - Filters: date range, operation type, user search
  - Read-only (no create/update/delete)
  - Summary row: total cost for filtered period
- **Per-user limit override**: admin can set `ai_daily_limit_override` via UserCrudController update form
- **`AiUsageTracker::canUse` modification**: check `$user->ai_daily_limit_override ?? config default`

#### HU-1004: Error logs via Telescope
- **Telescope access restriction**: ensure `TelescopeServiceProvider::gate` checks for superadmin role
- **Dashboard link**: widget with "Ver logs del sistema" → `/telescope`
- No custom error log CRUD

#### Cross-cutting
- **Superadmin role seeder**: create `superadmin` role in Spatie `roles` table, assign to admin user
- **Route protection**: all admin routes behind `CheckIfAdmin` middleware (already exists)
- **`is_active` check on login**: modify `AuthService::login` to check `is_active` before authenticating

### Out of Scope
- React admin dashboard (Backpack only)
- Custom error log viewer (Telescope handles this)
- Email alerts for AI 80% threshold (deferred — add as a scheduled job in a future feature)
- Real-time metrics updates (refresh on page load only)
- Premium/paid plan billing integration (plan field is a label, no payment)

## Acceptance Criteria

### AC-1: Dashboard shows user metrics
- **Given**: admin navigates to `/admin/dashboard`
- **When**: page loads
- **Then**: widgets show total users, active last 7 days counts.

### AC-2: Dashboard shows list metrics
- **Given**: dashboard
- **Then**: widgets show lists created today + total.

### AC-3: Dashboard shows AI consumption
- **Given**: dashboard
- **Then**: widgets show Claude API calls today, this month, estimated cost.

### AC-4: Dashboard shows waitlist count
- **Given**: dashboard
- **Then**: widget shows pending waitlist entries count.

### AC-5: Dashboard links to Telescope
- **Given**: dashboard
- **Then**: there is a "Ver logs del sistema" link that opens Telescope.

### AC-6: User CRUD shows extended columns
- **Given**: admin opens `/admin/user`
- **Then**: table shows name, email, created_at, lists count, AI usage, plan, is_active.

### AC-7: Admin can deactivate/reactivate a user
- **Given**: admin views a user
- **When**: admin toggles is_active
- **Then**: the user's `is_active` field is updated. Deactivated users cannot login.

### AC-8: Login blocked for deactivated users
- **Given**: user has `is_active = false`
- **When**: they attempt to login
- **Then**: HTTP 403 with "Tu cuenta ha sido desactivada."

### AC-9: Admin can change user plan
- **Given**: admin edits a user
- **When**: admin changes plan from 'free' to 'premium'
- **Then**: the `plan` field is updated on the user.

### AC-10: AI Usage CRUD shows consumption data
- **Given**: admin opens `/admin/ai-usage`
- **Then**: paginated table of `ai_usage_log` with user, operation, status, date, cost. Filterable by date range and operation.

### AC-11: Admin can set per-user AI limit override
- **Given**: admin edits a user
- **When**: admin sets `ai_daily_limit_override = 50`
- **Then**: that user gets 50 AI calls/day instead of the global default.

### AC-12: AiUsageTracker respects per-user override
- **Given**: user has `ai_daily_limit_override = 50`
- **When**: `canUse` is checked
- **Then**: limit is 50 (not the config default).

### AC-13: Telescope restricted to superadmin
- **Given**: a regular user tries to access `/telescope`
- **Then**: access denied. Only superadmin role can view.

### AC-14: Superadmin role seeded
- **Given**: fresh DB seed
- **Then**: `superadmin` role exists in Spatie roles table. First admin user has the role.

### AC-15: All admin routes require authentication + admin role
### AC-16: 100% backend test coverage on new code
### AC-17: Migration adds is_active + ai_daily_limit_override to users

## UX Decision

- **UX Designer Required**: NO
- **Backpack theme**: existing Tabler theme handles all UI.
- **S5-UX**: will be SKIPPED (no user-facing UI changes, admin-only Backpack).

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| `is_active` check breaks existing tests | Technical | Add `is_active => true` default in UserFactory. Existing tests unaffected. |
| Dashboard queries slow on large tables | Performance | All queries use indexed columns (user_id, date, status). COUNT/SUM are fast. |
| Telescope access leak | Security | Gate in TelescopeServiceProvider checks superadmin role. |

## Open Questions
None.

## Transition
- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
