# Review Results: FEAT-SHARED-AUTH

## Code Review: FEAT-SHARED-AUTH

### Summary
- **Status**: CHANGES REQUIRED
- **Reviewer**: code-reviewer
- **Date**: 2026-04-15

### Justification

Implementation is solid overall — clean service layer, proper transaction boundaries, correct authorization model, and comprehensive tests (17 tests, 36 assertions). One blocking permission bug found in `incrementQuantity`, plus minor non-blocking items.

### Findings

#### Readability
- No issues. Naming is clear and consistent. Controllers are thin, services contain business logic. Frontend state management in SharedListPage is straightforward.

#### Maintainability
- **Non-blocking**: `authorizeListOwnership` and `authorizeListWrite` in `ListItemController` (lines 92-128) share identical collaborator lookup logic. The first 6 lines of each method are duplicated. Consider extracting a `resolveCollaborator` helper that returns the collaborator (or null), then each method handles the permission check. Not blocking — the current code works correctly.
- **Non-blocking**: `ShoppingListService` constructor (lines 15-19) uses `??= new ListCollaboratorService()` as a manual fallback instead of relying on Laravel's DI container. Same pattern in `RegistrationService` (lines 14-18). This works but bypasses the container — if `ListCollaboratorService` ever gains constructor dependencies, these manual instantiations will break silently. Consider injecting via constructor type-hint only.

#### Tests
- 17 tests covering all 10 ACs. Edge cases tested: idempotent save, owner self-save prevention, cascade revocation, read-only permission enforcement.
- **Non-blocking**: No test for `incrementQuantity` with a read_only collaborator (related to the blocking bug below).
- **Non-blocking**: No test for retroactive linking (`linkRetroactive`) via the registration endpoint. The service method is tested indirectly through `RegistrationService`, but a dedicated integration test would strengthen coverage.

#### Performance
- No N+1 issues. `collaboratedListsForUser` eager-loads `user:id,name`. `collaboratorsForList` eager-loads `user:id,name,email`.
- `authorizeListOwnership` and `authorizeListWrite` each do one indexed query (`UNIQUE(user_id, shopping_list_id)`). Efficient.
- Dashboard `GET /api/lists` adds one extra query for collaborated lists. Acceptable given low volume.

#### Architectural Compliance
- Follows approved technical design: separate `ListCollaborator` model, `ListCollaboratorService`, cascade revocation via `share_token_id`.
- Controllers are thin — authorization and business logic properly in controller private methods and service respectively.
- Frontend: API layer (`sharedListApi.js`, `shareApi.js`) cleanly separated from components.
- Routes match the API contract in `04-implementation-notes.md`.

### Recommendation
- [x] Request changes

### Required Changes

1. **[BLOCKING] `ListItemController::incrementQuantity` (line 83)** — Uses `authorizeListOwnership` (read access) but `incrementQuantity` is a write operation. A `read_only` collaborator can currently increment item quantities. Change to `authorizeListWrite`.

### Non-Blocking Suggestions

2. **`ListItemController` lines 92-128** — Extract shared collaborator lookup from `authorizeListOwnership`/`authorizeListWrite` to reduce duplication.
3. **`ShoppingListService` line 18, `RegistrationService` line 18** — Replace manual `??= new ListCollaboratorService()` with proper DI constructor injection.
4. **Test coverage** — Add test for `incrementQuantity` with read_only collaborator (should return 403 after fix #1).

---

## Security Review: FEAT-SHARED-AUTH

### Summary
- **Status**: PASS WITH NOTES
- **Reviewer**: claude-security-reviewer
- **Date**: 2026-04-15

### Automated Gates

| Gate | Command | Result |
|------|---------|--------|
| Deps audit (PHP) | `composer audit` | PASS — No security vulnerability advisories found |
| Deps audit (JS) | `npm audit --omit=dev` | PASS — 0 vulnerabilities |
| Secret scan | `git ls-files \| grep -E '^\.env$'` | PASS — no .env tracked |
| Lockfile (PHP) | `test -f composer.lock` | PRESENT |
| Lockfile (JS) | `test -f package-lock.json` | PRESENT |

### OWASP Top 10 2021 Findings

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| A01 | Broken Access Control | PASS | Server-side auth on all endpoints. `authorizeListOwnership` checks owner OR collaborator. `authorizeListWrite` additionally checks `mode.allowsWrite()`. Owner-only endpoints (`collaborators`, `archive`, `delete`) use strict `authorizeOwnership`. IDOR prevented by scoped queries. `saveToAccount` checks `user_id !== owner` to prevent self-link. Unique constraint `(user_id, shopping_list_id)` prevents duplicate links. Cross-tenant test exists (`test_non_collaborator_cannot_access_list`). |
| A02 | Cryptographic Failures | N/A | No new crypto introduced. Share tokens use existing HMAC-SHA256 signing (`ShareTokenSigner`). JWT auth unchanged. No passwords stored in this feature. |
| A03 | Injection | PASS | All DB queries use Eloquent ORM with parameterized bindings. No `DB::raw`, no string concatenation in queries. Frontend uses JSX auto-escaping (`{}`). No `dangerouslySetInnerHTML`. |
| A04 | Insecure Design | PASS | Trust boundaries documented in tech design. Business limits enforced: owner can't self-link (409), unauthenticated can't save (401). Mode fixed at link time — not re-derived from token on each request. Cascade revocation prevents orphaned access. |
| A05 | Security Misconfiguration | N/A | No new config surfaces. Existing middleware stack unchanged. |
| A06 | Vulnerable Components | PASS | `composer audit` and `npm audit` clean. Lockfiles present and committed. |
| A07 | Auth Failures | PASS | JWT required for save endpoint (enforced server-side via `auth('api')->user()`). Save-status gracefully handles unauthenticated (returns `authenticated: false`). No new session or password flows. |
| A08 | Integrity Failures | N/A | No deserialization of user input. No webhooks. JWT algorithm pinned by existing Tymon\JWTAuth config. |
| A09 | Logging & Monitoring | PASS | Activity logging exists via `ListActivityLog`. New collaborator actions inherit existing logging through `ListItemService` context. |
| A10 | SSRF | N/A | No outbound HTTP requests in this feature. |

### OWASP LLM Top 10 v2 (2025)

N/A — This feature has no AI surface. No LLM calls, no prompts, no agent behavior.

### Cross-Cutting

- **Idempotency**: PASS — `ListCollaborator::updateOrCreate` with unique constraint `(user_id, shopping_list_id)` ensures idempotent saves. Test `test_save_is_idempotent` validates. Duplicate POST returns 201 with same result, no side effects.
- **Rate Limiting**: PASS — All shared endpoints under `throttle:60,1` middleware. Save endpoint included in this group.
- **Transactions**: PASS — `linkUser` wrapped in `DB::transaction`. `revoke` extends existing transaction to cascade-delete collaborators. `linkRetroactive` wrapped in transaction with `firstOrCreate`.

### Required Changes

*None — all blocking issues from code review (incrementQuantity permission) already fixed.*

### Recommendation
- [x] Approve with notes (Low only)

### Notes / Tech Debt

1. **Low — A01**: `collaboratorsForList` exposes collaborator emails to list owner. Acceptable for current use case (owner needs to identify collaborators), but consider masking emails if the feature is extended to non-owner viewers in the future.
2. **Low — A09**: No dedicated log entry for "user X saved list Y to their account" event. The link is recorded in DB, but an explicit activity log entry would improve audit trail for the owner.

---

## Test Gate: FEAT-SHARED-AUTH

### Result
- **Status**: FAIL
- **Date**: 2026-04-15
- **Stack**: Laravel

### Test Execution

| Metric | Value |
|--------|-------|
| Tests Run | Yes |
| Total Tests | 630 (full suite), 18 feature-specific |
| Passing | 630 |
| Failing | 0 |

### Acceptance Criteria Coverage

| AC ID | Description | Test | Status |
|-------|-------------|------|--------|
| AC-1 | Boton "Guardar en mis listas" | `test_authenticated_user_can_save_shared_list` | Covered |
| AC-2 | Vinculacion exitosa + save status | `test_save_status_returns_linked_state`, `test_save_status_detects_owner`, `test_save_status_unauthenticated` | Covered |
| AC-3 | Dashboard muestra listas colaboradas | `test_lists_index_includes_collaborated` | Covered |
| AC-4 | Acceso directo desde dashboard | `test_collaborator_can_read_items`, `test_collaborator_edit_can_add_items` | Covered |
| AC-5 | Revocacion en cascada | `test_revoking_token_removes_collaborators` | Covered |
| AC-6 | Panel de colaboradores (propietario) | `test_owner_can_list_collaborators`, `test_non_owner_cannot_list_collaborators` | Covered |
| AC-7 | Vinculacion retroactiva al registrarse | MISSING | **Missing** |
| AC-8 | Proteccion de duplicados | `test_save_is_idempotent` | Covered |
| AC-9 | Propietario no se auto-vincula | `test_owner_cannot_save_own_list` | Covered |
| AC-10 | Permisos respetados | `test_collaborator_read_only_cannot_add_items`, `test_read_only_collaborator_cannot_delete_items`, `test_read_only_collaborator_cannot_increment_quantity` | Covered |

### Path Coverage Matrix

| Path Type | Required | Found | Status | Notes |
|-----------|----------|-------|--------|-------|
| Happy Path | YES | 7 | OK | Save, save-status, dashboard, read items, add items, list collaborators, read-only save |
| Failure Path | YES | 4 | OK | Auth required (401), owner self-save (409), non-collaborator access (403), non-owner list collaborators (403) |
| Edge Cases | YES | 2 | MISSING | Idempotent save covered. Missing: retroactive linking with empty sessionUuids, revoked token sessions, owner's own sessions |
| Security Path | YES | 5 | OK | Unauthenticated save (401), read-only cannot add (403), read-only cannot delete (403), read-only cannot increment (403), non-collaborator cannot access (403) |

### Database Test Configuration

| Check | Status | Notes |
|-------|--------|-------|
| Transaction wrapping | YES | `use DatabaseTransactions` in test class |
| Real database (not SQLite) | YES | `phpunit.xml`: `DB_CONNECTION=mysql`, `DB_DATABASE=superia` |
| Test isolation | YES | Transactions rollback after each test |

### Security Tests

| Category | Tests Found | Status |
|----------|-------------|--------|
| Authentication | 2 | OK — `test_save_requires_authentication`, `test_save_status_unauthenticated` |
| Authorization | 5 | OK — owner self-save blocked, read-only write blocked (3 endpoints), non-owner collaborators listing blocked |
| Input validation | N/A | No user input beyond JWT + token param (both validated by middleware) |

### Missing Tests

1. **[AC-7] Retroactive linking — happy path**: Register with `session_uuids` that match existing `list_collaborator_sessions`. Verify `list_collaborators` rows created with correct `user_id`, `shopping_list_id`, `mode`, and `share_token_id`.
2. **[AC-7] Retroactive linking — empty sessionUuids**: Register with empty array. Verify no collaborators created, no errors.
3. **[AC-7] Retroactive linking — revoked token skipped**: Register with session UUID belonging to a revoked token. Verify no collaborator created for that list.
4. **[AC-7] Retroactive linking — owner's own list skipped**: Register user whose email matches a list owner, with session UUID for that list. Verify no self-link created.

### Configuration Issues
None.

### Verdict
**FAIL**: AC-7 (retroactive linking at registration) has zero test coverage. `ListCollaboratorService::linkRetroactive` is implemented but never exercised by any test. `RegistrationServiceTest::test_register_creates_user_and_updates_waitlist` calls `register()` without `sessionUuids`, and no integration test exists for `POST /api/auth/register` with `session_uuids`. The method contains conditional logic (skip revoked tokens, skip owner's own lists, `firstOrCreate` dedup) that is entirely untested.

Must fix before progression:
- [ ] Add test: retroactive linking happy path (register with valid session UUIDs → collaborators created)
- [ ] Add test: retroactive linking skips revoked tokens
- [ ] Add test: retroactive linking skips owner's own lists
- [ ] Add test: retroactive linking with empty array (no-op)
