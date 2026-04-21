# PRD: FEAT-PURCHASED-ITEM-SINK - Purchased Items Sink to Bottom of Shared List

## Business Objective

When shopping with a shared list, users mark items as purchased one by one. As the list grows, purchased items accumulate at their original positions, forcing users to scroll past them to find what still needs to be picked up. Moving purchased items to the bottom of the list keeps the actionable items always at the top and reduces cognitive load during the shopping trip.

## Problem Statement

Collaborators and owners shopping via `SharedListPage` are affected. The current implementation applies visual styling to purchased items (strikethrough, reduced opacity) but leaves them in their original position in the list. On a list of 20+ items, a user who has checked off 10 items must scroll through all of them to find the 10 remaining — the opposite of the desired shopping experience.

Note: `ListDetailPage` (the owner's main view) already implements the correct separation. This feature closes the gap for `SharedListPage`.

## Scope

### In Scope

- `SharedListPage`: split rendered items into two groups — pending (top) and purchased (bottom), matching the structure already in place in `ListDetailPage`
- Purchased items rendered in a visually distinct "Ya en el carro" section at the bottom, consistent with `ListDetailPage` styling
- The split updates immediately after each toggle (list is re-fetched after toggle already)
- Items grouped by category within the pending section (consistent with existing behaviour)

### Out of Scope

- `ListDetailPage` — already correct, no changes
- Animated transitions (items sliding from top to bottom)
- Drag-and-drop reordering
- Backend changes — the API already returns items ordered by `is_purchased, category, position`
- Any changes to the toggle API or data model

## Acceptance Criteria

### AC-1: Pending items shown at top, purchased at bottom
- **Given**: A shared list with both purchased and non-purchased items
- **When**: The user opens `SharedListPage`
- **Then**: All non-purchased items appear above all purchased items

### AC-2: Purchased item moves to bottom section on toggle
- **Given**: A user is viewing a shared list with item "Leche" not yet purchased
- **When**: The user marks "Leche" as purchased
- **Then**: "Leche" disappears from the pending section and appears in the purchased section at the bottom

### AC-3: Purchased section is visually distinct
- **Given**: There is at least one purchased item
- **When**: The purchased section renders
- **Then**: A section header (e.g. "Ya en el carro") separates the purchased items from pending items, consistent with `ListDetailPage`

### AC-4: No purchased section when all items are pending
- **Given**: A shared list where no items are purchased
- **When**: The list renders
- **Then**: No purchased section header or empty section is shown

### AC-5: No pending section when all items are purchased
- **Given**: A shared list where all items are purchased
- **When**: The list renders
- **Then**: No empty pending section is shown; only the purchased section is visible

### AC-6: Un-toggling a purchased item moves it back to pending
- **Given**: "Pan" is in the purchased section
- **When**: The user unchecks "Pan"
- **Then**: "Pan" moves back to the pending section in its category

## UX Decision

- **UX Designer Required**: No
- **UX Artifacts**: N/A
- **Basic UX Notes**: Replicate the existing `ListDetailPage` purchased/pending split pattern. The "Ya en el carro" section header and item rendering style are already defined there — use them as the reference implementation. No new design decisions required.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Regression in SharedListPage tests | Technical | Update existing tests to reflect new rendering structure |
| Visual inconsistency between pages | Technical | Use exact same section header and item rendering as ListDetailPage |

## Assumptions

- The share-token authentication on `SharedListPage` is unchanged
- The toggle API already re-fetches the full list (confirmed in code) — no optimistic update logic needed
- Items with no category are grouped under `'otros'` (existing behaviour, unchanged)

## Open Questions

None.

## Approval

- [ ] PRD approved on 2026-04-21

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 – Technical Design
- Required Artifacts for Next Step: 02-prd.md
