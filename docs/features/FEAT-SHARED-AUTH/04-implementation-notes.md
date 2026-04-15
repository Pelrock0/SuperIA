# Implementation Notes - FEAT-SHARED-AUTH

## Scope Changes

_None._

## API Contract (Backend -> Frontend)

### Endpoints Created

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/shared/{tokenParam}/save` | JWT required | Vincular lista compartida a cuenta del usuario |
| GET | `/api/shared/{tokenParam}/save-status` | JWT optional | Consultar si el usuario tiene la lista vinculada |
| GET | `/api/lists/{id}/collaborators` | JWT (owner only) | Listar colaboradores vinculados a una lista |

### Modified Endpoints

| Method | Path | Change |
|--------|------|--------|
| GET | `/api/lists` | Response incluye `collaborated` array con listas colaboradas |
| GET/POST/PUT/DELETE | `/api/lists/{id}/items/*` | Colaboradores con permisos adecuados pueden acceder |

### Request/Response Examples

```json
// POST /api/shared/{tokenParam}/save
// Request: (empty body, JWT in Authorization header)
// Response 201:
{ "data": { "linked": true, "mode": "edit" } }

// GET /api/shared/{tokenParam}/save-status
// Response 200 (authenticated, not linked):
{ "data": { "authenticated": true, "is_owner": false, "linked": false } }
// Response 200 (not authenticated):
{ "data": { "linked": false, "authenticated": false } }

// GET /api/lists/{id}/collaborators
// Response 200:
{ "data": [
    { "id": 1, "user_id": 5, "name": "Laura", "email": "laura@test.com", "mode": "edit", "linked_at": "2026-04-15T17:00:00+00:00" }
] }

// GET /api/lists (modified response)
{ "data": {
    "active": [...],
    "archived": [...],
    "collaborated": [
        { "id": 42, "name": "Semanal", "emoji": "🛒", "owner_name": "Pedro", "collaborator_mode": "edit", ... }
    ]
} }
```

### Error Codes

| Code | Meaning | Frontend Action |
|------|---------|-----------------|
| 401 | Not authenticated (save endpoint) | Show login prompt |
| 403 | Read-only trying to write | Disable write controls |
| 409 | Owner trying to save own list | Hide save button |

### Registration (retroactive linking)

POST `/api/auth/register` now accepts optional `session_uuids` array. Frontend should collect all `superia:session:*` keys from sessionStorage and send them.

## Implementation Decisions

- `authorizeListOwnership` now allows collaborators for read access
- `authorizeListWrite` is a new method that checks collaborator has edit mode
- `ListCollaborator::updateOrCreate` prevents duplicates and updates mode if token changes
- Cascade revocation uses `share_token_id` FK to delete all collaborators linked via a revoked token

## Known Issues / Technical Debt

_None._
