# Technical Docs — Sharing & Collaboration

**Keywords:** share, token, anonymous, presence, heartbeat, HMAC, collaborator, collab

## Overview

Lists can be shared via HMAC-signed URLs. Anonymous users can view (read) or mutate (write). Authenticated users can save shared lists to their account.

## Token URL Format

```
https://app.com/shared/{token_id}.{signature}
```

Where:
- `token_id` = UUID v4 (stored in DB)
- `signature` = HMAC-SHA256(APP_KEY, `{token_id}|{list_id}|{mode}`)

Signature is **never stored** — recomputed on every request via `ShareTokenSigner::verify()`.

## Token Validation (ValidateShareToken middleware)

```
1. Split param on '.' → (token_id, signature)
2. Load token from DB by token_id
3. hash_equals(recomputed_sig, provided_sig)  ← constant-time
4. Check revoked_at IS NULL
5. IF ':write' suffix: check mode = 'write'
6. Any failure → 410 Gone (unified, no information leak)
7. Attach ShareTokenContext to request->attributes
```

## Presence System

- Client sends heartbeat every 10s: `POST /api/shared/{token}/heartbeat { session_uuid }`
- Server does `upsert` on `(token_id, session_uuid)` unique key → `last_heartbeat_at = now()`
- Presence count: `SELECT COUNT(*) WHERE last_heartbeat_at > now() - 5s`
- Session UUID is tab-scoped (`sessionStorage`, not `localStorage`)

## Activity Log

- Rolling 50 entries per list
- Cleanup inside mutation transaction: `DELETE WHERE id NOT IN (SELECT last 50)`
- Entries: `{ actor_type: 'owner'|'anonymous', action, item_name, created_at }`

## Saving Shared List to Account

```
POST /api/shared/{token}/save (authenticated)
→ ListCollaboratorService::linkUser(user, tokenContext)
  → UPSERT list_collaborators { user_id, shopping_list_id, mode, share_token_id }
→ List appears in dashboard under "collaborated" section
→ User can access list directly (no token URL needed)
```

## Retroactive Linking

When an anonymous user registers:
1. Frontend sends `session_uuids` array in registration request
2. `RegistrationService` calls `ListCollaboratorService::linkRetroactive(user, uuids)`
3. Backfills collaborator records for lists used anonymously (skips revoked tokens, skips own lists)

## Token Revocation

```
DELETE /api/lists/{list}/share/{token}
→ SET revoked_at = now()
→ DELETE list_collaborator_sessions (immediate)
→ DELETE list_collaborators (immediate)
→ Cleanup command purges activity logs within 24h
```

## Key Security Properties

| Property | Mechanism |
|----------|-----------|
| Constant-time verification | `hash_equals()` |
| Mode cannot be upgraded | Mode encoded in signature payload |
| IDOR impossible | `list_id` derived from token, never from user input |
| Revocation immediate | DB flag checked on every request |
| APP_KEY rotation | Invalidates all tokens (document in runbook) |
