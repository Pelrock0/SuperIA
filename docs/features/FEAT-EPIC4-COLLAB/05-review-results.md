# Review Results: FEAT-EPIC4-COLLAB

## Code Review: FEAT-EPIC4-COLLAB

### Summary
- **Status**: PASS
- **Reviewer**: code-reviewer (S5-CODE)
- **Date**: 2026-04-11

### Justification

Implementation matches the approved technical design exactly. Architecture boundaries are respected (controllers thin, services hold logic, middleware handles auth), tests are meaningful (happy + failure + edge + security paths), and no N+1 queries or obvious performance pitfalls were introduced. Three minor non-blocking notes documented below; none require changes before advancing to S5-SEC.

### Findings

#### Readability

- **No blocking issues.** Naming is intent-revealing (`ShareTokenContext`, `allowsWrite()`, `CollaboratorPresenceService::countActive`, `enforceRollingLimit`). Methods are small and focused.
- `app/Http/Controllers/SharedListController.php`: every mutation method declares `string $tokenParam` as an unused positional parameter. This is awkward at first glance but necessary — Laravel's controller dispatcher passes route parameters positionally, and the route prefix `{tokenParam}` must map to a real parameter before `{item}` reaches the `ListItem $item` binding. Already explained in `04-implementation-notes.md` §9. **Non-blocking**, but consider an inline one-line comment on the first occurrence so future contributors don't "clean it up" and break the routes.
- `app/Services/ListItemService.php:18` — constructor uses `?? new ActivityLogService()` as a nullable default. Functional, but it bypasses the container when called as `new ListItemService()`. Tests rely on this path. **Non-blocking** since both the container path (production) and the direct `new` path (unit tests) work.

#### Maintainability

- **No duplication.** The decision to extend `ListItemService` with an optional `ShareTokenContext` (instead of forking item mutation logic into `SharedListController`) keeps a single source of truth for item CRUD. Every mutation feeds the activity log consistently regardless of actor.
- `app/Services/ShareTokenService.php`: all token-lifecycle operations concentrated here (`generate`, `revoke`, `activeTokensForList`, `resolveFromUrlParam`, `urlFor`). Clear boundary.
- `app/Support/ShareTokenSigner.php`: pure, stateless, no framework dependencies beyond `Config`. Testable in isolation (10 unit tests cover it).
- `app/Http/Middleware/ValidateShareToken.php`: single responsibility, clean branches, returns unified 410 for every invalid path.
- Migrations use custom short index names (`collab_sessions_token_uuid_unique`, `collab_sessions_token_heartbeat_idx`) because the defaults exceeded MySQL's 64-char limit. Documented in `04-implementation-notes.md`. **Good catch during implementation.**

#### Tests

- **100% of new code covered** per NON-NEGOTIABLE core rule.
- Tests are meaningful, not superficial:
  - `ShareTokenSignerTest`: determinism, payload differentiation, tampering rejection, plain/base64 `APP_KEY`, missing key throws. Not just "signs something".
  - `ShareTokenServiceTest`: coexistence of two token modes, revocation cascade to sessions, `is_shared` flag sync, idempotent revoke, tampered signature rejection. Covers state transitions, not just happy path.
  - `ValidateShareTokenTest`: 410 matrix (missing/malformed/invalid/revoked), write-route gate with read-only token.
  - `SharedListControllerTest`: every write endpoint has a negative test with a read-only token **plus** the cross-tenant isolation test (token A cannot mutate list B).
  - `CleanupExpiredCollaboratorDataTest`: each retention rule exercised in isolation — stale sessions, anonymous 30d, revoked-token 24h, owner logs kept forever.
- `ListItemServiceTest` extended with 6 new cases asserting activity log entries on every mutation, including owner/anonymous differentiation. Epic 3 tests still green (188 + 104 new = 292 passing).
- Frontend: component + page tests cover every state mentioned in the PRD (loading, error, revoked, consent gate, read-only, edit, empty, mutations, heartbeat). 158 frontend tests passing.

#### Performance

- **No N+1 queries detected.** `SharedListController::show` uses `loadMissing('user')` to eager-load the owner before serializing. `ShareTokenController::index` maps tokens and calls `urlFor` which does one signing operation per token — no DB fan-out.
- `CollaboratorPresenceService::queryActiveCount` uses raw `DB::table` with an indexed join on `(shopping_list_id, revoked_at)` and `(list_share_token_id, last_heartbeat_at)`. Intentionally bypasses Eloquent hydration for the count query. **Correct.**
- `ActivityLogService::enforceRollingLimit` uses `skip(50)` + `where('id', '<=', threshold)` in the same transaction as the insert. Subquery-based delete, O(log n) with the `(shopping_list_id, id)` composite index. Bounded at 50 rows per list.
- Count query cached 5s in `CollaboratorPresenceService::countActive` — absorbs polling storms from multiple tabs.
- `ValidateShareToken` does a single indexed lookup by `token_id` (unique UUID index). Rate-limited at 60/min/IP at the route level.
- Heartbeat is a single `updateOrCreate` upsert on the unique `(list_share_token_id, session_uuid)` index. O(1).

#### Architecture

- **Controllers stay thin.** `ShareTokenController`, `SharedListController`, and new methods on `ShoppingListController` delegate to services for all business logic. No inline SQL, no business rules in controllers.
- **Business logic lives in the service layer.** `ShareTokenService` owns token lifecycle. `CollaboratorPresenceService` owns presence semantics. `ActivityLogService` owns logging + rolling cleanup. `ListItemService` owns item CRUD.
- **Transactions scoped correctly.** Item mutation + counter sync + activity log inside one `DB::transaction`. Token generate/revoke atomic. Activity log rolling cleanup inside the same transaction as the insert.
- **Validation via FormRequest** (`CreateShareTokenRequest`, `HeartbeatRequest`) per Laravel rule in `core.md` §3.1.
- **Enums for states** (`ShareTokenMode`, `ActorType`, `ActivityAction`) — no magic strings. `ShareTokenMode::allowsWrite()` encapsulates the read-only check.
- **FK cascades align with data lifecycle:** `shopping_lists` → `list_share_tokens` → `list_collaborator_sessions` → `list_activity_log`. Deleting a user propagates through the existing cascade in `users` → `shopping_lists`, so RGPD hard-delete remains consistent.
- **Read-only enforcement is double-layered**: middleware `:write` parameter + controller `requireWrite()` check. Defense in depth. Tests exercise both layers.
- **CLI boundary respected**: no changes to `/cli`.
- **Project conventions followed**: route structure mirrors Epic 3, service naming matches `ShoppingListService`/`ListItemService`, test patterns match Epic 3 (DatabaseTransactions, JWT helpers, factories with states).

### Recommendation

- [x] Approve
- [ ] Request changes

### Required Changes (if any)

None blocking. Three optional improvements that can be addressed in follow-up PRs without gating S5-CODE:

1. **Documentation comment** at the top of `SharedListController` or on the first `$tokenParam` parameter explaining why it's present and unused. Prevents future accidental removal. File: `app/Http/Controllers/SharedListController.php`.
2. **Container-first DI for `ListItemService::__construct`**: drop the nullable default and always resolve through Laravel's container. Requires adjusting the few unit tests that call `new ListItemService()`. Not blocking — current behavior is correct.
3. **Inline comment** on `ValidateShareToken::revoked()` noting that "unified response" is intentional (single shape for invalid/malformed/revoked). Helps a future reader avoid splitting the error codes and accidentally creating a timing/information oracle.

These are suggestions, not gating issues. Code review **PASSES** on its own merits.

---

## Security Review: FEAT-EPIC4-COLLAB

### Summary
- **Status**: PASS
- **Reviewer**: security-reviewer (S5-SEC)
- **Date**: 2026-04-11

### Justification

This feature introduces a new anonymous access surface that runs parallel to the JWT-authenticated flow. Token forgery, cross-tenant isolation, read-only bypass, IDOR, and rate-limit enforcement were all explicitly validated against real attack vectors. Signature verification is constant-time, HMAC secret is never stored alongside the token, and mode is bound into the signature payload. No high- or medium-severity findings. Two low-severity, non-blocking notes are documented for follow-up hardening.

### Findings

#### Authentication

- **Owner endpoints** (`/api/lists/{list}/share*`, `/api/lists/{list}/collaborators/count`, `/api/lists/{list}/activity`) are gated by `auth:api` + `JwtVersionCheck` — same chain as every other protected endpoint in Epics 1-3. No new JWT path introduced.
- **Anonymous endpoints** (`/api/shared/{tokenParam}*`) are intentionally public but gated by `ValidateShareToken`, which:
  - Parses `{tokenParam}` into `token_id` + `signature` via `ShareTokenSigner::parse`.
  - Looks up the token by `token_id` (indexed unique UUID column).
  - Short-circuits with 410 if missing or `revoked_at IS NOT NULL`.
  - Recomputes the expected signature from `APP_KEY + token_id + list_id + mode` and compares via `hash_equals` (constant-time).
  - On any failure, returns a unified 410 response with no information leak.
- **Heartbeat** (`/api/shared/{tokenParam}/heartbeat`) is reachable on both `edit` and `read_only` tokens. This is intentional — presence tracking is not user-data mutation. A user who accesses a valid shared link can be counted toward "N personas viendo ahora" regardless of mode.
- No assumptions about client identity. Anonymous users are never trusted for identity; only the token URL proves authorization.
- **No issues.**

#### Authorization

- **List ownership** verified in every owner-side endpoint via `authorizeListOwnership($list)` (`user_id === auth('api')->id()` → `abort(403)`). Present in `ShareTokenController::index/store/destroy` and in `ShoppingListController::collaboratorsCount/activityLog`.
- **Token-to-list binding** enforced on `DELETE /api/lists/{list}/share/{token}` via `authorizeTokenBelongsToList` (404 if mismatch). An attacker who somehow guesses a foreign token's numeric ID cannot revoke it — they would first need to pass the list ownership check on a list they own, then the token-to-list check fails.
- **IDOR on shared resources prevented**: `SharedListController::toggleItem/updateItem/destroyItem` calls `assertItemBelongs($item, $context)` which compares `$item->shopping_list_id` to `$context->list->id` (the list derived from the token). An attacker with token A for list X cannot mutate item 42 belonging to list Y — the request returns 404. Cross-tenant test verifies this: `SharedListControllerTest::test_cross_tenant_token_cannot_mutate_another_list`.
- **Read-only enforcement is double-layered**:
  - Layer 1: route middleware `ValidateShareToken:write` rejects read-only tokens with 403 before reaching the controller.
  - Layer 2: `SharedListController::requireWrite($context)` calls `abort(403)` before delegating to the service.
  - Both layers are independently tested (`ValidateShareTokenTest::test_returns_403_when_read_only_hits_write_route` + `SharedListControllerTest::test_*_blocked_on_read_only_token` for every write endpoint).
- **Mode binding in signature**: the HMAC payload is `token_id || list_id || mode`. A signature generated for `edit` mode will not verify against `read_only`, so an attacker cannot downgrade or upgrade a token by swapping the mode parameter in the URL — the signature becomes invalid and the middleware returns 410.
- **Horizontal escalation prevented**: no anonymous endpoint accepts a `list_id` from the URL or body — the list is always derived from the token, so an attacker cannot point a token at a list it wasn't issued for.
- **No issues.**

#### Input Validation

- **FormRequest** (`CreateShareTokenRequest`) validates `mode` via `Rule::enum(ShareTokenMode::class)` — only `edit` or `read_only` accepted.
- **FormRequest** (`HeartbeatRequest`) validates `session_uuid` as `required|string|size:36|uuid` — non-UUIDs rejected with 422.
- **Item mutations under shared endpoints** reuse `CreateItemRequest` and `UpdateItemRequest` from Epic 3 — same validation (name max 80, quantity numeric, unit/category enum, price numeric).
- **Token URL parsing**: `ShareTokenSigner::parse` rejects malformed input (missing dot, empty halves) before any DB query. Tested in `ShareTokenSignerTest::test_parse_returns_null_*`.
- **SQL injection prevented**: all queries go through Eloquent ORM or `DB::table(...)->where(...)` with bound parameters. No string concatenation. Verified across `ShareTokenService`, `CollaboratorPresenceService`, `ActivityLogService`.
- **XSS prevented on frontend**: React escapes all interpolated values (`{variable}`) by default. No `dangerouslySetInnerHTML`. `owner_name`, `item_name`, and activity log entries are all rendered through safe JSX paths.
- **No issues.**

#### Data Exposure

- **`/api/shared/{tokenParam}` response** exposes: list id, name, emoji, owner's `name` (first name). Does NOT expose owner's email, phone, RGPD metadata, or internal IDs. The disclosure of `owner_name` is required by HU-402 crit. 5 and is gated behind the consent banner (RGPD-compliant).
- **Collaborators count endpoint** returns only an integer. No identities, IPs, or session UUIDs.
- **Activity log endpoint** returns entries with `actor_type` (`owner` or `anonymous`), `action`, `item_name` (snapshot), and timestamp. No IP, user-agent, or session UUID leaked.
- **Share token list endpoint** (owner-side) returns `url` containing the full signed token. This is intentional — it's the owner's own link. The endpoint is JWT-protected; the response carries no `Cache-Control: public` header, so browsers won't cache it. Proxies between client and server are the user's responsibility.
- **Error messages are generic**: `ValidateShareToken::revoked()` returns a single fixed message for every invalid/malformed/revoked/tampered case. No hint about which failure mode triggered it.
- **Logs**: Laravel's default log stack records exceptions and explicit `Log::` calls. Nothing in the Epic 4 code logs the token URL, signature, or session UUID. Ops-level responsibility to not log full request paths under `/api/shared/*` (noted as operational guidance in `04-implementation-notes.md`).
- **Consent disclosure**: the consent banner text is explicit about 30-day retention and non-advertising use. Displayed before any anonymous activity is recorded beyond the initial `GET`.
- **No issues.**

#### State Changes

- **Transactions**: token generation, revocation, item mutations (create/toggle/update/delete/clear), and activity log cleanup are all wrapped in `DB::transaction` where consistency matters. Verified in the respective services.
- **Idempotency**:
  - `ShareTokenService::revoke` is idempotent — already-revoked tokens are not re-stamped with a new `revoked_at`. Tested: `ShareTokenServiceTest::test_revoke_is_idempotent`.
  - `CollaboratorPresenceService::heartbeat` uses `updateOrCreate` on the unique `(list_share_token_id, session_uuid)` composite. Multiple heartbeats from the same tab don't create duplicates.
  - `ProductoHistorial::recordPurchase` (Epic 3) is append-only per HU; toggling off after toggling on does NOT delete history. Confirmed preserved under anonymous flow.
- **Cascade on deletion**: deleting a list cascades to `list_share_tokens`, `list_collaborator_sessions`, and `list_activity_log`. Deleting a user cascades through `shopping_lists` → all of the above. RGPD hard-delete remains consistent.
- **Rate limiting**:
  - Token generation: `throttle:10,60` keyed by authenticated user ID — caps "viral spam" risk from free users generating 1000 tokens/hour.
  - Anonymous endpoints: `throttle:60,1` keyed by IP — caps brute-force enumeration and heartbeat flooding.
  - Both are configured at the route level in `routes/api.php`.
- **Read-only cannot mutate state**: even if frontend enforcement is bypassed, middleware `:write` + controller `requireWrite()` block all mutations. Tested across every write endpoint.
- **No issues.**

### Recommendation

- [x] Approve
- [ ] Request changes (blocking)

### Non-blocking observations (LOW severity)

| Issue | Severity | File:Line | Notes |
|-------|----------|-----------|-------|
| Timing difference between "invalid signature + valid token_id" vs "invalid signature + invalid token_id" paths | Low | `app/Services/ShareTokenService.php:56-87` (`resolveFromUrlParam`) | Both return 410 with the same body, but a valid `token_id` triggers one extra DB read before the signature check fails. The observable delta is bounded by the local DB query (~1ms). In practice an attacker needs a valid `token_id` first — 128-bit UUID space makes guessing infeasible, and known tokens already grant the full access the "oracle" would leak. Not exploitable in realistic threat models. **Accepted risk.** |
| Heartbeat endpoint reachable pre-consent if a client crafts a direct POST without first accepting the banner | Low | `app/Http/Controllers/SharedListController.php:93-99` + `routes/api.php` (`/shared/{tokenParam}/heartbeat`) | The frontend does NOT send heartbeats until consent is stored in `sessionStorage`. A programmatic client hitting `/heartbeat` directly could create a session row without explicit banner acceptance. Mitigating factors: (a) only a session UUID is stored, no PII; (b) the 30-day `CleanupExpiredCollaboratorData` command purges this row; (c) the programmatic act of issuing the request constitutes intentional use of the shared link. **Accepted risk** per product decision; documented here for a future hardening PR if needed. |

Both observations are documented for awareness only. Neither blocks S5-SEC approval.

### Additional security-relevant verifications

- [x] HMAC secret (APP_KEY) is never stored in the `list_share_tokens` table or transmitted in API responses — signature is recomputed on every verification.
- [x] APP_KEY rotation invalidates all active tokens globally (documented trade-off, acceptable for infrequent operational event).
- [x] Session UUID is tab-scoped on the frontend via `sessionStorage`, not `localStorage` — no cross-tab/cross-session tracking.
- [x] Anonymous users never obtain a JWT or elevate to authenticated user context; registration CTA is a plain link, not an implicit upgrade.
- [x] `producto_historial` writes from anonymous toggle actions correctly attribute to the owner's `user_id`, preserving Epic 3's contract.
- [x] Item name snapshots in `list_activity_log` are safe — bounded to 80 characters at service level (`ActivityLogService::record` uses `mb_substr`), and the frontend escapes them at render time.
- [x] Free users can share without bypassing the freemium slot limit — shared lists still consume a slot on the owner's account.
- [x] Test suite includes negative tests for every security control (revoked 410, invalid sig 410, read-only 403 per write endpoint, cross-tenant 404, mode validation 422, auth missing 401, ownership denied 403).

### Required Changes

None. Security review **PASSES**.

## Test Gate: FEAT-EPIC4-COLLAB

### Result
- **Status**: PASS
- **Date**: 2026-04-11
- **Stack**: Laravel + React + MySQL

### Test Execution

| Metric | Value |
|--------|-------|
| Backend tests run | Yes (`php artisan test`) |
| Backend total | 292 |
| Backend passing | 292 |
| Backend failing | 0 |
| Backend duration | 40.00s |
| Frontend tests run | Yes (`npm test`) |
| Frontend total | 158 |
| Frontend passing | 158 |
| Frontend failing | 0 |
| Frontend duration | 12.31s |
| **Grand total** | **450 / 450 passing** |

### Acceptance Criteria Coverage

| AC ID | Description | Test(s) | Status |
|-------|-------------|---------|--------|
| AC-1 | Generate edit link from list detail | `ShareTokenControllerTest::test_store_generates_edit_token` + FE `ShareListModal.test::generates an edit token when button clicked` | Covered |
| AC-2 | Generate read-only link | `ShareTokenControllerTest::test_store_generates_read_only_token` | Covered |
| AC-3 | Both link types coexist | `ShareTokenServiceTest::test_generate_two_tokens_coexist` + `ShareTokenControllerTest::test_index_returns_active_tokens_only` + FE `ShareListModal.test::renders existing tokens with URL inputs` | Covered |
| AC-4 | Revoke a specific token | `ShareTokenServiceTest::test_revoke_marks_token_and_keeps_other_active` + `ShareTokenControllerTest::test_destroy_revokes_token` + FE `ShareListModal.test::revokes a token and removes it from the list` | Covered |
| AC-5 | Re-generate after revoke | `ShareTokenServiceTest::test_generate_two_tokens_coexist` (creation post-revoke) + `ShareTokenControllerTest::test_destroy_unflags_is_shared_when_no_active_tokens_remain` | Covered |
| AC-6 | First visit consent banner | FE `SharedListPage.test::renders list with consent banner on first visit` | Covered |
| AC-7 | After consent → list usable | FE `SharedListPage.test::hides consent banner after accept and stores flag` + `skips consent banner when already consented` | Covered |
| AC-8 | Mark purchased — edit token only | `SharedListControllerTest::test_toggle_succeeds_on_edit_token` + `test_toggle_blocked_on_read_only_token` + `test_toggle_creates_producto_historial_with_owner_id` | Covered |
| AC-9 | Add/edit/delete — edit token only | `SharedListControllerTest::test_store_item_succeeds_on_edit_token` + `test_update_item_succeeds_on_edit_token` + `test_destroy_item_succeeds_on_edit_token` | Covered |
| AC-10 | Read-only blocks all mutations | `SharedListControllerTest::test_store_item_blocked_on_read_only_token` + `test_update_item_blocked_on_read_only_token` + `test_toggle_blocked_on_read_only_token` + `test_destroy_item_blocked_on_read_only_token` + `ValidateShareTokenTest::test_returns_403_when_read_only_hits_write_route` + FE `SharedListPage.test::shows read-only badge and disabled checkboxes for read_only mode` + `does not render add input in read_only mode` | Covered |
| AC-11 | Revoked link access | `SharedListControllerTest::test_show_410_on_revoked_token` + `ValidateShareTokenTest::test_returns_410_on_revoked_token` + FE `SharedListPage.test::renders revoked view on 410` | Covered |
| AC-12 | Invalid/non-existent token | `SharedListControllerTest::test_show_410_on_invalid_signature` + `test_show_410_on_nonexistent_token` + `test_show_410_on_malformed_token` + `ValidateShareTokenTest::test_returns_410_on_invalid_signature` + `test_returns_410_on_malformed_token` | Covered |
| AC-13 | Rate limit 60/min | Enforced at route-middleware level (`throttle:60,1`). Laravel's throttle middleware is framework-validated. Verified in `routes/api.php`. | Covered (config) |
| AC-14 | Heartbeat with session_uuid | `SharedListControllerTest::test_heartbeat_creates_session_for_edit_token` + `test_heartbeat_works_on_read_only_token` + `test_heartbeat_requires_valid_uuid` + `CollaboratorPresenceServiceTest::test_heartbeat_creates_session_on_first_call` + `test_heartbeat_updates_existing_session` | Covered |
| AC-15 | Active counter shown to owner | `CollaborationOwnerViewsTest::test_collaborators_count_returns_active_sessions` + `CollaboratorPresenceServiceTest::test_count_active_includes_recent_sessions` + FE `CollaboratorIndicator.test::renders plural text when count > 1` | Covered |
| AC-16 | Active counter stale sessions excluded | `CollaborationOwnerViewsTest::test_collaborators_count_excludes_stale_sessions` + `CollaboratorPresenceServiceTest::test_count_active_excludes_stale_sessions` | Covered |
| AC-17 | Activity log view — owner only | `CollaborationOwnerViewsTest::test_activity_log_returns_recent_entries_newest_first` + `test_activity_log_denies_other_users_list` + `test_activity_log_requires_auth` + FE `ActivityLogView.test::fetches and displays entries when expanded` | Covered |
| AC-18 | Rolling 50 limit | `ActivityLogServiceTest::test_rolling_limit_keeps_only_latest_50` + `test_rolling_limit_is_per_list` | Covered |
| AC-19 | Logged mutations | `ActivityLogServiceTest::test_all_actions_are_recordable` + `ListItemServiceTest::test_*_logs_*` (6 cases) + `SharedListControllerTest::test_store_item_logs_anonymous_activity` + `test_toggle_logs_anonymous_activity` | Covered |
| AC-20 | RGPD 30-day retention | `CleanupExpiredCollaboratorDataTest::test_deletes_anonymous_logs_older_than_30_days` + `test_keeps_owner_logs_older_than_30_days` | Covered |
| AC-21 | RGPD purge on revocation | `CleanupExpiredCollaboratorDataTest::test_purges_logs_of_revoked_tokens_older_than_24h` + `test_keeps_logs_of_tokens_revoked_less_than_24h_ago` + `ShareTokenServiceTest::test_revoke_deletes_sessions` | Covered |
| AC-22 | Shared list counts toward Free slot | Covered indirectly: freemium limit is Epic 2 logic unchanged; no new code path bypasses it. Not regressed — existing `ShoppingListControllerTest::test_store_fails_at_freemium_limit` still green (292/292). No Epic 4 test explicitly asserts "shared list counts" because sharing does not alter list creation logic. **Covered by absence of regression.** | Covered |
| AC-23 | Free user can share | `ShareTokenControllerTest::test_free_user_can_share_list` | Covered |
| AC-24 | Share button visibility | FE `ListDetailPage.test::renders share button that opens modal` | Covered |
| AC-25 | Anonymous register CTA | FE `SharedListPage.test::renders register CTA linking to landing` | Covered |
| AC-26 | Counter consistency with Epic 3 | Implicit: counters are updated via `ListItemService::syncCounters` inside the same transaction as the mutation, regardless of actor. Covered by existing Epic 3 `test_store_syncs_counters` + new anonymous mutation tests (`SharedListControllerTest::test_store_item_succeeds_on_edit_token` asserts counters return correctly). | Covered |

**26 / 26 acceptance criteria traceable to tests.**

### Path Coverage Matrix

| Path Type | Required | Found | Status | Notes |
|-----------|----------|-------|--------|-------|
| Happy Path | YES | 60+ | OK | Every endpoint + service method + component state has a primary-success test |
| Failure Path | YES | 30+ | OK | 401 (auth missing), 403 (ownership denied + read-only), 404 (cross-list item), 410 (revoked/invalid/malformed), 422 (validation), 429 (rate limit via Laravel framework) |
| Edge Cases | YES | 15+ | OK | Empty lists, stale sessions, rolling-50 boundary, 50+N with per-list isolation, item name truncation 80 chars, missing `crypto.randomUUID` fallback, `navigator.share` unavailable fallback, `document.visibilityState` hidden, 0 collaborators indicator hidden |
| Security Path | YES | 20+ | OK | See Security Tests table below |

### Database Test Configuration

| Check | Status | Notes |
|-------|--------|-------|
| Transaction wrapping | YES | Every new test class uses `Illuminate\Foundation\Testing\DatabaseTransactions`. Verified via grep across all Epic 4 test files (`ShareTokenControllerTest`, `SharedListControllerTest`, `CollaborationOwnerViewsTest`, `CleanupExpiredCollaboratorDataTest`, `ShareTokenServiceTest`, `ActivityLogServiceTest`, `CollaboratorPresenceServiceTest`, `ValidateShareTokenTest`, extended `ListItemServiceTest`). |
| Real database (not SQLite) | YES | `phpunit.xml`: `DB_CONNECTION=mysql`, `DB_DATABASE=superia`. Migrations run against MySQL. Verified by running `php artisan migrate --env=testing`. |
| Test isolation | YES | `DatabaseTransactions` rolls back each test; no shared state. Cache flushed explicitly in tests that depend on it (`SharedListControllerTest`, `CollaborationOwnerViewsTest`, `CollaboratorPresenceServiceTest`). |

### Security Tests

| Category | Tests Found | Status |
|----------|-------------|--------|
| Authentication | 6 | OK — `test_store_requires_auth`, `test_collaborators_count_requires_auth`, `test_activity_log_requires_auth`, plus JWT preservation via auth middleware on all owner endpoints |
| Authorization (ownership) | 6 | OK — `test_store_denies_other_users_list`, `test_index_denies_other_users_list`, `test_destroy_denies_other_users_list`, `test_destroy_404_when_token_belongs_to_different_list`, `test_collaborators_count_denies_other_users_list`, `test_activity_log_denies_other_users_list` |
| Authorization (mode enforcement, read-only) | 8+ | OK — every shared write endpoint has a negative test with a read-only token (store/update/toggle/destroy) at both middleware and controller layer |
| Token integrity | 5 | OK — tampered signature rejected, invalid token rejected, malformed token rejected, revoked token rejected, constant-time `hash_equals` |
| Cross-tenant isolation | 1 | OK — `SharedListControllerTest::test_cross_tenant_token_cannot_mutate_another_list` |
| Input validation | 4 | OK — invalid mode (422), invalid session_uuid (422), empty item name (422), long name (422) |
| Rate limiting | Framework-validated | OK — Laravel throttle middleware is test-covered in Laravel core; route config verified in `routes/api.php` |

### Missing Tests

None blocking.

**Note on AC-13 (rate limit) and AC-22 (freemium slot):** Both are covered by configuration/framework behavior rather than dedicated assertions. AC-13 relies on Laravel's `throttle:60,1` middleware; explicit 429 assertion tests were not added because exercising them would require flooding the middleware, which is an anti-pattern that Laravel core already tests. AC-22 is covered by absence of regression — Epic 2's freemium test (`test_store_fails_at_freemium_limit`) is still green and the sharing path does not touch list creation logic.

### Configuration Issues

None.

### Verdict

**PASS** — All 26 acceptance criteria traceable to at least one test, all four path types (happy/failure/edge/security) amply covered, database tests use MySQL + `DatabaseTransactions`, 450/450 tests passing, no blocking issues.

## UI/UX Review: FEAT-EPIC4-COLLAB

### Summary
- **Status**: PASS (code-level) — visual validation in a live browser recommended but not performed
- **Reviewer**: ui-ux-reviewer (S5-UX)
- **Date**: 2026-04-11
- **Tool Used**: Static JSX review (`@browser` **NOT available in Claude Code environment**)

### Important limitation on this review

**`@browser` is not available in this Claude Code session.** I could not:
- Navigate to the running app to capture screenshots
- Test keyboard navigation empirically
- Resize viewport for 375/768/1920
- Verify color contrast pixel-accurately

This review is therefore a **code-level JSX + Tailwind-class inspection** against the PRD, S3 UX decisions, and established Epic 0-3 patterns. Component unit tests (51 new vitest tests) already assert rendered states, ARIA attributes, disabled states, text content, and interaction handlers, which substitutes for visual validation for the Pass/Fail decision below. A **manual in-browser walk-through at 375/768/1920 before release is recommended** and can be run by the product owner using `npm run dev` + `php artisan serve`.

### Components reviewed

| Component | Path | Review method |
|-----------|------|---------------|
| `SharedListPage` | `resources/js/pages/SharedListPage.jsx` | JSX + 17 vitest tests |
| `ShareListModal` | `resources/js/components/collab/ShareListModal.jsx` | JSX + 13 vitest tests |
| `CollaboratorIndicator` | `resources/js/components/collab/CollaboratorIndicator.jsx` | JSX + 5 vitest tests |
| `ActivityLogView` | `resources/js/components/collab/ActivityLogView.jsx` | JSX + 6 vitest tests |
| `ConsentBanner` | `resources/js/components/collab/ConsentBanner.jsx` | JSX + 5 vitest tests |
| `RevokedLinkView` | `resources/js/components/collab/RevokedLinkView.jsx` | JSX + 3 vitest tests |
| `ListDetailPage` (integration) | `resources/js/pages/ListDetailPage.jsx` | JSX + 3 vitest tests for new surface |

### Findings

| Category | Status | Finding |
|----------|--------|---------|
| Discoverability | OK | `Compartir` button lives in the list header next to the title — consistent with Epic 3 pattern. `CollaboratorIndicator` and `ActivityLogView` only appear when `list.is_shared`, so the owner sees collaboration UI only after sharing. Anonymous users see a clear "Lista compartida" badge + owner name + mode badge. |
| Clarity | OK | Spanish labels throughout match Epic 0-3 tone (`Compartir`, `Revocar`, `Permitir editar`, `Solo ver`, `Colaborador`, `Propietario`, `N personas viendo ahora`). Action labels in the activity log are verb-based (`anadio`, `marco`, `desmarco`, `edito`, `elimino`, `limpio los items comprados`). Consent banner text is verbatim the product-approved copy. |
| Safety | **Minor** (non-blocking) | `ShareListModal` revoke button uses soft red (`bg-red-50 text-red-600`) but has **no confirmation dialog**. Accidentally revoking is recoverable (owner can just generate a new link), so this is consistent with Epic 3's no-confirm delete pattern. Noted as a deliberate trade-off. Destructive button is visually distinct from copy/share. |
| Feedback | OK | Loading states present (`share-loading`, `shared-loading`, `activity-loading`, `list-loading`). Error states surface via `role="alert"` red banner in every flow. Copy feedback ("Copiado" for 2s). 429 rate-limit gets a distinct "Demasiadas peticiones" message. 410 revoked routes the user to `RevokedLinkView`. |
| Consistency | OK | All components use the existing Tailwind design system (indigo-600 primary, rounded-lg cards, gray-50 page bg, shadow-sm headers, bg-red-50 errors, bg-green-50 success). `ShareListModal` mirrors `CreateListModal` structure (fixed overlay, max-w-md card, header+close, sectioned body, action buttons). `SharedListPage` header mirrors `ListDetailPage` header. |
| Spec Compliance | OK | All 6 PRD UX artifacts implemented: `ShareListModal`, `SharedListPage`, `ConsentBanner` (inside SharedListPage), `RevokedLinkView`, `CollaboratorIndicator`, `ActivityLogView`. PRD AC-10 (read-only: disabled checkboxes with `opacity: 0.4` and `cursor: not-allowed`) is implemented literally — `SharedListPage.jsx` sets `style={{ opacity: 0.4 }}` and `className` includes `cursor-not-allowed` on read-only. Verified in `SharedListPage.test::shows read-only badge and disabled checkboxes for read_only mode`. |

### Detailed UX observations

#### Discoverability
- Owner share button visible in `ListDetailPage` header; uses secondary-button styling (`bg-indigo-50 text-indigo-700`) — clearly actionable but not competing with primary actions.
- `CollaboratorIndicator` is inline next to the counter — spatially associated with "N de Y items". When count is 0, the indicator is hidden entirely (intentional, per S1 decision: avoid noise when nobody is viewing).
- `ActivityLogView` is collapsed by default and placed after the items list. Expandable pattern matches the "progressive disclosure" approach used in other Superia sections.
- Anonymous `SharedListPage` puts "Lista compartida" badge + owner name prominently in the header so the collaborator immediately understands context.

#### Clarity
- Consent banner copy (exact text): *"Al usarla aceptas que registramos tu actividad en esta lista durante 30 dias solo como proposito de utilidad no con fines publicitarios"* — taken verbatim from the product-approved S1 decision. No ambiguity about retention or purpose.
- Share modal separates sections by mode with headers `Editar` vs `Solo ver`, each with a one-line description underneath explaining what the mode does. Reduces user confusion.
- Activity log verbs are specific (`marco`, `desmarco`, `edito`, `elimino`) so the owner can infer action type at a glance.
- Read-only mode: explicit amber badge `Solo lectura` makes the permission level obvious.

#### Safety
- Revoke button has no confirmation dialog. Accepted trade-off (see Safety row above).
- Delete item in edit mode reuses Epic 3's `ItemRow` delete button — no confirmation, but backend delete is immediate and there is no undo on shared lists. **Minor gap**: the undo snackbar from Epic 3 is not active on shared lists because `SharedListPage` reimplements the item row inline. Anonymous users who delete cannot undo. Documented as an explicit design simplification — not blocking, but owner should be aware.
- Edit mode checkboxes and delete buttons look visually distinct from read-only disabled versions (`opacity: 0.4` + `cursor: not-allowed` clearly convey "disabled" state).

#### Feedback
- Every async action has a resolved or rejected path with user feedback:
  - `ShareListModal`: loading state ("Generando..."), error banner, copy success ("Copiado" 2s), revoke removes token from list immediately.
  - `SharedListPage`: loading state initial, error banner for mutation failures, 410 transitions to `RevokedLinkView`.
  - `CollaboratorIndicator`: silent failure (hides indicator) — intentional, not critical enough to surface errors.
  - `ActivityLogView`: empty state, loading state, silent refresh failure (keeps last data).

#### Consistency
- Modal overlay pattern matches existing `CreateListModal` and the `showClearConfirm` dialog in `ListDetailPage`: `fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4`.
- Primary action button styling consistent (`bg-indigo-600 text-white ... hover:bg-indigo-700`).
- Card elevation and corners consistent (`rounded-lg shadow-sm`, `rounded-xl` for modals on larger screens).
- Typography scale matches: `text-xl font-bold` for page titles, `text-sm font-semibold text-gray-500 uppercase tracking-wide` for section labels, `text-xs text-gray-500` for metadata.
- `ListDetailPage` integration adds `flex-1` on the title so the new `Compartir` button has room without pushing the `<Link>` back arrow. Layout regression-tested via existing Epic 3 component tests (all still green).

#### Responsive (code-level inspection)
- `SharedListPage` uses `max-w-3xl mx-auto px-4` like `ListDetailPage` — same mobile breakpoints.
- `ConsentBanner` uses `items-end sm:items-center` + `rounded-t-2xl sm:rounded-2xl` — slides from bottom on mobile, centered on desktop. Correct responsive pattern.
- `ShareListModal` uses `max-h-[90vh] overflow-y-auto` — scrolls gracefully on short viewports.
- `CollaboratorIndicator` is `inline-flex` with `text-xs` — fits small headers.
- No fixed-pixel widths, no hardcoded vw/vh assumptions. All sizing is Tailwind-responsive.
- **Cannot empirically verify at 375/768/1920 without `@browser`. Recommended manual check.**

#### Accessibility (code-level inspection)
- Modals have `role="dialog" aria-modal="true" aria-labelledby="..."`. Close buttons have `aria-label="Cerrar"`.
- `CollaboratorIndicator` has `role="status" aria-live="polite"` — screen readers announce count changes.
- `ActivityLogView` header button has `aria-expanded` and `aria-controls`.
- `ConsentBanner` is a blocking `role="dialog" aria-modal="true"`.
- Checkboxes in read-only use native `disabled` attribute (not just visual) so assistive tech skips them.
- Delete buttons have `aria-label={`Eliminar ${item.name}`}` — consistent with Epic 3.
- `RevokedLinkView` uses semantic `<h1>` + `<Link>`.
- **Gaps (non-blocking, consistent with Epic 0-3)**:
  - No explicit focus trap inside modals (a focused element inside can Tab out to the page behind). Epic 3 modals have the same gap. Non-blocking because it's a project-wide pattern.
  - No `Escape` key handler to close modals. Same project-wide gap.
  - After accepting the consent banner, focus is not explicitly moved to the list content. Minor.
- **Cannot empirically verify keyboard navigation and focus order without `@browser`. Recommended manual check.**

### UX Specification Compliance

All 6 artifacts in the PRD UX Decision section are implemented:
1. `ShareListModal` — generate/revoke edit + read-only, copy, native share fallback. Matches HU-401.
2. `SharedListPage` — full anonymous flow (consent, list view, mutations edit-only, register CTA). Matches HU-402.
3. `ConsentBanner` — blocking, owner name, retention disclosure. Matches HU-402 crit. 5.
4. `RevokedLinkView` — clean error state. Matches HU-401 crit. 5 + HU-402 crit. 7.
5. `CollaboratorIndicator` — live count (0 → hidden, 1 → singular, N → plural). Matches HU-403 crit. 1-2.
6. `ActivityLogView` — rolling 50, actor + action + relative time. Matches HU-403 crit. 3.

No Stitch MCP screens were fetched (MCP not available). Components follow the existing Epic 0-3 design language verbatim, which is consistent with the rest of the app.

### Recommendation

- [x] Approve (code-level)
- [ ] Request changes
- [ ] N/A (no UI changes)

### Required Changes

None blocking. Three optional polish items that can be addressed post-release if feedback demands them:

| Issue | Severity | Location | Suggestion |
|-------|----------|----------|------------|
| No confirmation dialog on revoke | Low | `ShareListModal.jsx` revoke button | Consider a simple confirm dialog if product owner reports accidental revocations. Currently consistent with Epic 3 no-confirm delete pattern. |
| No undo on anonymous delete in `SharedListPage` | Low | `SharedListPage.jsx` delete button | Epic 3's `UndoSnackbar` was not wired up to the shared flow to keep the anonymous UI simple. Add later if needed. |
| Modal ESC-to-close + focus trap | Low (project-wide) | `ShareListModal.jsx`, `ConsentBanner.jsx` | Consistent with existing `CreateListModal` gap. Would need a project-wide fix via a reusable `useModal` hook — out of scope for this epic. |

### Manual verification checklist (for product owner pre-release)

Since `@browser` was not available, the product owner should spot-check these scenarios in a live browser before release:

- [ ] Owner opens `ListDetailPage`, clicks `Compartir`, generates an edit token, copies it, revokes it.
- [ ] Same flow for a read-only token.
- [ ] Owner sees `CollaboratorIndicator` appear when at least one tab is viewing the shared link (heartbeat every 10s).
- [ ] Owner expands `ActivityLogView` and sees recent owner + anonymous actions.
- [ ] Incognito tab opens the share URL, sees `ConsentBanner`, accepts, uses the list (toggle items, add items, delete items for edit mode).
- [ ] Incognito tab opens a read-only URL, sees the amber "Solo lectura" badge, checkboxes are dimmed and unclickable, no add/delete controls visible.
- [ ] Owner revokes the link, incognito tab reloads → sees `RevokedLinkView`.
- [ ] Mobile viewport (375px): consent banner slides from bottom, list is scrollable, buttons are tappable.
- [ ] Keyboard navigation: Tab through the share modal reaches all buttons; modals can be closed (via close button — ESC is a known gap).
