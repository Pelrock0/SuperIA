# Technical Design — FEAT-SHARED-AUTH

## Architecture

`ListCollaboratorService` manages the collaborator lifecycle. Session UUID (sessionStorage) bridges anonymous→registered flows. Dashboard collaborator section uses a separate API query.

## Data Flow

```
Save shared list to account (authenticated user on shared list):
  POST /api/shared/{token}/save
  → ValidateShareToken middleware resolves token
  → SharedListController::saveToAccount(user, tokenContext)
    → ListCollaboratorService::linkUser(user, tokenContext)
      → UPSERT list_collaborators { user_id, shopping_list_id, mode, share_token_id }
         (idempotent: same user + same list = update mode, not duplicate row)
    → Return { saved: true }

Dashboard collaborator section:
  GET /api/lists  (existing endpoint, extended)
  → ShoppingListService::getListsForUser(user)
    → collaborated = ListCollaboratorService::collaboratedListsForUser(user)
      SELECT sl.*, u.name as owner_name, lc.mode
      FROM shopping_lists sl
      JOIN list_collaborators lc ON lc.shopping_list_id = sl.id
      JOIN users u ON u.id = sl.user_id
      WHERE lc.user_id = ?
    → Return { active, archived, collaborated }

Accessing collaborated list (no token URL):
  GET /api/lists/{list}
  → ShoppingListController::show(user, list)
    → authorizeListAccess(user, list):
        IF list.user_id == user.id → OK (owner)
        IF ListCollaborator.exists(user_id, list_id) → OK (collaborator)
        ELSE 403

Retroactive linking (on registration):
  POST /api/auth/register { ..., session_uuids: ['uuid1', 'uuid2'] }
  → RegistrationService::register()
    → (creates user)
    → ListCollaboratorService::linkRetroactive(user, session_uuids)
      SELECT DISTINCT lcs.shopping_list_id, lst.mode, lst.id as token_id
      FROM list_collaborator_sessions lcs
      JOIN list_share_tokens lst ON lst.id = lcs.list_share_token_id
      WHERE lcs.session_uuid IN (?) AND lst.revoked_at IS NULL
        AND lst.shopping_list_id NOT IN (
          SELECT shopping_list_id FROM shopping_lists WHERE user_id = ?  ← don't self-link
        )
      → INSERT list_collaborators for each match (skip existing)

Revocation cascade:
  DELETE /api/lists/{list}/share/{token}
  → ShareTokenController::destroy()
    → ShareTokenService::revoke(token)
    → ListCollaboratorService::removeByToken(token.id)
      → DELETE list_collaborators WHERE share_token_id = ?
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| UPSERT (not INSERT OR IGNORE) | Updates mode if user re-saves with different token |
| Retroactive linking filters revoked tokens | Don't link users to lists via revoked tokens |
| Self-link prevention in retroactive | Owner shouldn't become their own collaborator |
| Permissions fixed at link time | Mode comes from token at time of save; token can change later without affecting existing collaborators |

## Gotchas

- `authorizeListOwnership` vs `authorizeListAccess` vs `authorizeListWrite`: three distinct checks; using the wrong one causes 403 for valid collaborators (this was the bug found in review)
- `incrementQuantity` must use `authorizeListWrite` (not `authorizeListOwnership`) — collaborators with write mode can increment
- Email of collaborators is visible to list owner in the collaborator panel (LOW privacy note; accepted design)
