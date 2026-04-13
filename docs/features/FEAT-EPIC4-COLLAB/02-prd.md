# PRD: FEAT-EPIC4-COLLAB - Colaboracion y Listas Compartidas

## Business Objective

Enable users to share shopping lists with others — with or without accounts — so that households, couples, roommates and groups can collaborate on the same list in real-time. Collaboration is the primary viral growth vector for Superia: a non-user receives a link, uses the list, gets value, registers. Blocking or complicating sharing removes the main organic acquisition channel.

## Problem Statement

Today a Superia list belongs to a single user. There is no way to share it. A couple shopping together must either coordinate verbally or one person maintains the list while the other waits. This breaks the real use case — household shopping is a group activity. Additionally, the app has no mechanism to attract new users organically; every signup must come from paid channels.

## Scope

### In Scope

- **HU-401**: Generate share link from list detail. Two independent link types per list: `edit` (collaborators can add/check/edit/delete items) and `read_only` (collaborators can only view and mark as purchased? No — read_only = view only, no mutations). Copy link to clipboard. Share via native OS share sheet (WhatsApp, email, etc.). Revoke individual token. Re-generate a new token on demand after revoking.
- **HU-402**: Anonymous access to shared list via `/shared/:token` route. No login required. Consent banner on first access with owner name and 30d retention disclosure. View all items and their state. Mark/unmark items as purchased (both link types). Add/edit/delete items (edit link only). CTA to register. Clear error state when link is revoked or invalid.
- **HU-403**: Active collaborators indicator on list detail page — shows "N personas viendo ahora" (count only, no identities). Heartbeat every 10s from each active collaborator. 30s timeout marks session inactive. Activity log view showing last 50 modification events across all actors (owner + anonymous). Polling every 10s to refresh both indicator and log.
- New tables: `list_share_tokens`, `list_collaborator_sessions`, `list_activity_log`.
- New token middleware `ValidateShareToken` with rate limiting (60 req/min/IP) and constant-time response.
- New console command `CleanupExpiredCollaboratorData` for RGPD 30-day retention enforcement.
- Free users CAN share lists. Shared lists count toward the owner's Free slot limit (3 active).

### Out of Scope

- Real-time websockets (polling only, per HU-403 minimum)
- Individual collaborator identification (no names, no tracking — only count)
- Push notifications to owner when collaborators join or modify
- Email notifications on share events
- Multiple shared lists merging or cross-list operations
- Sharing individual items (only whole-list sharing)
- Registered-user-to-registered-user sharing as "collaborators" (Epic 4 MVP treats all non-owners as anonymous token holders, even if logged in; a logged-in user who clicks a shared link uses the same anonymous flow)
- Link expiration by date (HU-401 note says default is no expiration; explicitly not building configurable TTL in MVP)
- Owner transfer or co-ownership
- Permission granularity beyond edit / read-only
- Audit logs beyond 50 rolling entries per list
- Export of activity log

## Acceptance Criteria

### AC-1: Generate edit link from list detail
- **Given**: An authenticated list owner on list detail page
- **When**: They click "Compartir" and select "Permitir editar"
- **Then**: An edit token is generated. Modal shows the full shareable URL (`https://superia.com.local/shared/{token}`). A "Copiar enlace" button copies it to clipboard. Native share button opens OS share sheet.

### AC-2: Generate read-only link
- **Given**: An authenticated list owner on list detail page
- **When**: They click "Compartir" and select "Solo ver"
- **Then**: A read-only token is generated. URL shown and copyable. Independent from any existing edit token for the same list.

### AC-3: Both link types coexist
- **Given**: A list with an active edit token and an active read-only token
- **When**: Owner opens the share modal
- **Then**: Both tokens are listed, each with its URL, type (edit / read-only), creation date, and a "Revocar" button.

### AC-4: Revoke a specific token
- **Given**: A list with two active tokens (edit + read-only)
- **When**: Owner clicks "Revocar" on the edit token
- **Then**: The edit token is marked revoked. The read-only token remains active. Subsequent requests to the revoked URL return a "revoked" response.

### AC-5: Re-generate after revoke
- **Given**: A list whose edit token was just revoked
- **When**: Owner clicks "Permitir editar" again
- **Then**: A new edit token is generated with a new URL. The old URL remains revoked (not reactivated).

### AC-6: Anonymous access — first visit with consent
- **Given**: A non-authenticated user opens `/shared/{token}` for the first time
- **When**: The page loads with a valid edit or read-only token
- **Then**: A consent banner appears: "Esta lista es de [nombre propietario]. Al usarla aceptas que registramos tu actividad en esta lista durante 30 dias solo como proposito de utilidad no con fines publicitarios." With "Continuar" button. The list is not usable until the banner is accepted.

### AC-7: Anonymous access — after consent
- **Given**: The consent banner has been accepted in the current session
- **When**: The user interacts with the shared list
- **Then**: The list items load. Title, owner name, and permission mode (edit / read-only) are displayed. A subtle CTA "Crea tu propia lista gratis" links to the landing page.

### AC-8: Anonymous mark item as purchased — edit token only
- **Given**: A shared list loaded via an **edit** token
- **When**: The anonymous user clicks the checkbox on a pending item
- **Then**: The item is marked purchased (via the same logic as Epic 3 HU-303, including `producto_historial` write with the owner's `user_id`). An entry is added to `list_activity_log` with `actor_type=anonymous`, `action=item_checked`.

### AC-9: Anonymous add/edit/delete item — edit token only
- **Given**: A shared list loaded via an **edit** token
- **When**: The anonymous user adds, edits or deletes an item
- **Then**: The operation succeeds. A corresponding `list_activity_log` entry is created with `actor_type=anonymous` and the matching action (`item_added`, `item_edited`, `item_deleted`).

### AC-10: Read-only token — all mutations blocked
- **Given**: A shared list loaded via a **read_only** token
- **When**: The anonymous user attempts to mark/unmark, add, edit or delete an item (via UI or by crafting a direct API request)
- **Then**:
  - The backend returns HTTP 403 on every write endpoint. Enforcement is at the middleware + service layer, not only in the UI.
  - The UI does not expose add/edit/delete controls at all.
  - Item checkboxes are rendered disabled with `opacity: 0.4` and `cursor: not-allowed`, and clicking them is a no-op.
  - Canonical reference: HU-401 criterion 6 ("solo lectura — ver pero no modificar").

### AC-11: Revoked link access
- **Given**: A token that has been revoked by the owner
- **When**: Any user opens `/shared/{token}`
- **Then**: A "Enlace no disponible" page is shown: "Este enlace ya no esta activo. Pide uno nuevo al propietario de la lista." No list data is leaked.

### AC-12: Invalid / non-existent token
- **Given**: A URL with a random or malformed token
- **When**: The page loads
- **Then**: Same "Enlace no disponible" page. Same response time as a revoked token (constant-time, no information leakage).

### AC-13: Rate limit on shared endpoints
- **Given**: Requests hitting `/api/shared/{token}/*`
- **When**: An IP exceeds 60 requests per minute
- **Then**: HTTP 429 with `Retry-After` header. Applies per IP, across all tokens.

### AC-14: Heartbeat and active counter
- **Given**: A shared list page is open by N anonymous users
- **When**: Each open tab sends `POST /api/shared/{token}/heartbeat` every 10 seconds
- **Then**: The backend upserts a `list_collaborator_sessions` row keyed by a session UUID generated on first load (not persisted beyond the tab lifecycle).

### AC-15: Active counter shown to owner
- **Given**: An owner is viewing their list detail page while 2 anonymous collaborators are active
- **When**: The owner's page polls `GET /api/lists/{id}/collaborators/count` every 10s
- **Then**: The response is `{count: 2}`. The UI shows "2 personas viendo ahora". Only count is exposed — no IDs, no names, no IP.

### AC-16: Active counter — stale sessions
- **Given**: A collaborator session last heartbeated 31 seconds ago
- **When**: The count query runs
- **Then**: That session is excluded from the count (treated as inactive at 30s threshold). Subsequent cleanup command hard-deletes stale sessions.

### AC-17: Activity log view — owner only
- **Given**: An owner on list detail page with the activity log visible
- **When**: The log loads
- **Then**: Up to 50 most-recent entries are shown, each with: actor (`Propietario` or `Colaborador`), action (e.g. `anadio "Leche"`, `marco "Pan" como comprado`), timestamp (relative: "hace 2 minutos").

### AC-18: Activity log — rolling 50
- **Given**: A list with 50 existing entries in `list_activity_log`
- **When**: A new mutation creates a 51st entry
- **Then**: The oldest entry is deleted in the same transaction as the insert. The log always contains at most 50 entries per list.

### AC-19: Activity log — logged actions
- **Given**: Any of: add item, check item, uncheck item, edit item, delete item, clear completed (Epic 3 operations)
- **When**: The operation completes successfully (owner or anonymous)
- **Then**: Exactly one entry is written to `list_activity_log`. Failed operations do not write entries.

### AC-20: RGPD — 30-day retention for anonymous activity
- **Given**: `list_collaborator_sessions` rows and `list_activity_log` rows with `actor_type=anonymous` older than 30 days
- **When**: The `CleanupExpiredCollaboratorData` command runs
- **Then**: All such rows are deleted. Owner-authored log entries are not affected by the 30-day rule (they remain subject to the rolling 50 cap only).

### AC-21: RGPD — purge on revocation
- **Given**: A token is revoked
- **When**: The revoke operation executes
- **Then**: Sessions tied to that token are deleted immediately. Anonymous log entries tied to that token are marked for purge in the next cleanup cycle (kept short-term so owner can see "what happened" but cleaned within 24h).

### AC-22: Freemium — shared list counts toward slot
- **Given**: A Free user with 3 active lists, one of which has an active share token
- **When**: They attempt to create a 4th active list
- **Then**: The existing limit response from Epic 2 applies (blocked). The shared list consumes a slot like any other active list.

### AC-23: Freemium — Free user can share
- **Given**: A Free user with at least 1 active list
- **When**: They open the share modal
- **Then**: They can generate edit and read-only tokens with no upsell block. Sharing is not gated by plan.

### AC-24: Share button visibility
- **Given**: A list detail page viewed by the owner
- **When**: The page renders
- **Then**: A "Compartir" button is visible. Tapping it opens the `ShareListModal`.

### AC-25: Anonymous user attempts to create a new list
- **Given**: An anonymous collaborator on `/shared/{token}`
- **When**: They click the "Crea tu propia lista gratis" CTA
- **Then**: They are redirected to the registration page (Epic 1). There is no way to create a list from the shared view itself.

### AC-26: Counter consistency with Epic 3
- **Given**: Any item mutation from an anonymous collaborator (check, add, delete via edit token)
- **When**: The mutation completes
- **Then**: `shopping_lists.items_total` and `items_completed` reflect the new state, consistent with Epic 3 AC-19. Counters update regardless of actor type.

## UX Decision

- **UX Designer Required**: YES
- **UX Artifacts**: Stitch MCP screens for Superia. Consumed at S4, reviewed at S5-UX.
- **Screens involved**:
  - `ShareListModal` — Stitch "Compartir lista" (HU-401 note). Contains link list, copy, revoke, native share, toggle between edit/read-only generation.
  - `SharedListPage` — Stitch "Vista compartida" (HU-402 note). Full anonymous flow: consent banner, title, owner name, list items, add/edit/delete (edit mode only), register CTA.
  - `ConsentBanner` — Component within `SharedListPage` (first-visit gate).
  - `RevokedLinkView` — Error state shown at `/shared/:token` for revoked/invalid tokens. No Stitch screen yet — design inline, minimal (icon + message + link to landing).
  - `CollaboratorIndicator` — Component added to `ListDetailPage` (owner view). Small badge with live count.
  - `ActivityLogView` — Component or section within `ListDetailPage` (owner view). Expandable panel with rolling log. No Stitch screen yet — design inline following existing ListDetailPage aesthetic.

> **UI changes heads-up**: This PRD introduces significant new UI surface (one new page, one new modal, three new components). A **UX review at S5-UX is required** and must cover all six artifacts above.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Token brute-force enumeration | Security | 32-byte entropy HMAC token. Rate limit 60 req/min/IP. Constant-time response for valid vs invalid vs revoked tokens (avoid timing oracle). |
| Read-only bypass via crafted API call | Security | Permission enforced at service layer (`ListItemService` checks token mode), not only in UI. `ValidateShareToken` middleware attaches mode to request; controller checks mode before delegating. |
| Cross-tenant leak (token A returns list B data) | Security | Every shared endpoint derives `list_id` from the token itself, never from user input. Service validates `token.list_id` matches the operated resource. |
| Anonymous user hammers heartbeat | Performance | Heartbeat counts toward the 60/min rate limit. Upsert is single-query by session UUID. Indexed on `(token_id, session_uuid)`. |
| Stale collaborator sessions bloating table | Data | 30s inactivity threshold excludes from count queries. Hourly cleanup command hard-deletes rows older than 5 minutes since last heartbeat. |
| Activity log rolling cleanup race condition | Technical | Insert + delete-oldest executed in the same DB transaction. Subquery-based delete avoids lock contention under load. |
| RGPD non-compliance for anonymous data | Security / Legal | Consent banner explicit and blocking. 30d retention enforced by command. Purge on revocation within 24h. Retention documented in privacy policy (TBD ownership of that update). |
| Free users abuse sharing as viral spam | Operational | Rate limit on token generation (TBD at S3: propose 10 tokens/hour per owner). Not solved in MVP beyond this. |
| Polling at 10s by many users creates DB load | Performance | Cached count query (short TTL ~5s) at Laravel Cache layer. Heartbeat upsert is O(1). Indexes on `(token_id, last_heartbeat_at)`. |
| Frontend tab/browser behavior unreliable for heartbeat | Technical | Acceptable trade-off. Worst case: counter shows a user that just closed their tab for up to 30s. Not safety-critical. |

## Assumptions

- Stitch MCP screens "Compartir lista" and "Vista compartida" exist and are accessible via MCP at S4.
- Laravel's built-in RateLimiter supports per-IP throttling (confirmed from Epic 1 login attempts).
- HMAC-SHA256 key material comes from `APP_KEY` or a dedicated env var (decision at S3).
- Anonymous collaborators using the same browser tab keep the same session UUID until tab close. No persistence beyond tab lifecycle.
- `producto_historial` writes from anonymous mutations use the owner's `user_id` (consistent with Epic 3; the history belongs to the list owner).
- The 10 predefined product categories from Epic 3 apply unchanged to items added by anonymous collaborators.
- Epic 2's 3-active-list limit for Free users is enforced at list creation, not at share time. Sharing does not alter existing Epic 2 logic.

## Open Questions

None. All resolved at S1 (consent text, token types, revocation, heartbeat/timeout, log retention, RGPD, rate limit, freemium interaction).

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
- Required Artifacts for Next Step: 01-scope.md, 02-prd.md
