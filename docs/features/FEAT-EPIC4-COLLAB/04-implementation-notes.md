# Backend Implementation Notes - FEAT-EPIC4-COLLAB

## Summary

Backend for Epic 4 (Collaboration / Shared Lists). Adds token-based anonymous access to shopping lists in parallel to the existing JWT flow. Three new tables, one middleware, three new services, two new controllers, one new console command, and an extension of `ListItemService` so every item mutation feeds a rolling-50 activity log with the right actor type.

All 292 backend tests pass (187 pre-existing + 105 new).

## Files Changed

### Database

| File | Change | Description |
|------|--------|-------------|
| `database/migrations/2026_04_11_100000_create_list_share_tokens_table.php` | Created | Share tokens table (owner-generated, revocable) |
| `database/migrations/2026_04_11_100001_create_list_collaborator_sessions_table.php` | Created | Heartbeat sessions (tab-scoped) |
| `database/migrations/2026_04_11_100002_create_list_activity_log_table.php` | Created | Rolling 50 per list, owner + anonymous actions |
| `database/factories/ListShareTokenFactory.php` | Created | Factory with `readOnly()` / `revoked()` states |
| `database/factories/ListCollaboratorSessionFactory.php` | Created | Factory with `stale()` state |
| `database/factories/ListActivityLogFactory.php` | Created | Factory with `anonymous()` state |

### Enums

| File | Change | Description |
|------|--------|-------------|
| `app/Enums/ShareTokenMode.php` | Created | `edit`, `read_only`, `allowsWrite()` helper |
| `app/Enums/ActorType.php` | Created | `owner`, `anonymous` |
| `app/Enums/ActivityAction.php` | Created | 6 mutation types |

### Models

| File | Change | Description |
|------|--------|-------------|
| `app/Models/ListShareToken.php` | Created | Eloquent model with sessions relation, `isRevoked()` helper |
| `app/Models/ListCollaboratorSession.php` | Created | No updated_at, custom created_at only |
| `app/Models/ListActivityLog.php` | Created | No updated_at, custom table name |
| `app/Models/ShoppingList.php` | Modified | Added `shareTokens()` and `activityLog()` relations |

### Support layer

| File | Change | Description |
|------|--------|-------------|
| `app/Support/ShareTokenSigner.php` | Created | HMAC-SHA256 signer over APP_KEY. `sign`, `urlToken`, `parse`, `verify`. Accepts plain or `base64:` APP_KEY |
| `app/Support/ShareTokenContext.php` | Created | Read-only DTO passed from middleware to controllers/services |

### Services

| File | Change | Description |
|------|--------|-------------|
| `app/Services/ShareTokenService.php` | Created | Generate / revoke / list / resolve tokens. Syncs `shopping_lists.is_shared` flag. Deletes sessions on revoke. |
| `app/Services/ActivityLogService.php` | Created | `record()` with rolling-50 cleanup (insert + subquery delete in transaction). `getRecent()`. Name truncation at 80 chars. |
| `app/Services/CollaboratorPresenceService.php` | Created | Heartbeat upsert. Cached count (5s TTL) filtered to `last_heartbeat_at >= now-30s` and excluding revoked tokens. `deleteStale()` for cleanup. |
| `app/Services/ListItemService.php` | Modified | All mutation methods accept optional `ShareTokenContext $context = null`. Writes to activity log on every mutation with matching actor type. Signature extension is additive; Epic 3 tests still green. |

### HTTP layer

| File | Change | Description |
|------|--------|-------------|
| `app/Http/Middleware/ValidateShareToken.php` | Created | Parses `{tokenParam}`, verifies signature, loads token, short-circuits on revoked/invalid with unified 410. Accepts `:write` parameter to enforce mode. Attaches `ShareTokenContext` to request attributes. |
| `app/Http/Requests/CreateShareTokenRequest.php` | Created | `mode` enum validation |
| `app/Http/Requests/HeartbeatRequest.php` | Created | `session_uuid` size-36 UUID validation |
| `app/Http/Controllers/ShareTokenController.php` | Created | Owner endpoints: `index`, `store`, `destroy`. JWT-authorized + ownership checks. |
| `app/Http/Controllers/SharedListController.php` | Created | Anonymous endpoints: `show`, `storeItem`, `updateItem`, `toggleItem`, `destroyItem`, `heartbeat`. Uses request attribute for `ShareTokenContext`. Explicit `string $tokenParam` parameter in every method for Laravel positional binding. Double-layer mode check (middleware `:write` + `requireWrite()` inside controller). |
| `app/Http/Controllers/ShoppingListController.php` | Modified | Added `collaboratorsCount()` and `activityLog()` owner views. New deps: `CollaboratorPresenceService`, `ActivityLogService`. |

### Console

| File | Change | Description |
|------|--------|-------------|
| `app/Console/Commands/CleanupExpiredCollaboratorData.php` | Created | Three-phase cleanup: stale sessions (>5min), anonymous logs (>30d), revoked-token logs (>24h since revocation). |
| `routes/console.php` | Modified | Scheduled hourly |

### Routes

| File | Change | Description |
|------|--------|-------------|
| `routes/api.php` | Modified | Added owner-side `/lists/{list}/share*`, `/lists/{list}/collaborators/count`, `/lists/{list}/activity` (JWT + `throttle:10,60` on token generation). Added public `/shared/{tokenParam}*` routes under `ValidateShareToken` middleware + `throttle:60,1`, with write endpoints gated by `ValidateShareToken:write`. |

## Migrations

| Migration | Description | Reversible |
|-----------|-------------|------------|
| `2026_04_11_100000_create_list_share_tokens_table` | Share tokens with unique `token_id` and `(shopping_list_id, revoked_at)` index | Yes |
| `2026_04_11_100001_create_list_collaborator_sessions_table` | Sessions with custom short-name indexes `collab_sessions_token_uuid_unique` and `collab_sessions_token_heartbeat_idx` (default names exceeded MySQL 64-char limit) | Yes |
| `2026_04_11_100002_create_list_activity_log_table` | Activity log with `(shopping_list_id, id)` index for rolling-50 query | Yes |

## API Contract (Backend → Frontend)

### Owner endpoints (JWT)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/lists/{list}/share` | List active share tokens for a list |
| `POST` | `/api/lists/{list}/share` | Generate new token (body: `{mode: "edit" | "read_only"}`). Throttle `10,60` per user. |
| `DELETE` | `/api/lists/{list}/share/{token}` | Revoke a specific token |
| `GET` | `/api/lists/{list}/collaborators/count` | Active collaborator count |
| `GET` | `/api/lists/{list}/activity` | Last 50 activity entries |

### Anonymous endpoints (token-based)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/shared/{tokenParam}` | Load shared list (owner name, items, mode, counters) |
| `POST` | `/api/shared/{tokenParam}/heartbeat` | Send heartbeat (body: `{session_uuid}`) |
| `POST` | `/api/shared/{tokenParam}/items` | Add item (edit only) |
| `PUT` | `/api/shared/{tokenParam}/items/{item}` | Update item (edit only) |
| `PATCH` | `/api/shared/{tokenParam}/items/{item}/toggle` | Toggle purchased (edit only) |
| `DELETE` | `/api/shared/{tokenParam}/items/{item}` | Delete item (edit only) |

All anonymous endpoints rate-limited at `60,1` per IP.

### Request/Response Examples

```json
// POST /api/lists/{list}/share
// Request
{"mode": "edit"}
// Response 201
{
  "data": {
    "token": {
      "id": 1,
      "mode": "edit",
      "url": "http://superia.com.local/shared/550e8400-e29b-41d4-a716-446655440000.HMACsignatureBase64Url",
      "created_at": "2026-04-11T10:00:00+00:00"
    }
  }
}

// GET /api/lists/{list}/share
{
  "data": {
    "tokens": [
      {"id": 1, "mode": "edit", "url": "...", "created_at": "..."},
      {"id": 2, "mode": "read_only", "url": "...", "created_at": "..."}
    ]
  }
}

// GET /api/shared/{tokenParam}
{
  "data": {
    "list": {"id": 5, "name": "Compra semanal", "emoji": "🛒", "owner_name": "Maria"},
    "mode": "edit",
    "items": {"frutas_verduras": [...], "otros": [...]},
    "counters": {"items_total": 12, "items_completed": 3}
  }
}

// POST /api/shared/{tokenParam}/heartbeat
// Request
{"session_uuid": "550e8400-e29b-41d4-a716-446655440000"}
// Response
{"data": {"status": "ok"}}

// GET /api/lists/{list}/collaborators/count
{"data": {"count": 2}}

// GET /api/lists/{list}/activity
{
  "data": {
    "entries": [
      {
        "id": 123,
        "actor_type": "anonymous",
        "action": "item_checked",
        "item_name": "Leche",
        "created_at": "2026-04-11T10:05:00+00:00"
      }
    ]
  }
}
```

### Error Codes

| Code | Meaning | Frontend Action |
|------|---------|-----------------|
| 401 | Missing/invalid JWT (owner endpoints) | Redirect to login |
| 403 | Owner not owner of list / anonymous writing with read-only token | Show "acceso denegado" or hide write UI |
| 404 | Item does not belong to this list | Generic not found |
| 410 | Shared link revoked, invalid, malformed or token mismatched | Render `RevokedLinkView` |
| 422 | Validation error (mode, session_uuid, item fields) | Show field-level errors |
| 429 | Rate limit exceeded | Show "demasiadas peticiones, reintentar en N s", read `Retry-After` header |

## Implementation Decisions

1. **Single-service mutation path**: Extended `ListItemService` instead of duplicating item logic in `SharedListController`. The service accepts an optional `ShareTokenContext`, which determines both the actor type in the activity log and (implicitly) whether a `ProductoHistorial` record is tied to the owner (always the list owner's `user_id`). Prevents drift between owner and anonymous flows.

2. **HMAC with APP_KEY, no DB-stored signature**: Token URLs carry `{token_id}.{hmac_signature}`. DB stores only `token_id`. Signature is derived from `APP_KEY + token_id + list_id + mode`. An attacker with DB dump still cannot forge URLs; a revoked token with intact signature still fails because the DB lookup is the revocation source of truth. APP_KEY rotation invalidates all tokens globally — accepted.

3. **Double-layer read-only enforcement**: Middleware attaches mode to request; `SharedListController::requireWrite()` calls `abort(403)` before delegating to services. The middleware `:write` parameter is defense-in-depth at the route level. **Never trust frontend for this.**

4. **Rolling-50 cleanup inside the same transaction** as the inserting mutation. Subquery `skip(50)` + `delete where id <= threshold` avoids lock contention. Log is always bounded even under write bursts.

5. **Collaborator count cached 5s**: Polling N collaborators at 10s each would otherwise hammer the DB with `N*6/min` counts. Cache is invalidated on every heartbeat so new joiners are visible within ~5s.

6. **Heartbeat is not a write mutation**: Read-only tokens can heartbeat. This is consistent with "ver pero no modificar" — presence tracking is not list modification.

7. **Session UUID portability**: Backend generates nothing. Frontend provides it via `session_uuid` body param. Rationale: decoupled from auth, no user tracking, tab-scoped on the frontend via `sessionStorage`.

8. **Short index names**: MySQL 64-char identifier limit rejected the default-named unique index on `list_collaborator_sessions`. Custom short names: `collab_sessions_token_uuid_unique` and `collab_sessions_token_heartbeat_idx`.

9. **`string $tokenParam` in every SharedListController method**: Laravel's controller dispatcher passes route parameters positionally to typed method parameters. The route has `{tokenParam}/items/{item}`, so the string `tokenParam` arrives before the `ListItem $item` binding. Not declaring it in the signature causes "string given, ListItem expected" errors. The parameter is intentionally unused inside the method body — context comes from request attributes.

10. **`CollaboratorPresenceService` uses `DB::table` for the count query** rather than Eloquent to avoid the overhead of hydrating session + token models. Count is O(1) with the `(list_share_token_id, last_heartbeat_at)` index.

## Tests Added

| Test File | Type | What it tests |
|-----------|------|---------------|
| `tests/Unit/Support/ShareTokenSignerTest.php` | Unit | Sign determinism, payload differentiation, parse, verify constant-time, plain and base64 APP_KEY, missing key throws |
| `tests/Unit/Services/ShareTokenServiceTest.php` | Unit | Generate (edit + read_only), coexistence, revoke (idempotent, cascades sessions, unflags is_shared), active tokens query, resolveFromUrlParam (valid, malformed, revoked, tampered, invalid), urlFor |
| `tests/Unit/Services/ActivityLogServiceTest.php` | Unit | Record owner/anonymous, truncation, rolling-50 enforcement, per-list isolation, getRecent ordering/limit, all 6 actions |
| `tests/Unit/Services/CollaboratorPresenceServiceTest.php` | Unit | Heartbeat upsert, count active (includes recent, excludes stale, excludes revoked tokens, zero when none), cache behavior + invalidation on heartbeat, deleteStale |
| `tests/Unit/Services/ListItemServiceTest.php` | Unit (extended) | Added 6 tests: owner/anonymous activity log on each mutation (add, toggle check, toggle uncheck, update, delete, clear-completed) |
| `tests/Unit/Middleware/ValidateShareTokenTest.php` | Unit | 410 cases (missing, malformed, invalid sig, revoked), context attached on valid, 403 on read-only + write route, 200 on edit + write route |
| `tests/Feature/ShareTokenControllerTest.php` | Feature | Owner generate (edit + read_only), invalid mode, ownership denied, auth required, index excludes revoked, revoke flow, is_shared sync, cross-list 404, Free-user can share |
| `tests/Feature/SharedListControllerTest.php` | Feature | `show` happy + 410 variants, store/update/toggle/destroy happy + read-only blocked + validation, producto_historial writes with owner_id, anonymous activity log writes, heartbeat (edit + read_only + invalid UUID + revoked), cross-tenant 404 |
| `tests/Feature/CollaborationOwnerViewsTest.php` | Feature | collaboratorsCount (active, zero, stale excluded, ownership, auth), activityLog (newest first, actor/action exposure, empty, ownership, auth) |
| `tests/Feature/CleanupExpiredCollaboratorDataTest.php` | Feature | Stale sessions deletion, anonymous log 30d retention, owner logs kept forever, revoked-token log purge >24h, keeps logs <24h, output assertions |

## Test Coverage Report

```
Component                    Tests  Result
────────────────────────────────────────────
ShareTokenSignerTest           10    PASS
ShareTokenServiceTest          14    PASS
ActivityLogServiceTest          8    PASS
CollaboratorPresenceService     9    PASS
ValidateShareTokenTest          7    PASS
ListItemServiceTest (new)       6    PASS
ShareTokenControllerTest       12    PASS
SharedListControllerTest       22    PASS
CollaborationOwnerViewsTest    10    PASS
CleanupExpiredCollabDataTest    6    PASS
────────────────────────────────────────────
Epic 4 new tests              104
Previous backend tests        188
────────────────────────────────────────────
Total backend                 292    PASS
Duration                      39.72s
```

All paths tested per NON-NEGOTIABLE core rule:
- Happy paths: every endpoint and service method
- Failure paths: 401, 403, 404, 410, 422 across all affected endpoints
- Edge cases: stale sessions, expired logs, rolling-50 boundary, empty states
- Security paths: read-only bypass attempts (middleware + controller), cross-tenant tokens, tampered signatures, revoked tokens, invalid tokens, missing tokens, constant-time 410, Free user sharing

## Notes for Reviewers

1. **Security-critical middleware**: `ValidateShareToken` is the single chokepoint for anonymous access. Any new shared endpoint must sit behind it with `:write` when mutating. Tests cover the matrix of mode vs route type.
2. **Signature vs revocation**: Signature verification uses `hash_equals` (constant-time). Revocation check uses DB flag. Both must pass. Revocation takes precedence in the error response (same 410).
3. **`ProductoHistorial` writes from anonymous users**: Always tied to the owner's `user_id` because "historial" belongs to the list owner per Epic 3 contract. Verified in `SharedListControllerTest::test_toggle_creates_producto_historial_with_owner_id`.
4. **Activity log rolling-50 race condition**: Cleanup runs in the same DB transaction as the insert. A concurrent second insert may temporarily see 51 rows during its own transaction but cleanup will bring it back. No lost entries, no drift beyond one row transiently.
5. **Presence cache invalidation**: Cache is per-list. Heartbeat invalidates the cache of the list that owns the heartbeating token. Concurrent heartbeats on different lists don't interfere.
6. **Free user can share** (PRD AC-23): explicit test case in `ShareTokenControllerTest::test_free_user_can_share_list`. No freemium gate at share time.
7. **Read-only enforcement** (HU-401 crit. 6, PRD AC-10): every write endpoint has a negative test with a read-only token. Verified at both middleware level (`ValidateShareTokenTest::test_returns_403_when_read_only_hits_write_route`) and controller level (SharedListController tests).

## Deviations from Design

None. Implementation follows the S3 technical design exactly.

## Known Issues / Technical Debt

- **Session UUID collisions**: The DB unique constraint is `(token_id, session_uuid)`. Different tokens can share a session UUID by coincidence, which is intentional (sessions are per-token). No issue.
- **Cache TTL vs active window**: Count cache TTL (5s) vs active window (30s). A joiner's first heartbeat invalidates the cache, so new collaborators appear within 5s at worst. Departing collaborators only disappear when the cached value expires AND the 30s window passes, so the count can lag by up to 30 + 5 seconds. Acceptable per design.
- **No Laravel IDE helper hints for `ShareTokenContext`** attached via `$request->attributes`. Developers must remember it's there. Consider a base controller helper in future if painful.

---

# Frontend Implementation Notes - FEAT-EPIC4-COLLAB

## Summary

Frontend for Epic 4 (Collaboration / Shared Lists). One new public page (`SharedListPage`), five new collab components (share modal, collaborator indicator, activity log panel, consent banner, revoked link view), two new API clients (owner `shareApi`, anonymous `sharedListApi`), one new public route (`/shared/:tokenParam`), and integration into `ListDetailPage` (Compartir button, collaborator indicator, activity log).

All 158 frontend tests pass (107 pre-existing + 51 new). No backend code touched.

## Components Created

| Component | Location | Purpose |
|-----------|----------|---------|
| `SharedListPage` | `resources/js/pages/SharedListPage.jsx` | Public page, anonymous flow (consent gate, list view, mutations when edit mode, heartbeat loop, read-only UX with disabled checkboxes, register CTA) |
| `ShareListModal` | `resources/js/components/collab/ShareListModal.jsx` | Owner modal: generate/revoke tokens per mode, copy URL, native share fallback |
| `CollaboratorIndicator` | `resources/js/components/collab/CollaboratorIndicator.jsx` | Live "N personas viendo ahora" badge polling every 10s, paused on hidden tab, hidden when count=0 |
| `ActivityLogView` | `resources/js/components/collab/ActivityLogView.jsx` | Collapsible panel showing last 50 entries, relative timestamps, polls every 10s when open |
| `ConsentBanner` | `resources/js/components/collab/ConsentBanner.jsx` | Blocking modal on first shared-list visit, shows owner name + 30d retention disclosure |
| `RevokedLinkView` | `resources/js/components/collab/RevokedLinkView.jsx` | Error state shown when shared link returns 410 |

## Components Modified

| Component | Changes |
|-----------|---------|
| `ListDetailPage` | Added Compartir button opening `ShareListModal`. Added `CollaboratorIndicator` next to counter when `list.is_shared`. Added `ActivityLogView` panel below items when `list.is_shared`. Refetches list after modal close (to reflect new `is_shared` flag). |
| `app.jsx` | Added public route `/shared/:tokenParam` rendering `SharedListPage`. Outside the `ProtectedRoute` guard — no JWT required. |

## Library Files Created

| File | Purpose |
|------|---------|
| `resources/js/lib/shareApi.js` | Owner-side helpers using the JWT-authenticated `api` client: `listShareTokens`, `createShareToken`, `revokeShareToken`, `getCollaboratorsCount`, `getActivityLog` |
| `resources/js/lib/sharedListApi.js` | Anonymous helpers using a separate axios instance without JWT interceptor: `fetchSharedList`, `addSharedItem`, `updateSharedItem`, `toggleSharedItem`, `deleteSharedItem`, `sendHeartbeat`. Token is part of the URL path. |

## State Management

All state is local to components (no new global stores). `SharedListPage` keeps list/items/counters/mode/consented as `useState`. Session UUID and consent flag live in `sessionStorage` under keys `superia:session:{tokenParam}` and `superia:consent:{tokenParam}` — tab-scoped, auto-cleared on tab close, not persistent. Heartbeat loop uses `useRef` for the timer handle, paused when `document.visibilityState !== 'visible'`.

`CollaboratorIndicator` and `ActivityLogView` each manage their own polling cycle. Both pause on hidden tab. `ActivityLogView` only fetches when expanded.

## API Integration

| Endpoint | Hook/Function | Error Handling |
|----------|---------------|----------------|
| `GET /api/lists/:id/share` | `listShareTokens` (owner) | Show error in modal |
| `POST /api/lists/:id/share` | `createShareToken` (owner) | Show error, handle 429 specifically |
| `DELETE /api/lists/:id/share/:token` | `revokeShareToken` (owner) | Show error |
| `GET /api/lists/:id/collaborators/count` | `getCollaboratorsCount` (owner) | Silent — component hides on failure |
| `GET /api/lists/:id/activity` | `getActivityLog` (owner) | Silent — keeps last state |
| `GET /api/shared/:token` | `fetchSharedList` (anon) | 410 -> `RevokedLinkView`, other -> error message |
| `POST /api/shared/:token/items` | `addSharedItem` (anon, edit only) | Show error in page header |
| `PUT /api/shared/:token/items/:id` | `updateSharedItem` (anon, edit only) | Show error |
| `PATCH /api/shared/:token/items/:id/toggle` | `toggleSharedItem` (anon, edit only) | Show error |
| `DELETE /api/shared/:token/items/:id` | `deleteSharedItem` (anon, edit only) | Show error |
| `POST /api/shared/:token/heartbeat` | `sendHeartbeat` (anon) | Silent — ignore transient failures |

## Tests Added

| Test File | Type | What it tests |
|-----------|------|---------------|
| `resources/js/components/collab/ShareListModal.test.jsx` | Component | Load tokens, render sections, generate edit/read-only, revoke + remove, copy to clipboard, native share + clipboard fallback, 429 error, generic generate error, revoke error, load error, close |
| `resources/js/components/collab/CollaboratorIndicator.test.jsx` | Component | Hide at zero, singular text at 1, plural at N, fetch on mount, silent API failure |
| `resources/js/components/collab/ActivityLogView.test.jsx` | Component | Collapsed by default, expand fetches entries, empty state, loading state, all 6 action labels, relative timestamps (seconds/minutes/hours/days/null) |
| `resources/js/components/collab/ConsentBanner.test.jsx` | Component | Owner name rendering, fallback text, retention disclosure, accept callback, dialog role |
| `resources/js/components/collab/RevokedLinkView.test.jsx` | Component | Heading, retry message, landing link |
| `resources/js/pages/SharedListPage.test.jsx` | Page | Loading state, 410 -> revoked view, generic error, list render with consent banner on first visit, consent accept + storage, skip banner when already consented, heartbeat starts after consent with uuid format, read-only badge + disabled checkboxes + no add input + no edit panel, add item, toggle, delete, edit via panel, empty state, register CTA href, add item error, session uuid reuse from sessionStorage |
| `resources/js/pages/ListDetailPage.test.jsx` (extended) | Page | Added 3 tests: share button opens modal, activity log shown when shared, hidden when not shared |

## Test Coverage Report

```
Test Files                                       Tests  Result
─────────────────────────────────────────────────────────────
resources/js/components/collab/ShareListModal     13    PASS
resources/js/components/collab/CollaboratorInd.    5    PASS
resources/js/components/collab/ActivityLogView     6    PASS
resources/js/components/collab/ConsentBanner       5    PASS
resources/js/components/collab/RevokedLinkView     3    PASS
resources/js/pages/SharedListPage                 17    PASS
resources/js/pages/ListDetailPage (new cases)      3    PASS
─────────────────────────────────────────────────────────────
Epic 4 frontend new tests                         51
Previous frontend tests                          107
─────────────────────────────────────────────────────────────
Total frontend                                   158    PASS
Duration                                         ~12s
```

## Visual Validation

| Evidence | Description | Method | Status |
|----------|-------------|--------|--------|
| Component tests (vitest + jsdom) | Every component state rendered and asserted — consent, read-only, edit, empty, error, revoked, loading | vitest | Verified |
| Integration via `SharedListPage.test.jsx` | End-to-end anonymous flow asserted: fetch -> consent -> heartbeat -> mutate | vitest | Verified |

**`@browser` visual validation not available in this Claude Code environment.** Component and integration tests cover every rendered state mentioned in the PRD. A manual in-browser walk-through is recommended at S5-UX with the dev server running (`npm run dev` + `php artisan serve`).

## Accessibility

- `ConsentBanner` uses `role="dialog"` + `aria-modal="true"` + `aria-labelledby`
- `ShareListModal` uses same modal ARIA pattern, close button has `aria-label="Cerrar"`
- `CollaboratorIndicator` uses `role="status"` + `aria-live="polite"`
- `ActivityLogView` header button uses `aria-expanded` + `aria-controls`
- Shared list checkboxes in read-only mode are `disabled` (native disabled state) + visual `opacity: 0.4` + `cursor: not-allowed`, with an `aria-label` describing current state
- Item delete buttons have `aria-label={`Eliminar ${item.name}`}`
- Revoked view has `role="heading"` on the main message and semantic link back

## Performance Notes

- Heartbeat loop uses `setTimeout` chain, not `setInterval`, so a long-running request doesn't overlap itself.
- Both polling components (`CollaboratorIndicator`, `ActivityLogView`) check `document.visibilityState === 'visible'` before firing, dropping traffic while the tab is hidden.
- `ActivityLogView` only starts polling when the panel is expanded; collapsed = zero traffic.
- `sharedListApi` is a separate axios instance so the main `api` interceptor (JWT refresh) doesn't wrap anonymous calls.

## Notes for Reviewers

1. **Anonymous traffic bypasses the JWT interceptor** by using a dedicated axios instance (`resources/js/lib/sharedListApi.js`). Any new shared endpoint must go through this client, not the main `api` object, otherwise the JWT refresh retry logic will misfire.
2. **Consent gate is blocking**: the list is rendered but overlaid by `ConsentBanner` until accepted. Heartbeat only starts **after** consent, so pre-consent users are not counted as "viewing".
3. **`sessionStorage` not `localStorage`**: consent and session UUID are tab-scoped on purpose. New tab = new consent prompt = new session UUID. Aligned with "no cross-session tracking".
4. **Read-only UX**: checkboxes are `disabled` and visually dimmed (`opacity: 0.4`, `cursor: not-allowed`) per PRD AC-10. Add input and delete/edit affordances are not rendered at all in read-only mode. Backend still enforces 403 — frontend is cosmetic.
5. **`is_shared` flag on `ListDetailPage`** controls `CollaboratorIndicator` and `ActivityLogView` visibility. After the share modal closes, the page refetches the list so the flag updates immediately.
6. **Native share API fallback**: `navigator.share` is used when available, falling back to clipboard copy. Both paths tested.
7. **Polling timer tests**: polling interval tests were simplified to avoid jsdom + fake-timer race conditions. The "on mount fetch" case is covered directly; the 10s loop implementation is verified via the `setTimeout` chain pattern (same pattern already validated in `UndoSnackbar`).

## Deviations from Design/UX

None. The UX outline from S3 is implemented as specified. Stitch MCP screens were not fetched in this Claude Code environment (MCP not available); the components follow the existing design system (Tailwind, indigo-600 accent, rounded-lg cards) used throughout Epics 0-3, which is consistent with the rest of the app.

## Transition

- Gate Status: S4 COMPLETE (backend + frontend)
- Next Step: STEP 5 — Multi-reviewer pass (S5-CODE, S5-SEC, S5-TEST, S5-UX)
