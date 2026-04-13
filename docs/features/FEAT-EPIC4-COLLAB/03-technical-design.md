# Technical Design: FEAT-EPIC4-COLLAB

## Overview

Three new tables (`list_share_tokens`, `list_collaborator_sessions`, `list_activity_log`) plus a token-validation middleware to enable anonymous access to shared shopping lists. The feature adds a parallel authorization path to the existing JWT one: authenticated owners use `auth:api`, anonymous collaborators use `ValidateShareToken`, and both eventually call the same `ListItemService` — which is extended to accept an optional `ShareTokenContext` so it can record activity with the right actor type without duplicating item mutation logic.

Tokens are generated as random 32-byte payloads base64url-encoded for the URL. The URL carries `{token_id}.{signature}` where `signature = HMAC-SHA256(APP_KEY, token_id || list_id || mode)`. The DB stores only `token_id`, `list_id`, `mode`, `revoked_at` — never the signature — so the signature acts as a proof-of-URL-integrity while revocation stays DB-driven. Rate limiting is per-IP via Laravel's `throttle` middleware at 60 req/min. A scheduled `CleanupExpiredCollaboratorData` command enforces the RGPD 30-day retention for anonymous data and purges sessions/logs tied to revoked tokens.

Presence ("N personas viendo ahora") is solved with a heartbeat-upsert pattern: each open tab sends `POST /shared/{token}/heartbeat` every 10s with a `session_uuid` generated on first load (stored in `sessionStorage`, tab-scoped). Active count queries filter sessions with `last_heartbeat_at >= now - 30s`. No identities are stored — just counts. Activity log is rolling 50 per list, enforced via insert + delete-oldest in the same transaction.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | Share token, session, activity log models; enums for mode/action/actor | `App\Models\ListShareToken`, `App\Models\ListCollaboratorSession`, `App\Models\ListActivityLog`, `App\Enums\ShareTokenMode`, `App\Enums\ActivityAction`, `App\Enums\ActorType` |
| Services | Token lifecycle, presence tracking, activity logging, item mutation extended for anonymous actors | `App\Services\ShareTokenService`, `App\Services\CollaboratorPresenceService`, `App\Services\ActivityLogService`, `App\Services\ListItemService` (extended) |
| Infrastructure | HMAC signing, rate limiting, cleanup scheduling | `App\Support\ShareTokenSigner`, `App\Http\Middleware\ValidateShareToken`, `App\Console\Commands\CleanupExpiredCollaboratorData` |
| Controllers/API | Thin HTTP layer, delegate to services, constant-time error responses | `App\Http\Controllers\ShareTokenController` (owner), `App\Http\Controllers\SharedListController` (anonymous), new methods on `ShoppingListController` for activity/count |
| Frontend | Share modal, shared-list page, consent banner, collaborator indicator, activity log view, revoked-link view | `pages/SharedListPage.jsx`, `components/collab/ShareListModal.jsx`, `components/collab/CollaboratorIndicator.jsx`, `components/collab/ActivityLogView.jsx`, `components/collab/ConsentBanner.jsx`, `components/collab/RevokedLinkView.jsx` |

### Data Flow

#### Owner generates share token
```
1. POST /api/lists/{listId}/share { mode: "edit" | "read_only" }
2. ShareTokenController -> authorizeListOwnership
3. ShareTokenService::generate(list, mode) {
     token_id = Str::uuid()
     record = ListShareToken::create({ shopping_list_id, token_id, mode })
     signature = ShareTokenSigner::sign(token_id, list.id, mode)
     url = APP_URL . "/shared/" . token_id . "." . signature
     ShoppingList::update({ is_shared: true }) // if not already
   }
4. Return 201 { token: { id, mode, url, created_at } }
```

#### Owner revokes token
```
1. DELETE /api/lists/{listId}/share/{tokenRecordId}
2. ShareTokenController -> authorizeListOwnership -> authorizeTokenBelongsToList
3. ShareTokenService::revoke(token) {
     token.update({ revoked_at: now() })
     // delete active sessions tied to this token
     ListCollaboratorSession::where('list_share_token_id', token.id)->delete()
     // mark is_shared = exists(non-revoked token for this list)
     syncIsShared(list)
   }
4. Return 204
```

#### Anonymous visit (first time)
```
1. GET /shared/{token_id}.{signature}  (frontend route, not API)
2. Frontend renders SharedListPage shell
3. SharedListPage calls GET /api/shared/{token_id}.{signature}
4. ValidateShareToken middleware {
     [token_id, signature] = explode('.', $param)
     token = ListShareToken::where('token_id', $token_id)->first()
     if (!token || $token->revoked_at) -> return 410 (revoked/invalid, constant-time)
     expectedSignature = ShareTokenSigner::sign(token_id, token.shopping_list_id, token.mode)
     if (!hash_equals(expectedSignature, $signature)) -> return 410
     $request->attributes->set('shareTokenContext', ShareTokenContext { token, list, mode })
   }
5. SharedListController::show(request) {
     ctx = request->shareTokenContext
     return {
       list: { id, name, owner_name },
       items: listItemService->getItemsForList(ctx.list),
       mode: ctx.mode
     }
   }
6. Frontend checks sessionStorage for consent flag
7. If not consented: show ConsentBanner (blocks list interaction)
8. On "Continuar": sessionStorage.setItem('consent:{token_id}', '1')
9. Frontend generates session_uuid (crypto.randomUUID()) -> sessionStorage
10. Frontend starts heartbeat loop (every 10s)
```

#### Anonymous mark item as purchased
```
1. PATCH /api/shared/{token_id}.{signature}/items/{itemId}/toggle
2. ValidateShareToken middleware -> attaches context
3. SharedListController::toggleItem -> checks ctx.mode (both modes allow toggle)
4. ListItemService::togglePurchased(item, ownerUserId, listId, ctx?) {
     DB::transaction {
       item.toggle()
       if (purchased) ProductoHistorial::create({ user_id: list.owner, ... })
       syncCounters(list)
       ActivityLogService::record(list, actorType, action, item.name, ctx?.tokenId)
     }
   }
5. Return updated item + counters
```

#### Anonymous add item (edit token only)
```
1. POST /api/shared/{token_id}.{signature}/items { name, quantity?, ... }
2. ValidateShareToken middleware
3. SharedListController::addItem -> if ctx.mode == read_only -> abort 403
4. ListItemService::create(list, data, ctx) {
     DB::transaction {
       item = list.items.create(data)
       syncCounters(list)
       ActivityLogService::record(list, anonymous, item_added, item.name, ctx.tokenId)
     }
   }
5. Return 201
```

#### Heartbeat
```
1. POST /api/shared/{token_id}.{signature}/heartbeat { session_uuid }
2. ValidateShareToken middleware
3. SharedListController::heartbeat ->
   CollaboratorPresenceService::heartbeat(ctx.token, session_uuid) {
     ListCollaboratorSession::updateOrCreate(
       { list_share_token_id: token.id, session_uuid },
       { last_heartbeat_at: now() }
     )
   }
4. Return 204
```

#### Owner polls collaborators count
```
1. GET /api/lists/{listId}/collaborators/count
2. ShoppingListController::collaboratorsCount -> authorizeListOwnership
3. CollaboratorPresenceService::countActive(list) {
     return DB::query:
       SELECT COUNT(DISTINCT s.id)
       FROM list_collaborator_sessions s
       JOIN list_share_tokens t ON s.list_share_token_id = t.id
       WHERE t.shopping_list_id = ?
         AND t.revoked_at IS NULL
         AND s.last_heartbeat_at >= NOW() - INTERVAL 30 SECOND
   }
4. Cache result for 5 seconds keyed by list_id to absorb polling storm from multiple collaborators
5. Return { count: N }
```

#### Owner polls activity log
```
1. GET /api/lists/{listId}/activity
2. ShoppingListController::activityLog -> authorizeListOwnership
3. ActivityLogService::getRecent(list, 50) {
     return ListActivityLog::where('shopping_list_id', list.id)
       ->orderByDesc('created_at')
       ->limit(50)
       ->get()
   }
4. Return { entries: [...] }
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| Generate token | Insert token + sync `is_shared` | Atomic flag update |
| Revoke token | Update token + delete sessions + sync `is_shared` | Atomic state transition |
| Item mutation (owner or anonymous) | Item write + counter sync + activity log insert + rolling-50 cleanup | Activity log consistent with item state |
| Activity log rolling cleanup | Insert new + delete oldest (if count > 50) | Never exceed 50 per list |
| Heartbeat upsert | Single-row upsert, no transaction needed | Idempotent by unique key |
| Cleanup command | Per-statement, no cross-statement consistency needed | Purges are independent |

### Token Signing Scheme

```php
namespace App\Support;

use Illuminate\Support\Facades\Config;

class ShareTokenSigner
{
    public static function sign(string $tokenId, int $listId, string $mode): string
    {
        $payload = $tokenId . '|' . $listId . '|' . $mode;
        $key = Config::get('app.key'); // base64:... from APP_KEY
        $rawKey = base64_decode(substr($key, 7));
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $rawKey, true)), '+/', '-_'), '=');
    }

    public static function urlToken(string $tokenId, int $listId, string $mode): string
    {
        return $tokenId . '.' . self::sign($tokenId, $listId, $mode);
    }
}
```

Verification in middleware uses `hash_equals()` (constant-time). On any parse/lookup/signature failure, middleware returns a unified 410 response with a fixed delay buffer to eliminate timing oracles.

### Activity Log Rolling Cleanup

```php
// Inside DB::transaction within ActivityLogService::record
$log = ListActivityLog::create($data);

$threshold = ListActivityLog::where('shopping_list_id', $listId)
    ->orderByDesc('id')
    ->skip(50)
    ->value('id');

if ($threshold !== null) {
    ListActivityLog::where('shopping_list_id', $listId)
        ->where('id', '<=', $threshold)
        ->delete();
}
```

Subquery-based delete avoids full-table scans. Indexed on `(shopping_list_id, id DESC)`.

## Data Model

### New Table: `list_share_tokens`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | Internal reference (used in `DELETE /share/{id}`) |
| `shopping_list_id` | foreignId | FK shopping_lists.id, CASCADE, index | Parent list |
| `token_id` | uuid | UNIQUE, indexed | Public identifier in URL |
| `mode` | enum | `edit`, `read_only`, NOT NULL | Permission level |
| `revoked_at` | timestamp | NULLABLE | Revocation marker |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

Indexes: `UNIQUE(token_id)`, `(shopping_list_id, revoked_at)` for "active tokens per list".

### New Table: `list_collaborator_sessions`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | |
| `list_share_token_id` | foreignId | FK list_share_tokens.id, CASCADE, index | Session belongs to a specific token |
| `session_uuid` | char(36) | NOT NULL | Tab-scoped identifier, frontend-generated |
| `last_heartbeat_at` | timestamp | NOT NULL, index | For activity check and cleanup |
| `created_at` | timestamp | | |

Indexes: `UNIQUE(list_share_token_id, session_uuid)`, `(list_share_token_id, last_heartbeat_at)`.

### New Table: `list_activity_log`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | |
| `shopping_list_id` | foreignId | FK shopping_lists.id, CASCADE, index | Parent list |
| `list_share_token_id` | foreignId | FK list_share_tokens.id, CASCADE, NULLABLE, index | Null for owner-authored entries |
| `actor_type` | enum | `owner`, `anonymous`, NOT NULL | Distinguishes owner vs anonymous |
| `action` | enum | `item_added`, `item_checked`, `item_unchecked`, `item_edited`, `item_deleted`, `list_cleared`, NOT NULL | Logged action |
| `item_name` | string(80) | NOT NULL | Snapshot at time of action |
| `created_at` | timestamp | NOT NULL, index (DESC) | For ordering |

Indexes: `(shopping_list_id, id DESC)` for latest-50 query; `(list_share_token_id)` for purge-on-revoke.

### Enums

```php
// App\Enums\ShareTokenMode
enum ShareTokenMode: string {
    case Edit = 'edit';
    case ReadOnly = 'read_only';
}

// App\Enums\ActorType
enum ActorType: string {
    case Owner = 'owner';
    case Anonymous = 'anonymous';
}

// App\Enums\ActivityAction
enum ActivityAction: string {
    case ItemAdded = 'item_added';
    case ItemChecked = 'item_checked';
    case ItemUnchecked = 'item_unchecked';
    case ItemEdited = 'item_edited';
    case ItemDeleted = 'item_deleted';
    case ListCleared = 'list_cleared';
}
```

### Migrations (3)

1. `xxxx_create_list_share_tokens_table.php` — reversible
2. `xxxx_create_list_collaborator_sessions_table.php` — reversible
3. `xxxx_create_list_activity_log_table.php` — reversible

No changes to existing tables (Epic 2's `is_shared` boolean on `shopping_lists` is reused, just flipped by `ShareTokenService`).

### API Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/lists/{list}/share` | POST | JWT | Generate share token (body: `{mode}`) |
| `/api/lists/{list}/share` | GET | JWT | List active tokens for the list |
| `/api/lists/{list}/share/{token}` | DELETE | JWT | Revoke specific token |
| `/api/lists/{list}/collaborators/count` | GET | JWT | Active collaborators count |
| `/api/lists/{list}/activity` | GET | JWT | Last 50 activity log entries |
| `/api/shared/{tokenParam}` | GET | ValidateShareToken | Load shared list |
| `/api/shared/{tokenParam}/items` | POST | ValidateShareToken + edit mode | Add item |
| `/api/shared/{tokenParam}/items/{item}` | PUT | ValidateShareToken + edit mode | Edit item |
| `/api/shared/{tokenParam}/items/{item}/toggle` | PATCH | ValidateShareToken + edit mode | Toggle purchased |
| `/api/shared/{tokenParam}/items/{item}` | DELETE | ValidateShareToken + edit mode | Delete item |
| `/api/shared/{tokenParam}/heartbeat` | POST | ValidateShareToken | Heartbeat with `{session_uuid}` |

> **Read-only enforcement**: Any write endpoint (POST/PUT/PATCH/DELETE under `/api/shared/{tokenParam}/items*`) requires `ctx.mode == edit`. Enforcement is double-layered: middleware flags `requires_write`, and `SharedListController` short-circuits with 403 before delegating to the service. `GET /api/shared/{tokenParam}` works for both modes. `POST .../heartbeat` works for both (not a mutation to user data). Canonical source: HU-401 criterion 6. Backend MUST validate token mode on every write, never rely on frontend enforcement.

### API Response Format

```json
// POST /api/lists/{id}/share (201)
{
  "data": {
    "token": {
      "id": 1,
      "mode": "edit",
      "url": "https://superia.com.local/shared/550e8400-e29b-41d4-a716-446655440000.dGVzdFNpZ25hdHVyZQ",
      "created_at": "2026-04-11T10:00:00Z"
    }
  }
}

// GET /api/lists/{id}/share
{
  "data": {
    "tokens": [
      { "id": 1, "mode": "edit", "url": "...", "created_at": "..." },
      { "id": 2, "mode": "read_only", "url": "...", "created_at": "..." }
    ]
  }
}

// GET /api/shared/{tokenParam}
{
  "data": {
    "list": { "id": 5, "name": "Compra semanal", "owner_name": "Maria" },
    "mode": "edit",
    "items": { "frutas_verduras": [...], ... },
    "counters": { "items_total": 12, "items_completed": 3 }
  }
}

// GET /api/lists/{id}/collaborators/count
{ "data": { "count": 2 } }

// GET /api/lists/{id}/activity
{
  "data": {
    "entries": [
      { "id": 123, "actor_type": "anonymous", "action": "item_checked", "item_name": "Leche", "created_at": "2026-04-11T10:05:00Z" },
      { "id": 122, "actor_type": "owner", "action": "item_added", "item_name": "Pan", "created_at": "2026-04-11T10:04:30Z" }
    ]
  }
}

// 410 revoked/invalid (same shape for both to avoid information leakage)
{ "error": "Este enlace ya no esta activo." }

// 429 rate limit
{ "error": "Demasiadas peticiones." }
// Headers: Retry-After: 60
```

## Integration with Existing Code

### ShoppingList Model
Add relationships:
```php
public function shareTokens(): HasMany { return $this->hasMany(ListShareToken::class); }
public function activityLog(): HasMany { return $this->hasMany(ListActivityLog::class); }
```

### ListItemService — signature extension
Each mutation method gets an optional `ShareTokenContext $context = null`. When present, the service records an activity log entry with `actor_type=anonymous`; when null, with `actor_type=owner`. This keeps the mutation logic in one place and avoids branching at the controller layer.

```php
public function create(ShoppingList $list, array $data, ?ShareTokenContext $context = null): array
{
    return DB::transaction(function () use ($list, $data, $context) {
        $item = $list->items()->create([...]);
        $counters = $this->syncCounters($list);
        $this->activityLog->record(
            $list,
            $context ? ActorType::Anonymous : ActorType::Owner,
            ActivityAction::ItemAdded,
            $item->name,
            $context?->tokenId
        );
        return ['item' => $item, 'counters' => $counters];
    });
}
```

Existing Epic 3 tests for `ListItemService` must continue to pass. A new activity log entry is written on every mutation, so those tests need to be updated to either assert or ignore the log. Preferred: assert the log entry is present for each operation. This expands Epic 3 tests, does not replace them.

### ShoppingListController
Add two methods: `collaboratorsCount(ShoppingList $list)` and `activityLog(ShoppingList $list)`. Both authorize ownership. Thin delegation to services.

### AccountDeletionService
On hard-delete of a user, cascade removes `shopping_lists` → `list_share_tokens` → `list_collaborator_sessions` + `list_activity_log`. No changes needed (existing cascades handle it).

## Security

### Threat model

| Threat | Mitigation |
|--------|------------|
| Token enumeration (guess token_id) | 128-bit UUID space; even if guessed, signature must match → 256-bit HMAC space |
| Token forgery (craft valid URL without DB read) | HMAC with APP_KEY as secret; forgery requires key compromise |
| Token forgery with DB read access | Signature not stored → attacker cannot reconstruct URLs even with full DB dump |
| Brute-force of signature | Rate limit 60/min per IP + 256-bit signature space |
| Timing oracle on valid/invalid/revoked | Unified 410 response, `hash_equals`, fixed-delay buffer |
| Read-only bypass via crafted API call | Middleware attaches `mode` to request; controller short-circuits on write endpoints before service layer |
| Cross-tenant leak (token A → list B data) | `list_id` always derived from `token.shopping_list_id`, never from URL params |
| Anonymous user pollutes `producto_historial` | `producto_historial.user_id = list.owner.user_id` (historial is owned by the list owner, consistent with Epic 3) |
| Session UUID collision | `session_uuid` scoped per `list_share_token_id`; collision requires 122-bit UUID collision |
| Owner RGPD rights conflict (delete account while list shared) | Cascade delete propagates through tokens/sessions/logs |

### Authorization Model

```
Route                                    Middleware stack
/api/lists/**                            auth:api + JwtVersionCheck
/api/shared/{tokenParam}/**              ValidateShareToken (attaches context)
                                         + mode check in controller for writes
                                         + throttle:60,1
/api/lists/{list}/share (POST)           auth:api + JwtVersionCheck + throttle:10,60
                                         (10 tokens/hour per authenticated user)
```

### Rate Limiting Details

- **Public shared endpoints**: `throttle:60,1` keyed by IP (Laravel default).
- **Token generation**: `throttle:10,60` keyed by authenticated user ID (addresses PRD "Free users abuse sharing as viral spam" risk).
- **Heartbeat**: counts against the 60/min bucket. 1 request every 10s = 6/min — well within budget.

## Performance

### Query Optimization

- **`collaboratorsCount`**: indexed join on `(shopping_list_id, revoked_at)` + `(list_share_token_id, last_heartbeat_at)`. Result cached 5s per list_id to absorb polling from multiple tabs.
- **`activityLog`**: `ORDER BY id DESC LIMIT 50` on `(shopping_list_id, id)` index. No join needed.
- **`heartbeat`**: single `INSERT ... ON DUPLICATE KEY UPDATE` (MySQL upsert) on `(list_share_token_id, session_uuid)` unique. O(1).
- **Activity log rolling cleanup**: subquery delete indexed on `(shopping_list_id, id)`. O(log n).
- **Token verification**: single indexed lookup on `token_id` (UUID unique index).

### Caching Strategy

| Cache | Key | TTL | Invalidation |
|-------|-----|-----|--------------|
| Collaborators count | `list_collaborators:{list_id}` | 5s | Time-based |

No other caching. Activity log must be real-time for owner.

### Polling Load

Worst case scenario: a popular list with 10 active collaborators, polling at 10s interval. That's 10 heartbeats/10s + 1 owner poll/10s = 11 req/10s = 66 req/min on that list. Within throttle budgets (each collaborator is a distinct IP). DB load: 11 indexed upserts + 1 cached count per 10s. Negligible at this scale.

## Frontend Architecture

### Routes

- `/shared/:tokenParam` — public, no auth guard. Renders `SharedListPage`.
- `/app/listas/:id` — existing, extended with `CollaboratorIndicator` and `ActivityLogView` components when `list.is_shared` is true.

### Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `SharedListPage` | `pages/SharedListPage.jsx` | Full anonymous flow: consent gate, list view, mutations (if edit), heartbeat loop, CTA register |
| `ShareListModal` | `components/collab/ShareListModal.jsx` | Owner modal: active tokens list, generate buttons, revoke, copy, native share |
| `CollaboratorIndicator` | `components/collab/CollaboratorIndicator.jsx` | Live count badge polling every 10s |
| `ActivityLogView` | `components/collab/ActivityLogView.jsx` | Expandable panel, polls every 10s |
| `ConsentBanner` | `components/collab/ConsentBanner.jsx` | Blocking modal on first visit of a `SharedListPage` |
| `RevokedLinkView` | `components/collab/RevokedLinkView.jsx` | Error state component |

### State Management

- `SharedListPage`:
  - `mode`: edit | read_only (from API)
  - `items`, `counters`: from API
  - `ownerName`: from API
  - `consented`: boolean, derived from `sessionStorage.getItem('consent:{tokenId}')`
  - `sessionUuid`: derived from `sessionStorage.getItem('session:{tokenId}')` or generated on first load
  - Heartbeat interval (10s), cleared on unmount/visibility change

- `ShareListModal` (owner view):
  - `tokens`: array, loaded on open
  - Local loading/error per action

- `CollaboratorIndicator`, `ActivityLogView`: simple polling hooks using `setInterval` inside `useEffect`. Both pause when tab hidden (`document.visibilityState`).

### API Integration

- `services/shareApi.js`: owner-side (JWT) — generate, revoke, list, count, activity
- `services/sharedListApi.js`: anonymous — show, addItem, toggleItem, editItem, deleteItem, heartbeat. No JWT header. Token param in URL.

### sessionStorage Rationale

Per S1 decision (no tracking), we intentionally do NOT use cookies or localStorage. sessionStorage is tab-scoped and cleared on tab close, used here only for:
1. `consent:{tokenId}` — so navigation within the tab doesn't re-prompt the banner
2. `session:{tokenId}` — to keep the heartbeat session stable across page re-renders within the same tab

Neither identifies the user across sessions, visits, or devices. Consistent with "N personas viendo ahora, no tracking individual".

## Cleanup Command

```
php artisan app:cleanup-collaborator-data
```

Actions (each in its own statement, no cross-statement transaction needed):

1. Delete `list_collaborator_sessions` where `last_heartbeat_at < NOW() - INTERVAL 5 MINUTE`
2. Delete `list_activity_log` where `actor_type='anonymous'` AND `created_at < NOW() - INTERVAL 30 DAY`
3. Delete `list_activity_log` where `list_share_token_id IN (SELECT id FROM list_share_tokens WHERE revoked_at IS NOT NULL AND revoked_at < NOW() - INTERVAL 24 HOUR)`

Schedule in `app/Console/Kernel.php`:
```php
$schedule->command('app:cleanup-collaborator-data')->hourly();
```

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| HMAC-signed URL token (chosen) | Stateless verification of URL integrity, no secret in DB, per-token revocation via flag | Requires APP_KEY stability; signature in URL adds length | **Selected**: matches HU-401 note; strong integrity without DB secrets |
| SHA-256 hash in DB | Simpler, standard pattern | Token plaintext must be handled carefully; less integrity guarantee | Rejected: deviates from HU-401 HMAC spec |
| JWT-based tokens | Standard, expirable | Requires JWT library changes; revocation still needs DB; overkill for this use case | Rejected |
| Increment/decrement counters for sessions | Faster count query | Desync on edge cases (browser crash, tab close) | Rejected: query is cheap with index + 5s cache |
| Websocket-based presence | True real-time | New infra (broadcaster, pusher), ops burden, HU explicitly says polling | Rejected: HU-403 says polling 10s minimum |
| Per-session cookies for consent | Works across tabs | Contradicts "no tracking" decision | Rejected |
| sessionStorage for consent/session (chosen) | Tab-scoped, no persistence | Consent reprompts on new tab | **Selected**: aligned with privacy decision |
| Separate controller for shared-list read vs write | Clean separation | Code duplication for item mutation logic | Rejected: single `SharedListController` with mode checks |
| Mode check in controller only | Simple | Easy to forget on new endpoints | Rejected |
| Mode check in middleware + controller | Defense in depth | Slightly more code | **Selected**: middleware flags `requires_write`, controller enforces |
| Activity log cleanup on write (rolling 50) | Log always bounded | +1 subquery on every mutation | **Selected**: simplicity > cron |
| Activity log cleanup via cron | No per-write overhead | Log can exceed 50 transiently; another moving part | Rejected |
| Collaborators count cached 5s | Absorbs polling storms | Count is 5s stale | **Selected**: 5s staleness invisible to user |
| No cache on count | Always fresh | High DB load under many collaborators | Rejected |
| Read-only allows toggle | Easier collaborators | Contradicts HU-401 | Rejected: HU-401 canonical |
| Read-only = view-only (chosen) | Matches HU-401 literally | Stricter collaborator UX | **Selected**: HU-401 crit. 6 canonical. PRD AC-8/AC-9/AC-10 updated. |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Read-only enforcement skipped on future shared endpoint | High (security/spec violation) | Medium | Middleware attaches `requires_write` flag; `SharedListController` checks mode on every write before delegating to service. Feature tests include negative case for each write endpoint with a read_only token. |
| APP_KEY rotation invalidates all existing share links | Medium | Low | Documented in ops notes. Rotation is rare. Acceptable trade-off for integrity. |
| Extending ListItemService breaks Epic 3 tests | Medium | Medium | Service signature uses optional parameter; Epic 3 tests pass null. Run full suite before S4 complete. |
| HMAC signature leaks via URL in server logs | Medium | Medium | Instruct ops (docs) to redact `/shared/*` from access logs. Laravel log does not log paths by default. Nginx config guidance. |
| Owner revokes token while anonymous is mid-mutation | Low | Low | Request fails with 410 at next call. In-flight mutation already committed. Not data-corrupting. |
| Cleanup command fails silently | Medium | Low | Command writes to Laravel log on failure. Monitored via existing log infra. |
| Polling causes visible lag on slow devices | Low | Low | Pause polling when tab hidden (`visibilityState`). |
| Rate limit breaks legitimate use | Low | Low | 60/min is generous; heartbeat is 6/min baseline; leaves 54/min for mutations. |
| MySQL upsert dialect (ON DUPLICATE KEY UPDATE) non-portable | Low | Low | Project is MySQL-only per stack.yaml. Use Eloquent `updateOrCreate` for portability anyway. |
| sessionStorage disabled by user / incognito | Low | Low | Fall back to in-memory React state; consent prompts per page load. Acceptable. |

## Open Questions

None. PRD AC-8/AC-9/AC-10 updated: read_only forbids all mutations (toggle, add, edit, delete). Canonical reference: HU-401 crit. 6. Backend validates token mode on every write endpoint, frontend enforcement alone is insufficient.

## Implementation Notes

### Suggested execution order for S4

1. **Backend foundation** (migrations + models + enums): establish the schema and domain primitives first.
2. **`ShareTokenSigner` + `ValidateShareToken` middleware** with unit tests: security-critical, land it standalone.
3. **`ShareTokenService`** with unit tests: owner flows (generate, revoke, list).
4. **`ActivityLogService`** with unit tests: including rolling-50 enforcement.
5. **`CollaboratorPresenceService`** with unit tests.
6. **Extend `ListItemService`** with context support, update Epic 3 tests to assert log entries.
7. **Controllers + routes** (ShareTokenController, SharedListController, ShoppingListController extensions).
8. **Feature tests**: happy paths, permission failures, read-only bypass attempts, rate limits, constant-time revoked/invalid.
9. **`CleanupExpiredCollaboratorData` command** + feature test.
10. **Frontend** (after backend green): SharedListPage, ShareListModal, CollaboratorIndicator, ActivityLogView, ConsentBanner, RevokedLinkView. Connect to API. Component tests per Epic 3 pattern (vitest).

### Testing Reminders (NON-NEGOTIABLE per core.md)

- 100% coverage target.
- Real MySQL DB + DatabaseTransactions trait (no SQLite).
- Happy + failure + edge + security paths.
- Security negative tests mandatory: revoked token, invalid signature, read-only bypass, rate limit 429, cross-tenant token, timing oracle (timing check with tolerance).

### Frontend work identified

Significant UI surface (1 page, 1 modal, 4 components). **S5-UX review required** post-implementation, covering all six artifacts listed in PRD §UX Decision.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation
- Required Artifacts: 01-scope.md, 02-prd.md, 03-technical-design.md
