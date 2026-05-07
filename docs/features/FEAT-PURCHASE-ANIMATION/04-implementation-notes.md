# Implementation Notes - FEAT-PURCHASE-ANIMATION

## Scope Changes

| Date | Type | Description | Impact |
|------|------|-------------|--------|
| 2026-04-25 | Design deviation | `ItemRow.jsx` not created; animation inlined into both pages | Tech debt: duplication |
| 2026-04-25 | Timing | 1.5s green + 300ms exit = 1.8s total before re-fetch | AC-2 satisfied |

## Implementation Decisions

### ItemRow.jsx not created

The technical design proposed a shared `ItemRow.jsx` component. `ListDetailPage` and `SharedListPage` have divergent markup, styles, and edit-mode logic, making a shared component add more complexity than it removes. Animation logic was inlined into both pages.

**Tech debt**: Duplication accepted. Extract if rendering ever converges.

### Two-phase animation (1.5s green + 300ms exit)

PRD specified 1.5s before the item sinks. Implementation adds a 300ms exit animation (opacity fade + height collapse) for AC-2 smooth sink. The API call (AC-3) fires at 0ms via `Promise.all([api.patch, timerPromise(1500)])`. Total elapsed before re-fetch: ~1800ms.

### `exitingItems` Set

Both pages gained a second animation Set (`exitingItems`) alongside `justCheckedItems`. During the exit phase, the item collapses in place before the list re-fetches and reorders.

### `isMountedRef` in isolated `useEffect([], [])`

The cleanup effect (setting `isMountedRef.current = false`) is in a no-deps effect, separate from the data-fetch effect. Prevents the mount guard from firing on list navigation (id changes), which would permanently disable all toggles.

### `disabled` guard covers both animation phases

`disabled={isAnimating || isExiting}` — both the 1.5s green phase and the 300ms exit phase lock the checkbox to prevent double-toggle.

## Files Modified

| File | Change |
|------|--------|
| `resources/js/pages/ListDetailPage.jsx` | Added `exitingItems` state, split `useEffect`, exit animation in `handleToggle` and `renderItemCard` |
| `resources/js/pages/SharedListPage.jsx` | Same pattern — `exitingItems`, exit phase in `handleToggle`, pending and purchased loops updated |
| `resources/js/pages/ListDetailPage.test.jsx` | Added 5 purchase animation tests with fake timers |
| `resources/js/pages/SharedListPage.test.jsx` | Added 5 purchase animation tests with fake timers |

## Known Issues / Technical Debt

- Animation logic duplicated between `ListDetailPage` and `SharedListPage`. Extract to shared hook or component when rendering structures converge.
