# Technical Design: FEAT-EPIC5C-SUMMARY

## Overview

Diseño en tres capas concéntricas alrededor de un único service `WeeklySummaryService` que es la **fuente de verdad** para toda la lógica de elegibilidad, generación, persistencia, dispatch de email y conversión a lista. Encima del service hay (1) un **console command** orquestador disparado por el Laravel Scheduler los lunes 08:00 Europa/Madrid, y (2) cuatro **API controllers thin** + un **public unsubscribe controller** que delegan al service. Debajo del service hay (a) una **nueva tabla `weekly_summaries`** con unique constraint `(user_id, week_start_date)` que es la fuente de verdad de idempotencia, (b) **dos columnas nuevas en `users`** para el opt-in de email y el dismissed-at del banner, y (c) la extensión de `ClaudeClientInterface` con un nuevo método `generateWeeklySummary(array $context): array`.

La integración con la infra AI existente es directa: reutiliza `AiUsageTracker` (cuota compartida vía `AiOperation::Summary` que **ya existe en el enum** — descubierto durante S3, no requiere nuevo case), `BudgetCap` (global), `CircuitBreaker` (resiliencia Claude), `HistoryAnonymizer` (PII fuera del prompt), `PromptSanitizer` (defensa contra prompt injection vía nombres de productos del historial). El patrón es 1:1 con `ProductSuggestionService` y `ComplementarySuggestionService` de Epic 5A/5B.

El frontend reutiliza el patrón de Epic 5B: una page bajo `/app/resumen`, un banner dismissable en el dashboard, un toggle en settings, todo via Zustand + el cliente HTTP existente. La pantalla `resumen_semanal` ya existe en Stitch (decisión #12) y se fetchea via MCP en S4.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | Estado del resumen (enum), invariantes de elegibilidad (≥3 semanas history), invariantes de freemium (3-list cap heredada de Epic 2) | `App\Enums\WeeklySummaryStatus`, `App\Models\WeeklySummary` |
| Services | Toda la lógica de negocio: query de elegibilidad, generación con Claude, persistencia con dedup vía unique constraint, dispatch de email condicional al opt-in, conversión a lista, dismiss del banner | `App\Services\WeeklySummaryService` (NUEVO), `App\Services\ShoppingListService` (existente, reutilizado para `convertToList`) |
| Infrastructure | Cliente Claude real + fake, mailable + Blade template, signed URL para unsubscribe, scheduler entry, kill switch en config | `App\Support\Ai\ClaudeClient::generateWeeklySummary` (NUEVO), `App\Support\Ai\FakeClaudeClient::generateWeeklySummary` (NUEVO), `App\Mail\WeeklySummaryMail` (NUEVO), `routes/console.php` (entrada nueva), `config/ai.php` (sección nueva) |
| Controllers/API | Thin: validan FormRequest, delegan al service, devuelven JsonResponse. Cero lógica de negocio. | `App\Http\Controllers\WeeklySummaryController` (NUEVO, 3 acciones), `App\Http\Controllers\Settings\WeeklySummaryEmailController` (NUEVO, 1 acción), `App\Http\Controllers\Public\UnsubscribeWeeklySummaryController` (NUEVO, 1 acción) |
| Frontend | Page de resumen, banner dismissable en dashboard, toggle en settings, api client | `resources/js/pages/WeeklySummaryPage.tsx`, `resources/js/components/WeeklySummaryBanner.tsx`, `resources/js/api/weeklySummary.ts`, mod en `SettingsPage.tsx` (existente) |
| Console | Orquesta el run semanal: itera elegibles, llama service per-user con try/catch, loguea métricas | `App\Console\Commands\DispatchWeeklySummary` (NUEVO, signature `ai:dispatch-weekly-summary`) |

### Data Flow

#### Weekly cron run (Mondays 08:00 Europe/Madrid)

```
1. Laravel Scheduler triggers `ai:dispatch-weekly-summary` (withoutOverlapping(60))
2. Command checks config('ai.weekly_summary.enabled')
   - false → log "disabled by config" and exit 0
   - true → continue
3. Command resolves WeeklySummaryService from container
4. Command calls $service->eligibleUsers() → Collection<User>
   Query (single SQL):
     SELECT users.* FROM users
     WHERE deleted_at IS NULL
       AND email_verified_at IS NOT NULL
       AND last_login_at >= NOW() - INTERVAL 60 DAY
       AND id IN (
         SELECT user_id FROM producto_historial
         GROUP BY user_id
         HAVING COUNT(DISTINCT YEARWEEK(fecha_compra, 1)) >= 3
       )
5. Command iterates users with chunkById(100) for memory safety
   For each user (try/catch isolation):
     a. summary = $service->generateForUser(user)
        i.   Check AiUsageTracker::canUse(user, AiOperation::Summary)
             - false → record AiUsageStatus::UserCapped → return WeeklySummary{status: failed, error: "user quota exceeded"}
        ii.  Check BudgetCap::canSpend()
             - false → record AiUsageStatus::BudgetCapped → return WeeklySummary{status: failed, error: "global budget exceeded"}
        iii. Check CircuitBreaker::allow()
             - false → record AiUsageStatus::CircuitOpen → return WeeklySummary{status: failed, error: "circuit open"}
        iv.  Build context:
             - history (last 4 weeks): producto_historial → HistoryAnonymizer → list of product names
             - active list items: ShoppingList::active for user → list of names (or [])
             - month: Carbon::now(Europe/Madrid)->month (int 1-12)
        v.   Apply PromptSanitizer to all product names
        vi.  Try INSERT WeeklySummary{user_id, week_start, status: pending, payload_json: null}
             - if unique constraint fires → already dispatched this week, return existing row
        vii. Call $claude->generateWeeklySummary($context)
             - throws ClaudeException → CircuitBreaker::recordFailure → mark row failed → return
             - succeeds → CircuitBreaker::recordSuccess
        viii. Update WeeklySummary row: payload_json = result.products, claude_cost_usd = result.estimated_cost_usd, status = pending (still — "pending" means generated but not yet dispatched)
        ix.  Record AiUsageTracker::record(user, Summary, Success, cost)
        x.   Return WeeklySummary
     b. $service->dispatchEmailFor(summary)
        i.   If $summary->status === Failed → noop, return
        ii.  Re-fetch user.weekly_summary_email_opted_in (race window minimization)
             - false → mark row dispatched (in-app only), return
             - true → Mail::to(user)->send(new WeeklySummaryMail(summary))
        iii. Mark row status = Dispatched, dispatched_at = now()
   Per-user errors are caught here, logged with user_id, and the loop continues.
6. Command logs final metrics: processed=N, succeeded=X, email_sent=Y, failed=Z, total_cost_usd=$
7. Command returns SUCCESS (exit 0)
```

#### Frontend banner / page flow

```
1. User logs in, dashboard mounts
2. Dashboard calls GET /api/weekly-summary/latest
3. Backend WeeklySummaryController::latest:
   a. Query WeeklySummary::where(user_id, auth->id())->whereDate(week_start_date, currentWeekStart())->first()
   b. If null → return 404 {error: NO_SUMMARY_THIS_WEEK}
   c. If user.weekly_summary_in_app_dismissed_at >= week_start → return 404 {error: DISMISSED}
   d. Else → return 200 {data: {summary: WeeklySummaryResource}}
4. If 200, dashboard renders <WeeklySummaryBanner>
5. User clicks banner → navigate to /app/resumen
6. WeeklySummaryPage fetches the same endpoint, renders payload as a list of products
7. User clicks "Convertir en lista":
   a. POST /api/weekly-summary/{id}/convert-to-list
   b. Backend WeeklySummaryController::convertToList → service.convertToList(user, summary)
      - Tries ShoppingListService::create($user, ['name' => "Resumen semanal del DD/MM/YYYY", 'category' => null, 'emoji' => '📅', 'items' => $payload->products])
      - If ShoppingListService throws OverflowException → controller catches, returns 403 {error: FREEMIUM_LIMIT}
      - Otherwise returns 201 {data: ShoppingListResource}
   c. Frontend redirects to the new list view
8. User clicks X on banner:
   a. POST /api/weekly-summary/dismiss
   b. Backend WeeklySummaryController::dismiss → service.markDismissed(user) → users.weekly_summary_in_app_dismissed_at = now()
   c. Banner fades, does not re-appear until next Monday's run
```

#### Email + unsubscribe flow

```
1. Cron run sends WeeklySummaryMail to opted-in user
2. Email body (Blade) includes:
   - Greeting with user name
   - List of suggested products with quantities/units if available
   - CTA: "Convertir en lista" → deep link to /app/resumen?id={summary.id}
   - CTA: "Ver en la app" → /app/resumen
   - Footer: "Si ya no quieres recibir este resumen, [cancela tu suscripción]({signedUnsubscribeUrl})"
3. signedUnsubscribeUrl = URL::signedRoute('weekly-summary.unsubscribe', ['user' => $user->id], now()->addDays(30))
4. User clicks unsubscribe link
5. Laravel `signed` middleware validates signature + TTL
   - Invalid signature → 403 (Symfony default)
   - Expired → 403
6. UnsubscribeWeeklySummaryController::handle:
   a. Resolve $user from route binding
   b. Set $user->weekly_summary_email_opted_in = false → save()
   c. Return view('emails.weekly-summary.unsubscribed', ['user' => $user])
7. Idempotent: replay flips an already-false bool to false (no-op).
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| `generateForUser` | Wrap the INSERT INTO weekly_summaries + AiUsageTracker::record in a single `DB::transaction` | If the insert succeeds but the record fails, we'd have a row without usage tracking. Atomic. |
| `dispatchEmailFor` | No transaction. Mail send is a side effect outside DB; we use the `dispatched_at` UPDATE as the commit point. If mail fails before the UPDATE, the row stays in pending and the next run can retry. | Sending an email is not transactional; combining it with a DB transaction would not provide any guarantee. |
| `convertToList` | Reuses `ShoppingListService::create`'s existing transaction (already wraps the multi-row insert of list + items). No new transaction in this service method. | Single existing boundary suffices. |
| `markDismissed` | No explicit transaction (single-row UPDATE) | Atomic by definition. |
| Per-user error isolation | Each per-user iteration in the command is its OWN transaction scope. A failure in user N does NOT roll back user N-1's success. | This is the entire point of failure isolation (AC-4). |

**Rollback scenario**: if the cron crashes mid-run (e.g. server restart), users already processed have their `weekly_summaries` row committed. Users not yet processed have nothing. On the next manual rerun, the unique constraint protects against duplicates and only the unprocessed users get new rows.

## Data Model

### New Tables

| Name | Purpose | Key Fields |
|------|---------|------------|
| `weekly_summaries` | Persistent record of every weekly summary generation, source of truth for idempotency and email dispatch state | `id` BIGINT PK, `user_id` BIGINT FK→users.id ON DELETE CASCADE, `week_start_date` DATE, `status` VARCHAR(20) (enum: pending/dispatched/failed), `payload_json` LONGTEXT (JSON: array of products), `claude_cost_usd` DECIMAL(10,4) NULL, `dispatched_at` TIMESTAMP NULL, `error_message` TEXT NULL, `created_at` TIMESTAMP, `updated_at` TIMESTAMP |

**Indexes on `weekly_summaries`**:
- PK on `id` (default)
- **UNIQUE** on `(user_id, week_start_date)` — fuente de verdad de idempotencia (AC-3)
- INDEX on `(week_start_date)` — soporta el query "todos los resúmenes de esta semana" para futuro dashboard admin

### Modified Tables

| Name | Change | Field | Default | Purpose |
|------|--------|-------|---------|---------|
| `users` | Add column | `weekly_summary_email_opted_in` BOOLEAN NOT NULL DEFAULT 0 | false | Decisión #3: email es opt-in explícito (RGPD) |
| `users` | Add column | `weekly_summary_in_app_dismissed_at` TIMESTAMP NULL | null | Banner dismiss state, scoped a la semana actual |

Both columns are nullable / have defaults → no backfill required → `ALTER TABLE` con `ALGORITHM=INSTANT` en MySQL 8.

### Migrations

1. **`2026_04_12_000001_create_weekly_summaries_table.php`**
   - `up()`: Schema::create('weekly_summaries', ...) con todas las columnas listadas arriba + indexes
   - `down()`: Schema::dropIfExists('weekly_summaries')
2. **`2026_04_12_000002_add_weekly_summary_columns_to_users_table.php`**
   - `up()`: Schema::table('users', ...) con `weekly_summary_email_opted_in` + `weekly_summary_in_app_dismissed_at`
   - `down()`: Schema::table('users', ...) con dropColumn de las dos columnas

Both migrations are reversible per project rules.

### API Changes

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/weekly-summary/latest` | GET | `auth:api` | Return current-week summary for authenticated user, or 404 |
| `/api/weekly-summary/{id}/convert-to-list` | POST | `auth:api` + ownership check | Create a new ShoppingList from the summary's payload (subject to freemium limit) |
| `/api/weekly-summary/dismiss` | POST | `auth:api` | Mark current week's banner as dismissed for the authenticated user |
| `/api/settings/weekly-summary-email` | POST | `auth:api` | Toggle the user's email opt-in flag (`{enabled: bool}`) |
| `/unsubscribe/weekly-summary/{user}` | GET | `signed` middleware | Public route, signed URL, sets opt-in to false, renders confirmation view |

Response shapes follow the existing API pattern (`{data: {...}}` for success, `{error: {code, message}}` for failure).

### Config Changes

`config/ai.php` — add new section:

```php
'weekly_summary' => [
    'enabled' => env('AI_WEEKLY_SUMMARY_ENABLED', true),
    'model' => env('AI_WEEKLY_SUMMARY_MODEL', 'claude-haiku-4-5-20251001'),
    'max_tokens' => 1500,
    'history_weeks' => 4,
    'min_history_weeks' => 3,
    'inactivity_cutoff_days' => 60,
    'unsubscribe_token_ttl_days' => 30,
    'dispatch_chunk_size' => 100,
],
```

`.env.example` — add new entries:

```
AI_WEEKLY_SUMMARY_ENABLED=true
AI_WEEKLY_SUMMARY_MODEL=claude-haiku-4-5-20251001
```

### Routes

`routes/api.php` — add inside the `auth:api` group:

```php
Route::prefix('weekly-summary')->group(function () {
    Route::get('latest', [WeeklySummaryController::class, 'latest']);
    Route::post('dismiss', [WeeklySummaryController::class, 'dismiss']);
    Route::post('{summary}/convert-to-list', [WeeklySummaryController::class, 'convertToList']);
});

Route::post('settings/weekly-summary-email', [WeeklySummaryEmailController::class, 'update']);
```

`routes/web.php` — add the public signed route:

```php
Route::get('unsubscribe/weekly-summary/{user}', [UnsubscribeWeeklySummaryController::class, 'handle'])
    ->middleware('signed')
    ->name('weekly-summary.unsubscribe');
```

`routes/console.php` — add the scheduler entry:

```php
Schedule::command('ai:dispatch-weekly-summary')
    ->mondays()
    ->at('08:00')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping(60)
    ->onOneServer();
```

### ClaudeClientInterface extension

```php
/**
 * Generate a weekly summary of products the user is likely to need this week.
 *
 * @param  array{
 *     history_weeks: array<int, array<int, string>>,  // 4 arrays of sanitized product names per week
 *     active_list_items: array<int, string>,           // sanitized names of items in the user's active list (may be empty)
 *     month: int,                                      // 1-12
 * }  $context
 * @return array{
 *     products: array<int, array{nombre: string, cantidad_tipica: ?float, unidad_tipica: ?string, categoria: ?string, reason: string}>,
 *     estimated_cost_usd: float,
 * }
 *
 * @throws \App\Support\Ai\Exceptions\ClaudeException on any failure
 */
public function generateWeeklySummary(array $context): array;
```

Both `ClaudeClient` (real) and `FakeClaudeClient` implement this. The `FakeClaudeClient` accepts canned responses via a new public property `array $cannedWeeklySummary = []` and tracks calls via `array $weeklySummaryCalls = []` for assertion in tests.

## Performance

### Query Optimization

- **Eligibility query** (single SQL) avoids the N+1 trap of "load all users, filter in PHP". The `WHERE id IN (...)` subquery uses an aggregate over `producto_historial`. The existing index `(user_id, lista_id)` on `producto_historial` (added in Epic 5B) is sufficient; the `GROUP BY user_id HAVING COUNT(DISTINCT YEARWEEK(...)) >= 3` clause uses the user_id prefix.
- **chunkById(100)** in the command iteration prevents loading all eligible users into memory at once.
- **Banner endpoint** (`GET /api/weekly-summary/latest`) does a single indexed lookup `WHERE user_id = ? AND week_start_date = ?` (uses the unique constraint as an index).
- **Dispatch loop** is sequential per user (no parallel HTTP). Acceptable for V1 — at 1000 users × 1s avg per Claude call = ~17 minutes total. Within the `withoutOverlapping(60)` window.

### Caching Strategy

| Cache | Key | TTL | Invalidation |
|-------|-----|-----|--------------|
| None for V1 | — | — | — |

The summary itself IS cached (in `weekly_summaries` table) — that's the dedup mechanism. No additional Redis/file cache needed.

### Async Processing

V1 is synchronous within the cron job. The trade-off: simpler code, easier observability, single transaction scope per user. If at scale (>1000 users) the run takes too long, V2 can refactor to dispatch a `GenerateWeeklySummaryJob` per user via `Bus::batch()`. Out of scope for V1 (per PRD assumption).

## Security

### Authentication / Authorization

- All `/api/weekly-summary/*` endpoints sit behind `auth:api` (JWT, existing pattern).
- `/api/weekly-summary/{summary}/convert-to-list` uses route model binding for `WeeklySummary`, then verifies `$summary->user_id === auth()->id()` in the controller (returns 404 if mismatch — not 403, to avoid existence leak).
- `/unsubscribe/weekly-summary/{user}` uses `signed` middleware (Laravel built-in) which verifies HMAC signature against `APP_KEY` and TTL. No additional auth required (the signature IS the auth).

### Input Validation

- `POST /api/weekly-summary/dismiss`: empty body, no validation needed
- `POST /api/weekly-summary/{summary}/convert-to-list`: empty body, route binding handles ID validation
- `POST /api/settings/weekly-summary-email`: FormRequest with `enabled: required|boolean`

### Data Protection

- Email body contains real product names (PII for the user). The user is the only recipient. The mail provider sees the rendered HTML — accepted risk per PRD assumption (SMTP config is the user's responsibility).
- `payload_json` in `weekly_summaries` table contains the same product names. `users.weekly_summary_in_app_dismissed_at` is a timestamp, not PII.
- `HistoryAnonymizer` strips PII from the prompt CONTEXT sent to Claude. `PromptSanitizer` neutralizes prompt-injection attempts in product names.
- Unsubscribe link uses Laravel's `URL::signedRoute` which is HMAC-SHA256 over the route + parameters + expiration, signed with `APP_KEY`. Tampering breaks the signature.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| **Per-user iteration in command** | Simple, observable, single transaction scope per user, easy error isolation | Sequential — slow for >1000 users | **Selected** for V1. Refactor to job batching if profiling shows it's needed. |
| Job batching via `Bus::batch()` | Parallelizable, retryable per job, dashboard support via Horizon | More moving parts, queue worker required, harder to log aggregate metrics | Rejected for V1 — premature complexity. |
| **Unique constraint as idempotency source** | DB-enforced, single source of truth, race-safe | Insert failure must be caught and translated to "already exists" | **Selected**. The catch-and-translate is 5 lines. |
| Application-level "check then insert" | Slightly clearer code | Race condition: two cron runs could both pass the check and both insert | Rejected — race-unsafe. |
| **Email opt-in default false (decision #3)** | RGPD-safe, user explicit consent | Lower email engagement until users opt in | **Selected** per user decision. |
| Email opt-in default true (sent transactional) | Higher engagement | Legally questionable for non-transactional outbound | Rejected per user decision. |
| **Stateless signed unsubscribe URL (decision #10)** | Zero DB state, scales infinitely, regenerated per email | Replay possible within 30-day TTL | **Selected** per user decision. Replay risk accepted because the operation is idempotent (set to false). |
| Single-use DB-backed token | Replay-proof | Requires a tokens table, lookup per click, cleanup job | Rejected per user decision. |
| **Separate `weekly_summaries` table** | Clear ownership, easy to query history, idempotency via unique constraint | One more table | **Selected**. The data model needs persistence anyway. |
| Add columns to `users` table only | Fewer tables | Cannot store historical summaries, only "the latest"; idempotency would need a separate weekly counter column | Rejected — loses history and clutters `users`. |
| **Reuse `AiUsageTracker` shared quota (decision #6)** | Simpler, single budget surface to monitor | Heavy autocomplete users may miss summary | **Selected** per user decision. |
| Separate quota pool for summary | Isolated cost projection | Two budget surfaces, more config | Rejected per user decision. |
| **Claude infers seasonality from `month` int (decision #9)** | Zero maintenance, no hardcoded list | Prompt drift risk, possible hemisphere confusion | **Selected** per user decision; mitigated by explicit "España" in prompt. |
| Hardcoded month → seasonal products map | Deterministic, auditable | 12 buckets to maintain, locale-specific | Rejected per user decision. |
| **Banner dismiss via per-week check on `users.weekly_summary_in_app_dismissed_at`** | Single column, no extra table | Comparing timestamp to week_start every request | **Selected**. The comparison is one expression in the controller; cost is negligible. |
| Separate `weekly_summary_dismissals` table | Cleanest separation | One row per user per week, grows linearly | Rejected — overkill for a boolean-ish state. |
| **`mondays()->at('08:00')->timezone('Europe/Madrid')`** | Idiomatic Laravel | Hardcodes timezone | **Selected**. The timezone is the user base's timezone (Spain). |
| `weeklyOn(1, '08:00')` | Same effect | Same | Equivalent — choice is stylistic. |
| **`withoutOverlapping(60)` + `onOneServer()`** | Prevents double-runs in multi-server deploys | Requires cache driver (Redis recommended) | **Selected**. Belt-and-suspenders for the multi-server case. |
| No overlap protection | Simpler config | Risk of double dispatch | Rejected — defense in depth is cheap. |
| **`convertToList` uses `ShoppingListService::create`** | Reuses freemium check + activity log + transaction | Coupling to existing service | **Selected**. The existing service is the canonical creator; bypassing it would break invariants. |
| Inline list creation in WeeklySummaryService | Decoupled | Re-implements freemium logic, drift risk | Rejected — DRY violation with safety implications. |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Race condition: user opts out at 07:59 Monday, cron starts 08:00 with a stale snapshot | Low (one extra email) | Low | The check of `weekly_summary_email_opted_in` is re-read inside `dispatchEmailFor`, NOT cached from `eligibleUsers()`. Window reduces to milliseconds. |
| Claude returns malformed JSON for the products list | Medium (one user fails) | Medium | `ClaudeClient::generateWeeklySummary` validates the response shape before returning. Throws `ClaudeException` on parse failure → caught by per-user try/catch → row marked failed → loop continues. |
| Migration on `users` table locks production | Low (instant DDL) | Low | Both new columns are nullable / have defaults → MySQL 8 `ALGORITHM=INSTANT` for the ALTER. Document in migration comment. |
| Email Blade template renders broken HTML in some clients | Low (visual only) | Medium | Use `markdown` mailable or simple inline HTML. Test render in Mailtrap (dev) before deploy. Spanish-only V1, no i18n complexity. |
| Cron run takes >60 min with 10k+ users | Medium (next week's run skipped) | Low (V1 expected <1k users) | `withoutOverlapping(60)` prevents the next run from starting if the current one is still going. The skipped users get processed next Monday. AC documented. |
| Unsubscribe link works but user cannot easily re-opt-in | Low (UX) | Low | Settings toggle is accessible from the app at any time. The unsubscribe page renders a small "Re-activar" link pointing to the settings page (logged-in flow). |
| Stitch MCP server unavailable during S4 frontend work | Medium (blocks frontend) | Low | If the MCP fetch fails in S4, the developer can fall back to pulling the screen manually from the Stitch web UI and pasting. Document in S4 implementation notes. |
| `AI_WEEKLY_SUMMARY_ENABLED` change does not take effect because of `config:cache` | Medium (kill switch fails) | Medium | Document in `04-implementation-notes.md` and README: "After changing the env, run `php artisan config:clear` (or redeploy) for the kill switch to take effect." |
| Convert-to-list creates a list named "Resumen semanal del DD/MM" that collides with an existing list with the same name | Low (UX) | Very Low | List names are not unique in the schema. Two lists with the same name is acceptable. Append the year if needed for visual disambiguation (`DD/MM/YYYY`). |
| Unsubscribe link signature uses the same `APP_KEY` as session cookies — key rotation invalidates outstanding links | Low (one-shot user pain) | Very Low | Documented in S4 implementation notes. Coordinate any APP_KEY rotation with a notice to users that pending unsubscribe links may need re-issue. |
| The 4-week history window straddles a month boundary (e.g. cron runs 2026-05-04, history covers 2026-04-06 to 2026-05-04) — `month` field passed to Claude says "May" but most history is "April" | Low (slight prompt drift) | Always | Accepted. The `month` field represents "the month the summary is FOR", not the history window. Claude understands the distinction from the prompt structure. |
| `dispatch_chunk_size` of 100 is too small/large | Low (performance only) | Low | Configurable via `config('ai.weekly_summary.dispatch_chunk_size')`. Default 100 is a safe starting point; tune after first prod run. |

## Open Questions

None. All 15 questions raised in S1 were resolved by the user before S2; the PRD codified those decisions; this design implements them. No new TBDs surfaced during the design.

## Implementation Notes

### Suggested execution order for S4

1. **Migrations first**: create both migrations, run `php artisan migrate`. Verify the unique constraint exists by trying to insert a duplicate manually in tinker.
2. **Enums and DTOs**: `WeeklySummaryStatus` enum. No new `AiOperation` case needed (already exists as `Summary`).
3. **Model**: `WeeklySummary` with relations and casts. Add `weeklySummaries(): HasMany` to `User`.
4. **Service skeleton**: `WeeklySummaryService` with all method signatures, no body. Wire into the container if needed (autoresolved by default).
5. **Claude client extension**: add `generateWeeklySummary` to interface, implement in `FakeClaudeClient` first (canned response), then in `ClaudeClient` (real API call). Write the test for `FakeClaudeClient` first.
6. **Service body**: implement `eligibleUsers`, `generateForUser`, `dispatchEmailFor`, `markDismissed`, `convertToList` one at a time, with unit tests after each.
7. **Mailable + Blade template**: create the template, render it in tinker (`Mail::send(new WeeklySummaryMail($summary))`) to verify Mailtrap receives it.
8. **Console command**: implement the command, test via `php artisan ai:dispatch-weekly-summary --dry-run` if a dry-run flag is added (recommend yes), then real run against a single test user.
9. **Scheduler entry**: add to `routes/console.php`, verify with `php artisan schedule:list`.
10. **API controllers + routes + FormRequests**: thin, delegate to service. Tests for each endpoint.
11. **Public unsubscribe controller**: implement, verify the signed URL flow end-to-end.
12. **Frontend**: fetch Stitch screen via `mcp__stitch__get_screen` for `resumen_semanal`, generate `WeeklySummaryPage`, then `WeeklySummaryBanner`, then settings toggle, then `weeklySummary.ts` api client.
13. **Frontend tests** (vitest) for the 3 components.
14. **Final regression**: run `php artisan test` and `npm test`. Must be 100% green.

### Critical invariants to assert in tests

- The unique constraint blocks duplicate `(user_id, week_start_date)` inserts.
- The eligibility query EXCLUDES soft-deleted, unverified, inactive (>60d), and <3-week-history users.
- `dispatchEmailFor` re-reads `weekly_summary_email_opted_in` (does not trust the snapshot).
- `convertToList` propagates `OverflowException` from `ShoppingListService::create` and the controller translates to HTTP 403.
- The unsubscribe signed URL is rejected when tampered or expired.
- The kill switch (`AI_WEEKLY_SUMMARY_ENABLED=false`) makes the command exit 0 with zero side effects.
- The command's exit code is 0 even when individual users fail.

### Frontend work identified

YES — three new components (`WeeklySummaryBanner`, `WeeklySummaryPage`, settings toggle) + an api client + 3+ vitest test files. The S4 implementation type should be `S4-BOTH` (backend AND frontend), and `has_ui_changes` must be set to YES so that S5-UX runs.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation (S4-BOTH)
- Required Artifacts: `01-scope.md`, `02-prd.md`, `03-technical-design.md`
