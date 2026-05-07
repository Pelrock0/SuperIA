# FEAT-EPIC3-ITEMS — Shopping List Items + Purchase History

**Complexity:** HIGH (35-45h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-301 | List detail: items grouped by category, pending above completed, progress counter | Implemented |
| HU-302 | Add item: name (max 80), quantity/unit/category/price optional, 10 categories | Implemented |
| HU-303 | Mark purchased: checkbox+strikethrough, records in `producto_historial` for AI pipeline | Implemented |
| HU-304 | Edit item: side panel with all fields | Implemented |
| HU-305 | Delete item: no confirmation, frontend-only undo (5s snackbar) | Implemented |
| HU-306 | Clear completed: menu action with confirmation | Implemented |

## Complexity Classification

- Purchase history pipeline: HIGH — append-only `producto_historial`, feeds Epics 5-9
- Counter sync: MEDIUM — atomic COUNT in transaction
- Frontend: MEDIUM — optimistic UI, 5s undo snackbar

## Key Dependencies

- `producto_historial` table (append-only, feeds all AI features)
- 10 product category enum
- Counter sync via `COUNT()` (not increment/decrement)

## Design Decisions

- Counter sync via `COUNT()` ensures atomicity even if exceptions occur mid-operation
- Undo is frontend-only: backend deletes immediately, frontend holds data 5s for re-creation
- `producto_historial` is append-only — no edits or deletes (except RGPD hard-delete)
- Items without category grouped under "otros"
- Route `/completed` registered before `/{item}` to prevent segment collision

## Deviations

None.

## Review Findings

- Ownership chain validated: item → list → user on every mutation
- `producto_historial` SET NULL on list deletion (history survives list deletion)
- Delete button uses opacity-0 hover (keyboard visibility non-blocking suggestion)
- 39 backend + 26 frontend tests passing
