# Technical Design: FEAT-PURCHASED-ITEM-SINK

## Overview

`SharedListPage` currently renders all items grouped by category with no separation between purchased and non-purchased items. Purchased items receive visual styling (strikethrough, opacity) but stay in their original positions.

The fix is a pure frontend change: derive two computed values from the existing `items` state — `pendingCategories` and `purchasedItems` — and restructure the render output to show pending items first (grouped by category) and purchased items second (in a "Ya en el carro" section). This pattern is already in production in `ListDetailPage` and is copied verbatim.

No backend changes, no API changes, no migrations.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Modules |
|-------|-----------------|-------------|
| Backend | N/A — unchanged | — |
| API | N/A — unchanged | — |
| Frontend (state) | Derive `pendingCategories` and `purchasedItems` from existing `items` state | `SharedListPage.jsx` |
| Frontend (render) | Render pending section + purchased section using existing item card renderer | `SharedListPage.jsx` |

### Data Flow

```
API response → setItems(data.items)  [unchanged]
     ↓
items: { category: [ListItem, ...], ... }  [unchanged]
     ↓
pendingCategories = categoryKeys.filter(k => items[k].some(i => !i.is_purchased))
purchasedItems    = categoryKeys.flatMap(k => items[k].filter(i => i.is_purchased))
     ↓
Render:
  [pending section]
    pendingCategories.map(category =>
      items[category].filter(i => !i.is_purchased).map(renderItem)
    )
  [purchased section — only if purchasedItems.length > 0]
    "Ya en el carro" header
    purchasedItems.map(renderItem)
```

### Transaction Boundaries

Not applicable — read-only rendering, no state writes in this change.

## Data Model

### New Tables/Collections
None.

### Migrations
None.

### API Changes
None. The existing endpoint already returns items ordered by `is_purchased, category, position`. The frontend was simply not using that ordering for separation.

## Performance

### Query Optimization
Not applicable — no new queries.

### Caching Strategy
Not applicable.

### Notes
`pendingCategories` and `purchasedItems` are derived synchronously from `items` state in the render function — O(n) over the item list, negligible for typical list sizes (< 100 items).

## Security Considerations

No changes to authentication or authorization. `SharedListPage` continues to operate under the existing share-token context. No new data is exposed.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Replicate `ListDetailPage` pattern directly | Reference implementation exists, proven in production, consistent UX across pages | None | **Selected** |
| CSS-only sort (`order` property based on `is_purchased`) | No JS logic change | Items still in DOM order, screen readers read them in original order, fragile | Rejected — semantically incorrect |
| Optimistic update (move item instantly before re-fetch) | Faster perceived response | Adds state management complexity; re-fetch already fast enough | Rejected — not in scope, adds risk |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Existing `SharedListPage` tests break due to new DOM structure | Low | High | Update tests to match new structure; new tests for AC-1 through AC-6 |
| Category grouping behaviour changes | Low | Low | Pending items still grouped by category exactly as before — only the purchased items are extracted |

## Open Questions

None.

## Implementation Notes

1. Add two derived values after `categoryKeys` is defined:
   ```js
   const pendingCategories = categoryKeys.filter(k => items[k].some(i => !i.is_purchased));
   const purchasedItems    = categoryKeys.reduce((acc, k) => acc.concat(items[k].filter(i => i.is_purchased)), []);
   ```
2. Replace the existing `categoryKeys.map(...)` render block with:
   - `pendingCategories.map(category => items[category].filter(i => !i.is_purchased).map(renderItem))`
   - A conditional "Ya en el carro" section at the bottom (copy from `ListDetailPage` lines 711–745)
3. Update `SharedListPage.test.jsx` — existing tests that assert item positions in the list will need to account for the new section structure

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 – Implementation
- Required Artifacts: 02-prd.md, 03-technical-design.md
