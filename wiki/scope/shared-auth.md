# FEAT-SHARED-AUTH — Shared List Collaborators

**Complexity:** HIGH | **Status:** S5-PASS (all reviews, after fixes)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-SA1 | Authenticated user sees "Guardar en mis listas" on shared list | Implemented |
| HU-SA2 | Clicking creates ListCollaborator record; list appears in dashboard | Implemented |
| HU-SA3 | Collaborated lists in dashboard with COLABORADOR badge + owner name | Implemented |
| HU-SA4 | User opens list from dashboard without token URL | Implemented (after fix) |
| HU-SA5 | Revoking share token cascades: deletes linked collaborators | Implemented |
| HU-SA6 | Owner sees collaborator panel per list | Implemented |
| HU-SA7 | Anonymous user who registers retroactively links previous sessions | Implemented |
| HU-SA8 | Collaborator permissions enforced (read-only cannot edit) | Implemented (after fix) |

## Key Dependencies

- `ListCollaborator` model (new)
- `ListCollaboratorService` (new)
- Session UUID tracking via sessionStorage (anonymous→registered linking)
- `AuthContext` integration in frontend

## Design Decisions

- "Guardar" saves on click (no modal confirmation)
- Retroactive linking: session UUID passed at registration, service backfills collaborators
- Permissions fixed at link time (not re-derived from token at each request)
- Revocation cascades: `ListCollaboratorService::removeByToken()` on token revoke
- Owner panel shows collaborators in share modal (no separate UI)

## Blocking Issues Found & Fixed

1. `incrementQuantity` used wrong auth check (`authorizeListOwnership` instead of `authorizeListWrite`) — FIXED
2. `SharedListController@show` returned 403 for collaborators (only checked owner) — FIXED via `authorizeListAccess` helper

## Review Findings

- IDOR prevented: UNIQUE constraint on `(user_id, shopping_list_id)` + ownership checks
- One LOW note: collaborator email visible to list owner (acceptable — explicit design)
- 634 backend tests passing (22 feature-specific tests)
