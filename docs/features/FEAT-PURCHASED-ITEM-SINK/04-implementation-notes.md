# Implementation Notes - FEAT-PURCHASED-ITEM-SINK

## Scope Changes

No scope changes. Implementation exactly matches the technical design.

## Files Changed

| File | Change |
|------|--------|
| `resources/js/pages/SharedListPage.jsx` | Added `pendingCategories` / `purchasedItems` derived values; restructured render to split pending (by category) from purchased ("Ya en el carro") sections |
| `resources/js/pages/SharedListPage.test.jsx` | Added 6 new tests (AC-1 through AC-6) plus fixtures `mixedResponse` and `allPurchasedResponse` |

## Implementation Decisions

- Derived values computed synchronously from existing `items` state — no new state, no effect needed
- `pendingCategories.map` uses block body (`=> {}`) with explicit `return` so the `if (pending.length === 0) return null` early exit works cleanly
- Purchased section uses `data-testid="purchased-section"` and purchased rows use `data-testid="purchased-item-row"` (distinct from `shared-item-row`) for clean test targeting
- Item card JSX in purchased section is a verbatim copy of the pending item card — keeping both in sync manually (same pattern as `ListDetailPage`)

## Known Issues / Technical Debt

None.
