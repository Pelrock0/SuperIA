# Technical Design — FEAT-EPIC4-COLLAB

## Architecture

Token-based anonymous access parallel to JWT auth. `ShareTokenSigner` is a pure value class (no DB access). `ValidateShareToken` middleware resolves token context and attaches `ShareTokenContext` to request attributes.

## Data Flow

```
Create share token:
  POST /api/lists/{id}/share { mode: 'read'|'write' }
  → Generate token_id (UUID v4)
  → ShareTokenSigner::sign(token_id, list_id, mode, APP_KEY) → HMAC-SHA256 signature
  → INSERT list_share_tokens { token_id, shopping_list_id, mode, revoked_at=null }
  → Return URL: "https://app.com/shared/{token_id}.{signature}"
  → UPDATE shopping_lists SET is_shared=true

Anonymous access (any /shared route):
  → ValidateShareToken middleware:
    1. Split tokenParam on '.' → (token_id, signature)
    2. Verify: hash_equals(stored_sig, recomputed_sig)  ← constant-time
    3. Check revoked_at IS NULL
    4. Check mode if ':write' suffix on middleware
    5. Attach ShareTokenContext to request->attributes['shareTokenContext']
    6. Any failure → 410 Gone (unified, no information leak)

Anonymous item mutation:
  PUT /api/shared/{token}/items/{item} { name, quantity... }
  → ValidateShareToken:write middleware
  → SharedListController::updateItem()
    → Derive list_id from token context (not user input)
    → ListItemService::update(item, data, context)
      → ActivityLog entry: { actor_type='anonymous', token_id, action, item_name }
      → Rolling-50 cleanup inside same transaction:
          DELETE oldest WHERE shopping_list_id = ? AND id NOT IN (SELECT last 50)

Heartbeat (presence):
  POST /api/shared/{token}/heartbeat { session_uuid }
  → upsert list_collaborator_sessions (token_id, session_uuid) set last_heartbeat_at
  → Count sessions with last_heartbeat_at > now-5s → "N personas viendo"

Revoke:
  DELETE /api/lists/{id}/share/{token}
  → UPDATE list_share_tokens SET revoked_at = now()
  → DELETE list_collaborator_sessions WHERE list_share_token_id = token.id
  → Schedule: purge activity log entries after 24h (cleanup command)
  → If no active tokens remain: UPDATE shopping_lists SET is_shared=false
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Signature never stored | Token URL is self-verifying; revocation is DB flag (revoked_at) |
| Mode in signature payload | Prevents mode downgrade (read token cannot be used as write) |
| `hash_equals()` comparison | Constant-time; prevents timing oracle |
| Unified 410 for all failures | No enumeration: invalid, expired, tampered all look the same |
| Rolling-50 inside mutation transaction | Activity log never exceeds 50; no orphaned cleanup jobs |
| Session UUID from `sessionStorage` | Tab-scoped; no cross-tab or cross-session tracking |

## Gotchas

- APP_KEY rotation invalidates ALL active share tokens (operational risk, document in runbook)
- Heartbeat endpoint allowed in read-only mode (presence tracking, not a mutation)
- Anonymous mutations write to `producto_historial` with the list owner's `user_id` (not guest)
- Rate limiting applies per-IP (60/min); own users are not throttled separately for heartbeat
