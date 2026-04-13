# PRD: FEAT-EPIC2-LISTS - Gestion de Listas de Compra

## Business Objective

Users who completed registration (Epic 1) need a core workspace to manage their shopping lists. Without lists, the app has no utility beyond authentication. This epic delivers the fundamental CRUD operations that every subsequent feature (items, collaboration, AI) depends on.

## Problem Statement

Authenticated users land on an empty dashboard placeholder (`/app`). There is no way to create, view, edit, archive, or delete shopping lists. The app is functionally inert after login.

## Scope

### In Scope

- **HU-201**: Dashboard page showing all user's lists as cards (name, items pending/total, last modified, shared indicator placeholder, active/archived sections, empty state with CTA)
- **HU-202**: Create new list (name required max 60 chars, emoji unicode optional, category optional: Supermercado/Mercado/Online/Farmacia/Otro, freemium limit 3 active lists)
- **HU-203**: Edit list name, emoji, and category (auto-save, revert if name empty)
- **HU-204**: Archive and restore lists (archived section, archived lists don't count in freemium limit)
- **HU-205**: Delete list permanently (confirmation dialog, warning if shared placeholder, redirect to dashboard)
- `is_shared` field as boolean placeholder (always false, no functionality until Epic 4)
- Items count fields as placeholders (`items_total`, `items_completed` — always 0 until Epic 3)
- Integration with AccountDeletionService (delete user's lists on account deletion)

### Out of Scope

- Items inside lists (Epic 3)
- Sharing/collaboration (Epic 4)
- AI features (Epics 5-8)
- Real-time sync
- Drag-and-drop list reordering
- List templates or duplication
- List search/filter on dashboard
- Premium plan management or upgrade flow

## Acceptance Criteria

### AC-1: Dashboard — shows user's active lists
- **Given**: An authenticated user with 2 active lists
- **When**: They navigate to `/app`
- **Then**: Both lists appear as cards showing: name, emoji (if set), category, items count (0/0 placeholder), last modified date. Active lists appear first.

### AC-2: Dashboard — empty state
- **Given**: An authenticated user with no lists
- **When**: They navigate to `/app`
- **Then**: A welcome message is shown with a prominent CTA button to create the first list

### AC-3: Dashboard — archived section
- **Given**: An authenticated user with 1 active and 1 archived list
- **When**: They navigate to `/app`
- **Then**: The active list appears in the main section. The archived list appears in a separate "Archivadas" section below.

### AC-4: Dashboard — shared indicator placeholder
- **Given**: An authenticated user with lists
- **When**: They view the dashboard
- **Then**: Each card shows a shared indicator area (currently always empty/false). No functionality until Epic 4.

### AC-5: Create list — success
- **Given**: An authenticated user with fewer than 3 active lists
- **When**: They click "Nueva lista", enter name "Compra semanal", select emoji "🛒", category "Supermercado", and submit
- **Then**: The list is created and the user is redirected to the list detail page (`/app/listas/:id`). The list appears on the dashboard.

### AC-6: Create list — name only (minimal)
- **Given**: An authenticated user
- **When**: They create a list with only a name (no emoji, no category)
- **Then**: The list is created successfully with emoji and category as null

### AC-7: Create list — freemium limit reached
- **Given**: An authenticated user with 3 active lists (freemium maximum)
- **When**: They try to create a new list
- **Then**: The system shows a message: "Has alcanzado el limite de 3 listas activas. Archiva o elimina una lista para crear otra nueva."

### AC-8: Create list — validation
- **Given**: An authenticated user
- **When**: They submit the create form with empty name or name longer than 60 characters
- **Then**: Validation error is shown. The list is not created.

### AC-9: Edit list — name change
- **Given**: An authenticated user viewing their list on the dashboard
- **When**: They edit the list name to "Mercado sabado"
- **Then**: The name is updated and saved. Success feedback shown.

### AC-10: Edit list — empty name revert
- **Given**: An authenticated user editing a list name
- **When**: They clear the name field (empty)
- **Then**: The name reverts to the previous value. No error shown, no save attempted.

### AC-11: Edit list — emoji and category
- **Given**: An authenticated user
- **When**: They change the emoji to "🏪" and category to "Mercado"
- **Then**: Both fields are updated and saved.

### AC-12: Archive list
- **Given**: An authenticated user with an active list
- **When**: They select "Archivar" from the list options menu
- **Then**: The list moves to the "Archivadas" section. It no longer counts toward the freemium limit. Content is preserved.

### AC-13: Restore archived list
- **Given**: An authenticated user with an archived list
- **When**: They select "Restaurar" from the archived list options
- **Then**: The list returns to active status in the main dashboard section

### AC-14: Restore — freemium check
- **Given**: An authenticated user with 3 active lists and 1 archived list
- **When**: They try to restore the archived list
- **Then**: The system shows: "Has alcanzado el limite de 3 listas activas. Archiva o elimina una lista antes de restaurar."

### AC-15: Delete list — confirmation
- **Given**: An authenticated user with an active list
- **When**: They select "Eliminar" from the list options
- **Then**: A confirmation dialog appears: "Esta accion eliminara la lista permanentemente. Continuar?"

### AC-16: Delete list — execution
- **Given**: An authenticated user confirms deletion
- **When**: The deletion executes
- **Then**: The list is permanently deleted. The user returns to the dashboard. The list no longer appears.

### AC-17: Delete list — shared warning placeholder
- **Given**: An authenticated user deletes a list where `is_shared` is true (future scenario)
- **When**: They select "Eliminar"
- **Then**: The confirmation includes: "Esta lista esta compartida. Los colaboradores perderan el acceso."

### AC-18: Dashboard — responsive
- **Given**: An authenticated user on mobile (375px)
- **When**: They view the dashboard
- **Then**: List cards stack vertically, touch-friendly sizing, all functionality accessible

### AC-19: Account deletion — cascades lists
- **Given**: A user with 3 lists initiates account deletion (HU-105)
- **When**: The account is soft-deleted
- **Then**: All user's lists are also deleted (soft or hard, matching user lifecycle)

## UX Decision

- **UX Designer Required**: YES
- **UX Artifacts**: Stitch MCP screen "Dashboard" exists. Will be consumed during S4 frontend implementation and reviewed at S5-UX.
- **Screens involved**:
  - `DashboardPage` — Stitch screen "Dashboard" → `/app`
  - Create list modal/form (inline in dashboard)
  - List card component (reusable)
  - Empty state component

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Freemium limit bypass via race condition | Technical | Enforce limit check in DB transaction (atomic count + create) |
| Cascade deletion with future shared lists | Data | Implement soft-delete for lists now. Epic 4 will handle ownership transfer. |
| Dashboard performance with many archived lists | Performance | Paginate archived section or lazy-load. At current scale (max 3 active + archives), not a concern. |

## Assumptions

- Stitch MCP screen "Dashboard" exists and is accessible for frontend implementation
- Items count (items_total, items_completed) will be 0 until Epic 3 — dashboard shows "0 de 0" or similar placeholder
- The list detail page route (`/app/listas/:id`) will exist as a placeholder — full implementation in Epic 3
- Freemium limit (3 active lists) is hardcoded for now — no premium plan logic until Epic 10

## Open Questions

None. All resolved in S1.

## Approval

- [ ] PRD approved by stakeholder on [date]

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
- Required Artifacts for Next Step: 02-prd.md
