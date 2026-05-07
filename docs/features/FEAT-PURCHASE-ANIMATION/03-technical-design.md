# Technical Design: FEAT-PURCHASE-ANIMATION

## Bounded Context
- **Context**: list-items
- **Component**: `ItemRow.jsx` (shared by `ListDetailPage` and `SharedListPage`)

---

## Architecture Decision

All delay and animation logic lives in `ItemRow.jsx`. Parents (`ListDetailPage`, `SharedListPage`) remain unchanged. The API call (`onToggle`) fires after the 1.5s delay — the parent's optimistic update already happens immediately at the network level, so we defer only the DOM reorder.

**Wait** — the PRD (AC-3) requires the API call fires immediately, NOT after the delay. Re-reading the PRD:

> **AC-3**: la llamada `PATCH /api/lists/{list}/items/{item}/toggle` se dispara inmediatamente (no espera el delay del sink)

This means `onToggle` (which triggers the API call in the parent) must be called immediately on checkbox change. The 1.5s delay applies only to the visual state change that causes the DOM reorder (sink). The parent controls item position based on `item.is_purchased`; we delay telling the parent to re-evaluate by calling `onToggle` after 1.5s — but that conflicts with AC-3.

**Resolution**: The parent `handleToggle` in `ListDetailPage` makes the API call AND updates local state. Calling `onToggle` after 1.5s delays both. To honor AC-3, we need to split responsibilities:

Option A — `ItemRow` calls API directly + calls `onToggle` after delay (breaks encapsulation)
Option B — Pass two callbacks: `onToggleImmediate` (API) + `onToggleSink` (DOM reorder after delay)
Option C — `ItemRow` calls `onToggle` immediately but suppresses the parent's visual reorder for 1.5s

**Option C is the correct DDD approach**: The parent updates `item.is_purchased` immediately (optimistic), but `ItemRow` uses local `justChecked` state to stay in position for 1.5s and show the animation before the parent's re-render causes it to move.

Because React re-renders `ItemRow` immediately when `item.is_purchased` changes (parent updates optimistically), we need `ItemRow` to:
1. Detect the transition (pending → purchased) and enter "animation mode" for 1.5s
2. During animation mode: show green feedback + prevent visual reorder
3. After 1.5s: exit animation mode → parent's re-render moves item normally

**Revised Design**: `onToggle` fires immediately (honoring AC-3). `ItemRow` uses local `justChecked` state to delay the visual appearance. The key insight: while `justChecked=true`, we override the component's display to look "pending + green" even though `item.is_purchased` is already `true`.

---

## State Machine

```
State: IDLE
  → User checks checkbox
  → Call onToggle(item.id) immediately (API fires)
  → Set justChecked = true
  → Start setTimeout(1500ms)
  → Enter ANIMATING state

State: ANIMATING (justChecked = true)
  → Display: green background + strikethrough (animation feedback)
  → Checkbox: disabled (no double-toggle)
  → After 1500ms timeout fires:
    → Set justChecked = false
    → Enter IDLE state
    → Parent re-renders → item moves to purchased section (sink)

State: IDLE (item.is_purchased = true, justChecked = false)
  → Normal purchased appearance (opacity-60, line-through, gray)
  → User unchecks → same flow in reverse (justCheckedUncheck)
```

For simplicity in V1: the inverse (uncheck) uses the same `justChecked` flag — the parent handles direction based on `item.is_purchased` state.

---

## Component Design

### `resources/js/components/items/ItemRow.jsx`

```jsx
import { useState, useEffect, useRef } from 'react';

export default function ItemRow({ item, onToggle, onEdit, onDelete }) {
    const [justChecked, setJustChecked] = useState(false);
    const timeoutRef = useRef(null);

    // Cleanup on unmount (AC-5)
    useEffect(() => {
        return () => {
            if (timeoutRef.current) clearTimeout(timeoutRef.current);
        };
    }, []);

    function handleToggle() {
        onToggle(item.id); // API fires immediately (AC-3)
        setJustChecked(true);
        timeoutRef.current = setTimeout(() => {
            setJustChecked(false);
        }, 1500);
    }

    const isAnimating = justChecked;
    const showGreen = isAnimating && !item.is_purchased; // checking: was pending
    // For unchecking: isAnimating && item.is_purchased would show "pending" feedback

    const rowClasses = [
        'flex items-center gap-3 py-2 px-3 rounded-lg group',
        'transition-all duration-300',
        isAnimating
            ? (item.is_purchased
                ? 'bg-gray-50'              // unchecking feedback
                : 'bg-green-100')           // checking feedback (AC-1)
            : (item.is_purchased
                ? 'opacity-60 hover:bg-gray-50'
                : 'hover:bg-gray-50'),
    ].join(' ');

    const textClasses = [
        'block truncate transition-all duration-300',
        (isAnimating || item.is_purchased) ? 'line-through text-gray-400' : 'text-gray-900',
    ].join(' ');

    return (
        <div className={rowClasses} data-testid="item-row">
            <input
                type="checkbox"
                checked={item.is_purchased}
                onChange={handleToggle}
                disabled={isAnimating}  // prevent double-toggle during delay
                className="h-5 w-5 text-indigo-600 border-gray-300 rounded cursor-pointer disabled:cursor-not-allowed"
                aria-label={`Marcar ${item.name} como ${item.is_purchased ? 'pendiente' : 'comprado'}`}
            />
            <button onClick={() => onEdit(item)} className="flex-1 text-left min-w-0">
                <span className={textClasses}>
                    {item.name}
                </span>
            </button>
            {/* edit/delete buttons unchanged */}
        </div>
    );
}
```

### CSS Transitions

No new CSS files. Tailwind classes used:
- `transition-all duration-300` — smooth fade on row background and text color
- `bg-green-100` — soft green feedback on purchase (AC-1 ✓)
- `bg-gray-50` — neutral feedback on unpurchase
- `line-through text-gray-400` — strikethrough during animation (AC-1 ✓)
- `disabled:cursor-not-allowed` — visual cue checkbox is locked during delay

The sink animation (item moving to purchased section) is inherent to React re-render after `justChecked` becomes `false`. The parent's list re-orders on next render. Adding `transition-all` to the list container in `ListDetailPage` would enhance the sink, but is out of scope per PRD.

---

## Test Strategy

### File: `resources/js/components/items/ItemRow.test.jsx`

#### Tests that break without changes

| Test | Why it breaks | Fix |
|------|---------------|-----|
| `"calls onToggle when checkbox clicked"` | `onToggle` now fires immediately — passes. But checkbox becomes disabled after click, so subsequent assertions may fail. | Verify `onToggle` called — still works immediately |
| Any test asserting post-click state without advancing timers | `justChecked=true` changes classes — assertions on class names will fail | Add `vi.useFakeTimers()` + `vi.runAllTimers()` |

#### New tests required

```js
describe('purchase animation', () => {
    beforeEach(() => { vi.useFakeTimers(); });
    afterEach(() => { vi.useRealTimers(); });

    it('shows green background immediately on check', async () => {
        // render pending item → click checkbox
        // assert row has bg-green-100 before advancing timers
    });

    it('disables checkbox during animation delay', async () => {
        // click → assert checkbox disabled
        // advance 1500ms → assert checkbox enabled
    });

    it('calls onToggle immediately, not after delay', async () => {
        // click → assert onToggle called immediately
        // do NOT advance timers
    });

    it('removes green background after 1.5s', async () => {
        // click → advance 1500ms → assert bg-green-100 gone
    });

    it('cleans up timeout on unmount (no setState on unmounted)', async () => {
        // click → unmount before 1500ms → no errors
    });
});
```

#### Timer utilities

```js
import { vi } from 'vitest';
import { act } from '@testing-library/react';

// Advance timers inside act() to flush React state updates
await act(async () => {
    vi.advanceTimersByTime(1500);
});
```

---

## Data Flow

```
User clicks checkbox
    │
    ├─→ handleToggle()
    │       ├─→ onToggle(item.id)  ──────────────────→ API call (immediate, AC-3)
    │       ├─→ setJustChecked(true)
    │       └─→ setTimeout(1500ms)
    │
    │ [0ms - 1500ms: ANIMATING]
    │   ItemRow renders with bg-green-100 + line-through
    │   Checkbox disabled
    │
    └─→ [1500ms] timeout fires
            ├─→ setJustChecked(false)
            └─→ React re-renders
                    └─→ item.is_purchased=true + justChecked=false
                            → item appears in purchased section (sink, AC-2)
```

---

## Affected Files

| File | Change Type | Scope |
|------|-------------|-------|
| `resources/js/components/items/ItemRow.jsx` | Modify | Add `justChecked` state, `handleToggle`, animation classes |
| `resources/js/components/items/ItemRow.test.jsx` | Modify | Add fake timer setup, new animation tests |

**No other files change.** `ListDetailPage.jsx` and `SharedListPage.jsx` unchanged — they inherit the behavior automatically (AC-6 ✓).

---

## Implementation Notes

- `useRef` for the timeout ID (not `useState`) — ref changes don't trigger re-renders
- `clearTimeout` in both `useEffect` cleanup AND on new toggle click (cancel previous pending timer if user somehow triggers again)
- The `disabled` attribute on checkbox prevents double-toggle during the 1.5s window (mitigates the double-click risk in PRD)
- `transition-all duration-300` is already used in the project — no new Tailwind config needed

---

## Estimated Effort

| Task | Time |
|------|------|
| Modify `ItemRow.jsx` | 30 min |
| Update + add tests | 45 min |
| Manual QA (ListDetailPage + SharedListPage) | 15 min |
| **Total** | **1.5h** (+ 30 min human time = ~2h) |

---

## Approval
- [ ] Technical Design aprobado
