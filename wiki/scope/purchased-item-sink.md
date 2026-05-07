# FEAT-PURCHASED-ITEM-SINK — Purchased Item Sort in Shared Lists

**Complexity:** LOW | **Status:** S5-PASS (Code + Security + UX)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-PS1 | Pending items shown at top, purchased at bottom in SharedListPage | Implemented |
| HU-PS2 | Item moves to purchased section immediately on toggle | Implemented |
| HU-PS3 | "Ya en el carro" section header when items purchased | Implemented |
| HU-PS4 | No empty sections (hide pending section if 0 pending, purchased if 0 purchased) | Implemented |
| HU-PS5 | Un-toggle moves item back to pending | Implemented |

## Design Decisions

- Pure frontend render restructure — replicates pattern from `ListDetailPage`
- Derived values (`pendingCategories`, `purchasedItems`) computed synchronously from state
- No new API endpoints, no new state
- Manual item card duplication (acceptable per design)

## Deviations

None — pure UI change, pattern reuse.

## Review Findings

- 23/23 tests passing
- No new auth/authz surface (existing share-token context unchanged)
- Visual states verified in browser: section headers, mobile responsive
