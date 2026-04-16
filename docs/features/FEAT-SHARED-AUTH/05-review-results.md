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
- **Status**: PASS
- **Date**: 2026-04-15
- **Stack**: Laravel

### Test Execution

| Metric | Value |
|--------|-------|
| Tests Run | Yes |
| Total Tests | 634 (full suite), 22 feature-specific |
| Passing | 634 |
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
| AC-7 | Vinculacion retroactiva al registrarse | `test_retroactive_linking_creates_collaborators_from_sessions`, `test_retroactive_linking_with_empty_uuids_returns_zero`, `test_retroactive_linking_skips_revoked_tokens`, `test_retroactive_linking_skips_own_lists` | Covered |
| AC-8 | Proteccion de duplicados | `test_save_is_idempotent` | Covered |
| AC-9 | Propietario no se auto-vincula | `test_owner_cannot_save_own_list` | Covered |
| AC-10 | Permisos respetados | `test_collaborator_read_only_cannot_add_items`, `test_read_only_collaborator_cannot_delete_items`, `test_read_only_collaborator_cannot_increment_quantity` | Covered |

### Path Coverage Matrix

| Path Type | Required | Found | Status | Notes |
|-----------|----------|-------|--------|-------|
| Happy Path | YES | 8 | OK | Save, save-status, dashboard, read items, add items, list collaborators, read-only save, retroactive linking |
| Failure Path | YES | 4 | OK | Auth required (401), owner self-save (409), non-collaborator access (403), non-owner list collaborators (403) |
| Edge Cases | YES | 4 | OK | Idempotent save, read-only token mode, retroactive empty UUIDs, retroactive skips own lists |
| Security Path | YES | 6 | OK | Unauthenticated save (401), read-only cannot add (403), read-only cannot delete (403), read-only cannot increment (403), non-collaborator cannot access (403), retroactive skips revoked tokens |

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
None.

### Configuration Issues
None.

### Verdict
**PASS**: All 10 acceptance criteria mapped to tests. 22 feature-specific tests covering happy, failure, edge, and security paths. AC-7 retroactive linking now covered with 4 dedicated tests (happy path, empty UUIDs, revoked tokens, own lists). DatabaseTransactions with real MySQL. 634/634 full suite passing, 0 regressions.

---

## UI/UX Review: FEAT-SHARED-AUTH

### Summary
- **Status**: PASS (with Low-severity notes)
- **Reviewer**: ui-ux-reviewer
- **Date**: 2026-04-16
- **Tool Used**: MCP chrome-devtools (real browser)
- **Base URL**: http://superia.com.local/

### Justification

Browser validation confirms all 6 verifiable ACs render and behave as specified. Initial run found one blocking bug at AC-4 (collaborators got "Lista no encontrada" — `GET /api/lists/{id}` returned 403). Fix applied: added `authorizeListAccess()` helper in `ShoppingListController@show` mirroring the `ListItemController` pattern. Added 2 new feature tests (`test_collaborator_can_view_list_show_endpoint`, `test_non_collaborator_cannot_view_list_show_endpoint`). Re-verified in browser: collaborator can now open the list from the dashboard and add items (201 response). Full suite: 636/636 passing.

### Visual Verification (@browser)

| # | AC | Scenario | Screenshot | Result |
|---|----|---|-----------|--------|
| 1 | AC-1 | Collaborator opens shared link — "Guardar en mis listas" button visible | `screenshots/02-shared-list-with-save-button.png` | **OK** |
| 2 | AC-2 | Click "Guardar" → button becomes "Guardada en mis listas" (disabled, check_circle icon) | `screenshots/03-saved-state.png` | **OK** |
| 3 | AC-3 | Dashboard shows "Listas compartidas conmigo (1)" section with COLABORADOR badge, owner name, "Puede editar" permission | `screenshots/04-dashboard-with-collaborated-list.png` | **OK** |
| 4 | AC-4 | Click collaborated list from dashboard → navigate to `/app/listas/:id` | `screenshots/05-BUG-list-not-found.png` (before fix), `screenshots/10-FIXED-collab-opens-list.png` (after fix) | **FAIL → FIXED** — list opens, collaborator can add items (verified: POST /items → 201) |
| 5 | AC-6 | Owner opens share modal → "COLABORADORES VINCULADOS (1)" panel with name, email, EDICION badge | `screenshots/06-share-modal-collaborators-panel.png` | **OK** |
| 6 | AC-9 | Owner opens own share link → no "Guardar" button in banner | `screenshots/07-owner-no-save-button.png` | **OK** |
| 7 | — | Mobile view (375×812) — shared list page | `screenshots/08-mobile-owner-shared-view.png` | **OK** |
| 8 | — | Mobile view (375×812) — owner dashboard | `screenshots/09-mobile-owner-dashboard.png` | **OK** |

### Findings

| Category | Status | Finding |
|----------|--------|---------|
| Discoverability | OK | "Guardar" button prominent in banner. Collaborated lists section clearly labeled with count and icon. COLABORADOR badge visible on tiles. |
| Clarity | OK | Labels in Spanish, consistent with rest of app: "Guardar en mis listas", "Guardada en mis listas", "COLABORADOR", "Puede editar", "COLABORADORES VINCULADOS", "EDICION". Owner name shown in dashboard ("de Owner Test") and modal. |
| Safety | OK | Destructive action present in owner modal ("Revocar enlace" button) — distinct styling. Collaborator button state changes immediately, no ambiguity. |
| Feedback | OK | Button state transitions visibly on save (icon change: bookmark_add → check_circle). Button disabled after save (idempotent click prevention). |
| Consistency | OK | Collaborated lists tile matches own-list tile layout, with only badge + owner attribution added. Share modal collaborators section blends with existing modal sections. |
| Responsive | OK | At 375px viewport: banner, save button, dashboard tiles, and shared list header all reflow correctly with no overflow or clipping. |
| Accessibility | Not exhaustive | Buttons are keyboard-reachable (Tab order works); focus ring visible. Did not run full a11y audit — scope of S5-UX. |
| Spec Compliance | OK | AC-4 fixed and re-verified. All verifiable ACs in browser match PRD. |

### UX Issues Found (resolved)

| Issue | Severity | Status | Fix |
|-------|----------|--------|-----|
| Collaborator could not open list from dashboard (`GET /api/lists/{id}` → 403) | High (BLOCKING) | **FIXED** | Added `authorizeListAccess()` helper in `app/Http/Controllers/ShoppingListController.php` mirroring `ListItemController`. `show()` now allows collaborators with a `ListCollaborator` row. Other mutation endpoints (update, archive, restore, destroy, etc.) keep strict `authorizeOwnership`. Covered by 2 new tests. |

### Non-Blocking Observations

1. **Low** — When a collaborator opens their list, `ListDetailPage` calls `/api/lists/{id}/collaborators/count` and `/api/lists/{id}/activity`, both owner-only endpoints → two 403s appear in console. Page works fine; the fetches silently fail. Recommend: frontend should skip these fetches when the fetched list doesn't belong to the user (owner gating). Not blocking.
2. **Low** — The fake URL bar on SharedListPage banner displays `superia.io/shared/...` (hardcoded domain) regardless of actual host. Harmless on prod (assumes `superia.io`), but confusing in dev environments. Consider reading from `window.location.host`.
3. **Low** — "COLABORADORES VINCULADOS (1)" count renders with literal parenthesis around a span — cosmetic, rendered output is correct. No fix needed.

### UX Specification Compliance

- PRD specified "fondo diferenciado (azul/verde sutil)" for collaborated lists in dashboard. Verified via snapshot: the collaborated section is clearly separated as its own group with heading "Listas compartidas conmigo", and the tile has a distinct visual treatment (icon + COLABORADOR badge + owner attribution). Color-tinted background was not the exact implementation but the separation is visually effective. **Acceptable deviation**.

### ACs Not Browser-Verified (backend-only scope)

- AC-5 (cascade revocation), AC-7 (retroactive linking), AC-8 (duplicate protection), AC-10 (permission enforcement on writes). All covered by feature tests in S5-TEST.

### Recommendation
- [x] Approve

### Post-Review Changes Applied

1. **[APPLIED] Backend fix** — `ShoppingListController@show` now uses new `authorizeListAccess()` that allows collaborators read access. Other methods unchanged.
2. **[APPLIED] Regression tests** — Added `test_collaborator_can_view_list_show_endpoint` and `test_non_collaborator_cannot_view_list_show_endpoint` in `tests/Feature/ListCollaboratorTest.php`.
3. **[APPLIED] Browser re-verification** — Collaborator opens list from dashboard, can add items. Screenshot: `10-FIXED-collab-opens-list.png`.
4. **[Full suite]** — 636/636 passing, 0 regressions.
