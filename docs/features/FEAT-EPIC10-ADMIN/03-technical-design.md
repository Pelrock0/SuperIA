# Technical Design: FEAT-EPIC10-ADMIN

## Overview

Backend-only feature using Backpack CRUD (Blade, no React). 4 deliverables: (1) dashboard widgets via `AdminMetricsService` + custom Blade view, (2) extended `UserCrudController` with is_active toggle, plan management, AI limit override, (3) new `AiUsageCrudController` for read-only consumption browsing, (4) Telescope access gated to superadmin. 1 migration (2 columns on `users`), 1 seeder (superadmin role), 1 middleware modification (is_active check on login).

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes |
|-------|-----------------|-------------|
| Services | Aggregate metrics queries | `AdminMetricsService` (NEW) |
| Controllers (Backpack) | CRUD operations + dashboard | `AdminController` (modified), `UserCrudController` (modified), `AiUsageCrudController` (NEW) |
| Middleware | Login block for deactivated users | `AuthService::login` (modified) |
| Infrastructure | Role seeder, Telescope gate, migration | Seeder, `TelescopeServiceProvider`, migration |

### Data Flow

#### Dashboard (GET /admin/dashboard)
```
1. AdminController::dashboard
2. Resolves AdminMetricsService
3. Service runs 5 aggregate queries:
   a. Users: COUNT(*) total, COUNT(*) WHERE last purchase >= 7 days ago
   b. Lists: COUNT(*) WHERE created_at >= today, COUNT(*) total
   c. AI: COUNT(*) ai_usage_log WHERE date=today, COUNT(*) WHERE date this month, SUM(estimated_cost_usd) this month
   d. Waitlist: COUNT(*) WHERE status='pending'
4. Returns metrics array to Blade view
5. Dashboard renders widgets with the data
```

#### User CRUD operations
```
- List: existing columns + computed columns (lists_count, ai_usage_count)
- Update: is_active toggle, plan dropdown, ai_daily_limit_override number field
- is_active check: AuthService::login checks $user->is_active before auth
```

#### AI Usage CRUD
```
- Read-only list of ai_usage_log with filters
- Columns: user.email (via relationship), operation, status, date, estimated_cost_usd
- Filters: date range, operation enum, user search
```

### Transaction Boundaries
None needed. All operations are single-row reads or updates.

## Data Model

### Modified Tables

| Table | Column | Type | Default | Purpose |
|-------|--------|------|---------|---------|
| `users` | `is_active` | BOOLEAN | true | Account activation flag |
| `users` | `ai_daily_limit_override` | INT | NULL | Per-user AI daily limit (null = global default) |

### Migrations
1. `2026_04_14_100003_add_admin_columns_to_users_table.php` — add `is_active` + `ai_daily_limit_override`

### AiUsageTracker Modification

```php
public function canUse(User $user, AiOperation $operation): bool
{
    $plan = $this->planFor($user);
    $quota = $user->ai_daily_limit_override ?? $plan->dailySuggestionQuota();
    // ... rest unchanged
}
```

### AuthService Modification

```php
public function login(string $email, string $password, string $ipAddress, ...): array
{
    // ... existing lockout check ...
    $user = User::where('email', $email)->first();

    if (!$user || !Hash::check($password, $user->password)) { ... }

    if (!$user->is_active) {
        return ['success' => false, 'error' => 'ACCOUNT_DEACTIVATED', 'message' => 'Tu cuenta ha sido desactivada.'];
    }
    // ... rest unchanged
}
```

### Telescope Gate

```php
// app/Providers/TelescopeServiceProvider.php
protected function gate(): void
{
    Gate::define('viewTelescope', function ($user) {
        return $user->hasRole('superadmin');
    });
}
```

### Backpack Dashboard View

Override `resources/views/vendor/backpack/theme-tabler/dashboard.blade.php` with widgets:

```blade
@extends(backpack_view('blank'))
@section('content')
<div class="row">
    <!-- Widget cards with metrics from AdminMetricsService -->
</div>
@endsection
```

## Performance
- Dashboard: 5 COUNT/SUM queries on indexed tables. <50ms total.
- User CRUD: paginated, standard Backpack.
- AI Usage CRUD: paginated, filtered by date index.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| **Backpack for all admin** | Consistent, fast, auth built-in | Not as pretty as custom React | **Selected** |
| **Telescope for logs** | Already installed, full-featured | Separate UI from Backpack | **Selected** — link from dashboard |
| **is_active flag (not soft-delete)** | Reversible deactivation | Extra column | **Selected** per user decision |

## Implementation Notes

### S4 execution order
1. Migration (is_active + ai_daily_limit_override on users)
2. Update User model (fillable, casts, factory default)
3. Superadmin role seeder
4. AdminMetricsService
5. Dashboard Blade view with widgets
6. Extend UserCrudController (columns, filters, actions)
7. AiUsageCrudController (new, read-only)
8. Modify AuthService::login (is_active check)
9. Modify AiUsageTracker::canUse (per-user override)
10. Telescope gate
11. Tests
12. Run full suite

### Frontend work identified
NO — Backpack only. S4-BACKEND. S5-UX should be skipped (admin-only, no user-facing changes).

## Transition
- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation (S4-BACKEND)
