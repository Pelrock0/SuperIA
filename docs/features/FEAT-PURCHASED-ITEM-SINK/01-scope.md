# Scope Analysis: FEAT-PURCHASED-ITEM-SINK

## Feature Request

When a user marks a shopping list item as purchased (checks it off), it should visually move to the bottom of the list so that non-purchased items remain at the top. Without this, the user must scroll through purchased items to find what's left to buy.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | MEDIUM |
| Estimated Effort | 3 hours |
| Confidence | High |

## Justification

**Current state after code inspection:**

- `ListDetailPage` already implements the purchased/pending separation correctly:
  - Pending items rendered per category (filtered to `!is_purchased`)
  - Purchased items shown in a "Ya en el carro" collapsible section at the bottom
  - `handleToggle` calls `fetchList()` on every toggle — list re-orders after each action

- `SharedListPage` does NOT implement this separation:
  - All items rendered together from `categoryKeys` with no pending/purchased split
  - Purchased items only get visual styling (opacity, strikethrough) but stay in their original position
  - This is the gap causing the user's reported problem

**Complexity justification: MEDIUM** — modifies existing UI behaviour in `SharedListPage`, affects the shared-list shopping flow (used by collaborators), and requires replicating the purchased/pending split pattern from `ListDetailPage`.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | Pattern already proven in `ListDetailPage` — reference implementation exists |
| Data | Low | No schema changes. Backend already orders by `is_purchased, category, position` |
| Security | Low | No access control changes. SharedListPage operates under existing share-token auth |
| Performance | Low | No new queries. List is already re-fetched after each toggle |
| Operational | Low | Frontend-only change. No deploy complexity |

## Affected Areas

- `resources/js/pages/SharedListPage.jsx` — add pending/purchased split matching `ListDetailPage` pattern
- `resources/js/pages/SharedListPage.test.jsx` — update/add tests for new rendering behaviour

## Open Questions

None.

## Recommendation

- [x] Require PRD (MEDIUM → STEP 2)

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)
