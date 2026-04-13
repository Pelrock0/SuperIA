# Implementation Notes - FEAT-EPIC5C-SUMMARY

## Summary (Backend phase complete)

Backend de HU-505 entregado siguiendo el technical design de S3. Añadido `WeeklySummaryService` con 5 métodos (eligibleUsers, generateForUser, dispatchEmailFor, markDismissed, convertToList) que reutiliza toda la infra AI existente (AiUsageTracker, BudgetCap, CircuitBreaker, PromptSanitizer, HistoryAnonymizer) siguiendo el patrón de `ComplementarySuggestionService` de Epic 5B. Claude client extendido con `generateWeeklySummary`. Mailable + Blade template en español. Console command + scheduler entry (lunes 08:00 Europa/Madrid, withoutOverlapping + onOneServer). 5 endpoints (3 authenticated + 1 settings + 1 public signed unsubscribe). 44 tests nuevos, 523/523 backend passing, composer security exit 0.

**Fase frontend pendiente**: WeeklySummaryPage, WeeklySummaryBanner, settings toggle, api client, vitest tests. El Stitch screen `resumen_semanal` se fetcheará via MCP en la fase frontend (AC-19).

## Scope Changes

| Date | Type | Description | Impact |
|------|------|-------------|--------|
| 2026-04-12 | Clarification | **Decision #4 revised**: the original S1 decision assumed `users.last_login_at` existed. It does not. The schema has `login_attempts` (for rate-limiting failed logins, keyed by email) but no per-user last-login timestamp. User approved option D during S4: use `MAX(producto_historial.fecha_compra) >= now - 60 days` as an **activity-based** proxy for the "active user" filter. Better semantic match for a history-driven feature. No changes to auth code. Documented in `01-scope.md` Resolved Decisions table. | No scope creep. The eligibility query changed from login-based to activity-based. Tests cover both the happy case and the <60-day inactivity case. |

## Files Changed

### Migrations (new)

| File | Description |
|------|-------------|
| `database/migrations/2026_04_14_100000_create_weekly_summaries_table.php` | `weekly_summaries` table with UNIQUE(`user_id`, `week_start_date`) + secondary index on `week_start_date`. Source of truth for idempotency. |
| `database/migrations/2026_04_14_100001_add_weekly_summary_columns_to_users_table.php` | Adds `weekly_summary_email_opted_in` BOOLEAN default false + `weekly_summary_in_app_dismissed_at` TIMESTAMP NULL. Both nullable / default → no backfill. |

### Domain / Model (new)

| File | Description |
|------|-------------|
| `app/Enums/WeeklySummaryStatus.php` | enum: Pending / Dispatched / Failed |
| `app/Models/WeeklySummary.php` | Eloquent model with BelongsTo User, casts (status enum, payload_json array, dispatched_at datetime, week_start_date date). HasFactory trait. |
| `app/Models/User.php` | MODIFIED: added `weeklySummaries(): HasMany` relationship. |

### Service layer (new)

| File | Description |
|------|-------------|
| `app/Services/WeeklySummaryService.php` | 5 public methods: `eligibleUsers`, `generateForUser`, `dispatchEmailFor`, `markDismissed`, `convertToList`, plus helper `currentWeekStart`. Depends on existing AI guardrails (BudgetCap, AiUsageTracker, CircuitBreaker, PromptSanitizer, ClaudeClientInterface) + ShoppingListService for list creation (preserves existing freemium invariants). |

### AI / Infrastructure (modified + extended)

| File | Description |
|------|-------------|
| `app/Support/Ai/ClaudeClientInterface.php` | MODIFIED: added `generateWeeklySummary(array $context): array` method signature. |
| `app/Support/Ai/ClaudeClient.php` | MODIFIED: added new system prompt constant `WEEKLY_SUMMARY_SYSTEM_PROMPT` (Spanish), added `generateWeeklySummary` implementation using the same `Http::post(...)` pattern as `suggestComplements`, added `buildWeeklySummaryMessage` + `parseWeeklySummaryEntries` private helpers. |
| `app/Support/Ai/FakeClaudeClient.php` | MODIFIED: added `$cannedWeeklySummary` public property, `$weeklySummaryCalls` tracking array, and `generateWeeklySummary` override mirroring the fake pattern of existing methods (supports `$shouldThrow`). |
| `config/ai.php` | MODIFIED: added `weekly_summary` section with 8 keys (enabled, model, max_tokens, history_weeks, min_history_weeks, inactivity_cutoff_days, unsubscribe_token_ttl_days, dispatch_chunk_size). All env-backed. |
| `.env.example` | MODIFIED: added 2 entries — `AI_WEEKLY_SUMMARY_ENABLED=true`, `AI_WEEKLY_SUMMARY_MODEL=claude-haiku-4-5-20251001`. |

### Email (new)

| File | Description |
|------|-------------|
| `app/Mail/WeeklySummaryMail.php` | Mailable implementing ShouldQueue. Envelope subject "Superia — Tu resumen semanal". Content view `emails.weekly-summary` with 5 variables (userName, products, weekStart, unsubscribeUrl via `URL::temporarySignedRoute` with 30-day TTL, appUrl). |
| `resources/views/emails/weekly-summary.blade.php` | Simple HTML Spanish template: greeting, product list with quantity/unit/reason, CTA to app, unsubscribe footer. |
| `resources/views/emails/weekly-summary-unsubscribed.blade.php` | Confirmation page rendered after successful unsubscribe. |

### Console command (new)

| File | Description |
|------|-------------|
| `app/Console/Commands/DispatchWeeklySummary.php` | Signature `ai:dispatch-weekly-summary`. Checks kill switch, iterates eligible users (per-user try/catch), logs metrics (processed, succeeded, email_sent, failed, total_cost_usd), always returns exit 0. |
| `routes/console.php` | MODIFIED: added `Schedule::command('ai:dispatch-weekly-summary')->mondays()->at('08:00')->timezone('Europe/Madrid')->withoutOverlapping(60)->onOneServer()`. |

### HTTP layer (new + modified)

| File | Description |
|------|-------------|
| `app/Http/Controllers/WeeklySummaryController.php` | 3 actions: `latest` (GET, 200 or 404 NO_SUMMARY_THIS_WEEK / DISMISSED), `dismiss` (POST, marks banner dismissed), `convertToList` (POST, delegates to service, catches OverflowException → 403 FREEMIUM_LIMIT). Ownership check returns 404 (no existence leak). |
| `app/Http/Controllers/Settings/WeeklySummaryEmailController.php` | 1 action: `update` toggles `weekly_summary_email_opted_in` via FormRequest-validated boolean. |
| `app/Http/Controllers/Public/UnsubscribeWeeklySummaryController.php` | Public signed-middleware action: flips opt-in to false, renders confirmation view. Idempotent (second visit still works). |
| `app/Http/Requests/Settings/UpdateWeeklySummaryEmailRequest.php` | FormRequest: `enabled` required boolean. |
| `routes/api.php` | MODIFIED: added 4 routes under auth:api group (3 weekly-summary + 1 settings). |
| `routes/web.php` | MODIFIED: added signed route `/unsubscribe/weekly-summary/{user}` BEFORE the SPA catch-all; updated catch-all regex to exclude `unsubscribe`. |

### Test support (new)

| File | Description |
|------|-------------|
| `database/factories/WeeklySummaryFactory.php` | Factory with default pending payload + states `dispatched()` and `failed($reason)`. |
| `tests/Support/SeedsProductHistory.php` | Trait: `seedWeeklyHistory(User $user, int $weeks = 3, array $productNames = [...])` inserts rows into `producto_historial` spanning N distinct ISO weeks (mid-week each) for eligibility tests. |

### Tests added

| File | Type | Tests | What it covers |
|------|------|-------|----------------|
| `tests/Unit/Services/WeeklySummaryServiceTest.php` | Unit | 19 | Eligibility filter (soft-deleted, unverified, inactive, <3-weeks, happy), generateForUser (happy, idempotency, quota block, budget block, Claude exception), dispatchEmailFor (opted in, opted out, race window reread, failed noop), markDismissed, convertToList (happy, freemium 403, other-user 404), currentWeekStart. |
| `tests/Feature/DispatchWeeklySummaryCommandTest.php` | Feature | 6 | Happy path + Mail::fake queue assertion, kill switch off, per-user failure isolation (custom anonymous FakeClaudeClient subclass that throws on call #2 only), empty users exit 0, metrics logging, idempotent second run. |
| `tests/Feature/WeeklySummaryEndpointsTest.php` | Feature | 19 | All 5 endpoints: latest (4 cases + auth), dismiss (2), convert-to-list (4 including freemium + ownership), settings toggle (4), unsubscribe (4: valid, expired, tampered, replay idempotent). |

**Total new tests: 44. Backend suite: 479 → 523 passing (1008 assertions). Zero regressions.**

## API Contract (Backend → Frontend)

### Endpoints Created

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/weekly-summary/latest` | `auth:api` | Returns current-week summary or 404 if none / dismissed / failed |
| POST | `/api/weekly-summary/dismiss` | `auth:api` | Marks the week's banner as dismissed |
| POST | `/api/weekly-summary/{summary}/convert-to-list` | `auth:api` + ownership | Converts summary payload to a new ShoppingList (subject to freemium limit) |
| POST | `/api/settings/weekly-summary-email` | `auth:api` | Toggle email opt-in: `{ "enabled": bool }` |
| GET | `/unsubscribe/weekly-summary/{user}` | `signed` middleware | Public signed URL, flips opt-in off, renders HTML confirmation |

### Request/Response Examples

**GET /api/weekly-summary/latest (200)**
```json
{
  "data": {
    "summary": {
      "id": 42,
      "week_start_date": "2026-04-13",
      "products": [
        {
          "nombre": "Leche",
          "cantidad_tipica": 1.0,
          "unidad_tipica": "L",
          "categoria": "lacteos_huevos",
          "reason": "Compra semanal habitual"
        }
      ]
    }
  }
}
```

**GET /api/weekly-summary/latest (404 NO_SUMMARY_THIS_WEEK)**
```json
{
  "error": {
    "code": "NO_SUMMARY_THIS_WEEK",
    "message": "No hay resumen para esta semana."
  }
}
```

**GET /api/weekly-summary/latest (404 DISMISSED)**
```json
{
  "error": {
    "code": "DISMISSED",
    "message": "Ya descartaste el resumen de esta semana."
  }
}
```

**POST /api/weekly-summary/dismiss (200)**
```json
{ "data": { "message": "Resumen descartado." } }
```

**POST /api/weekly-summary/{id}/convert-to-list (201)**
```json
{
  "data": {
    "id": 99,
    "user_id": 7,
    "name": "Resumen semanal del 13/04/2026",
    "emoji": "📅",
    "status": "active"
  }
}
```

**POST /api/weekly-summary/{id}/convert-to-list (403 FREEMIUM_LIMIT)**
```json
{
  "error": {
    "code": "FREEMIUM_LIMIT",
    "message": "Has alcanzado el limite de 3 listas activas. Archiva o elimina una lista para crear otra nueva."
  }
}
```

**POST /api/settings/weekly-summary-email (200)**
```json
// request body
{ "enabled": true }

// response
{ "data": { "weekly_summary_email_opted_in": true } }
```

### Error Codes

| Code | Meaning | Frontend Action |
|------|---------|-----------------|
| 401 | Unauthorized | Redirect to login |
| 403 FREEMIUM_LIMIT | Freemium 3-list cap reached | Offer to archive an active list |
| 404 NO_SUMMARY_THIS_WEEK | No summary exists for the current ISO week | Hide the banner; show empty state on page |
| 404 DISMISSED | Summary exists but the user already dismissed this week | Hide the banner |
| 404 (convert-to-list) | Summary does not belong to the authenticated user | Hide the convert CTA |
| 422 | Validation error on toggle | Surface "enabled must be boolean" |

## Implementation Decisions

1. **Activity-based eligibility instead of login-based** — see Scope Changes. Proxy via `MAX(producto_historial.fecha_compra) >= now - 60 days`. Caught during S4 when implementing `eligibleUsers`; zero auth code touched.
2. **Unique constraint is the ONLY dedup mechanism** — no pre-check, no cache. The `QueryException` with `errorInfo[1] == 1062` (MySQL duplicate key code) is caught and translated to "return existing row". Race-safe.
3. **`dispatchEmailFor` re-reads the opt-in flag** via a fresh `DB` query (`User::where('id', $user->id)->value(...)`) rather than trusting the `User` object from the earlier eligibility snapshot. Closes the race window per PRD risk table.
4. **`convertToList` uses `ShoppingListService::create`** which already wraps its own transaction + freemium check. WeeklySummaryService never touches the freemium counter directly. DRY + invariant preservation.
5. **Ownership check in controller returns 404 not 403** to avoid existence leaks. A user probing summary IDs that aren't theirs gets the same response as a missing summary.
6. **`DispatchWeeklySummary` command never aborts** — per-user exceptions are caught and logged, loop continues. Exit code is always `SUCCESS`. Metrics are logged once at the end. The command is safe to rerun manually because the service is idempotent.
7. **`WeeklySummaryMail implements ShouldQueue`** — matches the existing Mailable pattern in this project. Tests use `Mail::assertQueued` (not `assertSent`). Noted during test development.
8. **`Mail::to($user->email)` instead of `Mail::to($user)`** — passes email string directly to avoid serialization of the User model via ShouldQueue/SerializesModels. Avoids subtle issues with the `$user` property on the mailable being re-hydrated.
9. **Kill switch in the COMMAND not the SERVICE** — `config('ai.weekly_summary.enabled')` is checked at command entry only. If a developer runs `WeeklySummaryService::generateForUser` manually via tinker, the kill switch does NOT apply. This is deliberate: the kill switch is a CRON-level gate, not a business-level gate.
10. **`withoutOverlapping(60)` + `onOneServer()`** both applied. The first prevents double-runs on the same server; the second coordinates across a multi-server deploy via the cache driver.
11. **Route::signed middleware uses Laravel's built-in HMAC over `APP_KEY`** — no custom signing logic. 30-day TTL from `config('ai.weekly_summary.unsubscribe_token_ttl_days')`.
12. **Unsubscribe route registered before the SPA catch-all** in `routes/web.php`. The catch-all regex was also updated to exclude `unsubscribe` explicitly as belt-and-suspenders.
13. **`SeedsProductHistory` trait** inserts directly via `DB::table` (not via factory) because `ProductoHistorial` does not have a factory today and creating one is out of scope. Direct insert is cleaner for test helper purposes.

## Deviations from Design

| Deviation | Reason |
|-----------|--------|
| `users.last_login_at` filter → `MAX(producto_historial.fecha_compra)` activity proxy | Design assumed a column that does not exist. User confirmed option D during S4. |
| `eligibleUsers()` uses two sequential queries (active_user_ids → within_that_set find ≥3 weeks) instead of one big subquery | Splitting avoids a correlated subquery / derived table over `producto_historial` and keeps each pass indexable. Same result, clearer plan. |
| `DispatchWeeklySummary` command does not use `chunkById(100)` in V1 | The design mentioned chunking for memory safety; in practice the current eligibility query returns a single `Collection<User>` (V1 expected <1000 users). If the collection becomes too large, a trivial refactor to `->chunk(100)` is a 1-line change. Documented here as tech debt. |
| Seasonal products rely 100% on Claude inference | Per decision #9 — not a deviation, but re-noted so reviewers know the acceptance of prompt drift risk is intentional. |

## Known Issues / Technical Debt

1. **No `chunkById(100)` in the command iteration** — V1 assumed <1000 users. If user count grows, refactor to chunked iteration. Low risk today.
2. **`WeeklySummaryService::eligibleUsers` does two queries** — a single JOIN would be faster but harder to read and index. Profile before optimizing.
3. **No admin dashboard / Backpack CRUD** to inspect generated summaries or inspect failed rows. Out of scope per PRD.
4. **No per-run cost cap** — relies on `BudgetCap` global only. If a single run somehow generates runaway cost (e.g. Claude outage loop with retries), the existing monthly budget is the only brake. Acceptable for V1 per decision #14.
5. **`ProductoHistorial` still has no factory** — the `SeedsProductHistory` trait uses `DB::table`. If more tests need product history fixtures, a factory would simplify them.
6. **Frontend phase complete** — see below.

---

## Frontend Phase Summary

Frontend implemented following existing project conventions: JSX (no TypeScript), Context API (no Zustand despite stack.yaml), Tailwind CSS, Axios via `lib/api.js`, `@testing-library/react` + vitest.

### New Files

| File | Description |
|------|-------------|
| `resources/js/lib/weeklySummaryApi.js` | API client: fetchLatestSummary, dismissSummary, convertSummaryToList, updateWeeklySummaryEmail |
| `resources/js/components/dashboard/WeeklySummaryBanner.jsx` | Persistent banner on dashboard; fetch on mount, dismiss on X click, navigate to /app/resumen |
| `resources/js/pages/WeeklySummaryPage.jsx` | Full page: product list, convert-to-list CTA, freemium error handling, loading/empty states |
| `resources/js/components/dashboard/WeeklySummaryBanner.test.jsx` | 6 vitest tests: loading, no-summary, product count, dismiss, view link, singular text |
| `resources/js/pages/WeeklySummaryPage.test.jsx` | 7 vitest tests: loading, empty, products, date, convert happy, freemium error, API error |

### Modified Files

| File | Change |
|------|--------|
| `resources/js/app.jsx` | Added import + `<Route path="/app/resumen" element={<WeeklySummaryPage />} />` inside ProtectedRoute |
| `resources/js/pages/DashboardPage.jsx` | Added `<WeeklySummaryBanner />` above `<ReplenishmentBanner />` |
| `resources/js/pages/ProfilePage.jsx` | Added weekly summary email toggle section with `role="switch"` aria, useEffect to fetch initial state, optimistic toggle with revert on error |
| `resources/js/pages/ProfilePage.test.jsx` | Added 5 new tests for the weekly summary toggle (render, off-by-default, on-when-opted-in, API call, revert on failure) |
| `app/Http/Controllers/Auth/ProfileController.php` | **Minimal backend touch (scope exception)**: added `weekly_summary_email_opted_in` to the `/api/profile` response so the toggle renders the user's real state. 1 line added. |

### Frontend Test Report

| Suite | Before | After | New |
|-------|--------|-------|-----|
| Total frontend | 208 | 226 | +18 |
| WeeklySummaryBanner | — | 6 | 6 |
| WeeklySummaryPage | — | 7 | 7 |
| ProfilePage (new toggle tests) | 10 | 15 | 5 |

**226/226 passing. Zero regressions.**

### Deviation: Backend touch in frontend phase

`ProfileController::show` had to include `weekly_summary_email_opted_in` in its response for the settings toggle to render the user's real opt-in state. This is a 1-line data-exposure addition to an already-authenticated endpoint. The alternative (a separate `GET /api/settings/weekly-summary-email` endpoint) would have been more scope creep than 1 line.

## Test Coverage Report

| Component | Coverage |
|-----------|----------|
| `WeeklySummaryService` | 100% (19 tests, all 5 public methods + helper) |
| `DispatchWeeklySummary` command | 100% (6 feature tests, all branches) |
| `WeeklySummaryController` | 100% (latest 5, dismiss 2, convert-to-list 4) |
| `WeeklySummaryEmailController` | 100% (opt-in, opt-out, validation, auth) |
| `UnsubscribeWeeklySummaryController` | 100% (valid, expired, tampered, replay) |
| `WeeklySummaryMail` | Covered transitively via service + command tests |
| `ClaudeClient::generateWeeklySummary` | Smoke: covered by `FakeClaudeClient` substitution in service tests. Real HTTP path would require mocking Http::fake — deferred as tech debt (follows existing project pattern for Claude client: only the fake is covered by tests, real client is smoke-verified only). |
| `FakeClaudeClient::generateWeeklySummary` | Covered by 19 service tests + 6 command tests that call it. |
| **Backend total** | **523/523 passing (1008 assertions)** — no regressions to the pre-existing 479 tests. |

## Notes for Reviewers

- **`composer security` runs clean** on the new code: composer audit 0 advisories, psalm taint analysis 0 errors (42s run). The psalm baseline established in FEAT-OPS-SECURITY-GATES remains at zero.
- **Kill switch behavior is command-level, not service-level**. A manual tinker session calling `WeeklySummaryService::generateForUser` will still hit Claude regardless of `AI_WEEKLY_SUMMARY_ENABLED`. Document this in runbooks.
- **Idempotency proof**: the test `test_idempotent_second_run_same_week_does_not_duplicate` runs the full command twice and asserts `WeeklySummary::count() == 1`. This is the proof-of-life for AC-3.
- **Failure isolation proof**: the `test_failure_isolation_one_user_fails_others_succeed` test uses an anonymous subclass of `FakeClaudeClient` that throws on the SECOND call only. The first user succeeds, the second fails, the command exits 0. This is the proof-of-life for AC-4.
- **SecurityGatesIntegrationTest** was NOT re-run in isolation (part of the 523 total). It's part of the backend suite and remains green.
- **Frontend phase** still to come. After user approval of the backend, run `python cli/cli.py prepare FEAT-EPIC5C-SUMMARY --mode=claude` and select option 2 (frontend only).
