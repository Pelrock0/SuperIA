# FEAT-EPIC4-COLLAB — Sharing & Real-Time Collaboration

**Complexity:** HIGH (40-50h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-401 | Share link: two independent tokens (edit/read-only), revocation, re-generation | Implemented |
| HU-402 | Anonymous access: consent banner, view items, mutations (edit token only), register CTA | Implemented |
| HU-403 | Collaborator presence: "N personas viendo ahora" via heartbeat (10s), activity log (50 rolling) | Implemented |

## Complexity Classification

- Token security: HIGH — HMAC signing, constant-time verification, mode enforcement
- Anonymous mutations: HIGH — actor context threading through ListItemService
- Real-time presence: MEDIUM — heartbeat polling, session tracking

## Key Dependencies

- 3 new tables: `list_share_tokens`, `list_collaborator_sessions`, `list_activity_log`
- HMAC-SHA256 signing (APP_KEY)
- Heartbeat polling (10s), presence cache 5s TTL
- Existing `ListItemService` extended (not forked)

## Design Decisions

- Token = `{token_id}.{HMAC-SHA256(APP_KEY, token_id||list_id||mode)}` — signature never stored
- Double-layer read-only enforcement: ValidateShareToken middleware + `requireWrite()` in controller
- Unified 410 response for invalid/revoked/malformed/tampered tokens (prevents timing oracle)
- Anonymous mutations attributed to list owner's `user_id` in `producto_historial`
- Rolling-50 activity log cleanup runs inside same transaction as insert
- Heartbeat uses `updateOrCreate` on `(token_id, session_uuid)` unique key
- Free users CAN share; shared lists count toward 3-slot freemium limit
- RGPD: 30-day retention on anonymous data; purge on revocation within 24h

## Deviations

None.

## Review Findings

- Signature verification uses `hash_equals()` (constant-time)
- Cross-tenant isolation verified: token A cannot mutate list B
- Rate limiting: 10 tokens/hour per owner (generation), 60/min per IP (anonymous)
- Accepted risks: localStorage tokens (XSS vector), heartbeat before consent (low-risk crafted requests)
- 292 backend + 158 frontend tests passing
