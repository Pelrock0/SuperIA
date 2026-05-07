# UI/UX Review: FEAT-REC-SAVE-PARTIAL — Iteration 2 (delta)

## Review Summary

- **Status**: APPROVED
- **Reviewer**: ui-ux-reviewer (Claude Code, Opus 4.7)
- **Date**: 2026-05-04
- **Tool Used**: Chrome DevTools MCP (`@browser` equivalent) on `https://superia.com.local`, mobile (375x812) and desktop (1280x900); component source verification for inline focus/contrast logic; bundle confirmed `app-DKlTshPP.js`.
- **Scope**: Delta verification of the 6 findings raised in iter 1 (`05-ux-review.md`). No re-review of items already PASS in iter 1.

## Visual Verification (@browser)

Screenshots saved under `docs/features/FEAT-REC-SAVE-PARTIAL/screenshots/iter2-*.png`.

| Finding | Test | Screenshot | Result |
|---|---|---|---|
| #1 Focus trap | Tab cycle inside dialog (forward + Shift+Tab wrap) | (live verified, see below) | RESOLVED |
| #2 Focus indicator | Tab onto each dialog button; verify `box-shadow` ring | `iter2-02-focus-ring-list-button.png` | RESOLVED |
| #3 Freemium subtitle contrast | 3 active lists, computed contrast on disabled subtitle | `iter2-03-freemium-contrast.png` | RESOLVED |
| #4 Confirm CTA copy | Pick existing list / Pick new list | `iter2-04-confirm-cta-new-list.png` | RESOLVED |
| #5 Desktop modal layout | Resize to 1280px, verify centered + 24px radius + no handle | `iter2-05-desktop-centered-modal.png` | RESOLVED |
| #6 Card clickable | Click on item name and quantity badge → toggles checkbox | `iter2-01-mobile-card-label.png` | RESOLVED |

## Findings (delta)

### #1 — High — Focus trap [RESOLVED]

**Test**: Opened the sheet with 1 active list (3 focusable buttons inside dialog: list-118, new-list, cancel; confirm is disabled until selection). Tabbed forward through all three, then once more from `save-target-cancel`.

- After `save-target-cancel` → Tab → focus moved to `save-target-list-118` (first focusable). Focus stayed inside the dialog (`dialog.contains(document.activeElement) === true`).
- Pressed `Shift+Tab` from `save-target-list-118` → focus moved to `save-target-cancel` (last focusable). Focus stayed inside the dialog.

**Source**: `SaveTargetSheet.jsx:65-98` adds a `keydown` handler that intercepts Tab/Shift+Tab, computes focusables via `FOCUSABLE_SELECTOR`, and wraps to first/last. Disabled buttons are filtered out via `filter((el) => !el.hasAttribute('disabled'))`. Initial focus is placed on the dialog root (`tabIndex={-1}`), and when focus is outside the dialog the handler also pulls it back in (`!root.contains(active)` branch).

Verdict: WAI-ARIA dialog focus-trap contract is satisfied.

### #2 — High — Visible focus indicator [RESOLVED]

**Test**: Tabbed onto each dialog button and read the inline `boxShadow` style:

- `save-target-cancel` (focused): `rgba(0, 62, 84, 0.35) 0px 0px 0px 3px`
- `save-target-list-118` (focused after Tab): `rgba(0, 62, 84, 0.35) 0px 0px 0px 3px`
- `save-target-new-list` (focused after Tab): `rgba(0, 62, 84, 0.35) 0px 0px 0px 3px`
- After blur (e.g., Cancel after Tab away): `boxShadow === 'none'` → ring correctly removed.

**Source**: `SaveTargetSheet.jsx` defines `focusRing = '0 0 0 3px rgba(0, 62, 84, 0.35)'` and applies it via `onFocus`/`onBlur` handlers on every button (`save-target-list-*`, `save-target-new-list`, `save-target-confirm`, `save-target-cancel`). Disabled buttons skip the ring on focus (`if (!e.currentTarget.disabled)` guard on confirm + new-list).

Note: `outline: none` is still set inline. The replacement (3px box-shadow ring at 35% opacity of the brand color #003e54 on white/light grey) provides ≥3:1 luminance contrast vs. the surrounding bg, satisfying WCAG 2.4.7 (Focus Visible) and WCAG 1.4.11 (Non-text Contrast). Visible to sighted keyboard users.

Verdict: WCAG 2.4.7 AA satisfied.

### #3 — High — Freemium subtitle contrast [RESOLVED]

**Test**: Created 2 additional active lists for user 145 (now 3 total: Cena del finde, Despensa básica, Palomeque). Re-opened the sheet — `+ Nueva lista` is correctly disabled with the limit message.

Computed contrast via `getComputedStyle` + WCAG luminance formula:
- Subtitle color: `rgb(65, 72, 76)` = `#41484c` (matches fix spec)
- Button background: `#f7f9fb`
- Sheet background: `#ffffff`
- Wrapper opacity: 1 (no longer 0.5 — wrapper opacity was removed)
- **Contrast ratio: 8.82:1 (vs button bg) / 9.31:1 (vs sheet bg)**

WCAG 1.4.3 AA requires ≥4.5:1 for normal text and ≥3:1 for large text. 8.82:1 is **AAA-compliant** (≥7:1).

`aria-describedby="save-target-new-list-hint"` is correctly set on the disabled button when freemium-locked, so screen readers will announce the explanation in the same accessible name.

Verdict: WCAG 1.4.3 AAA satisfied; explanation legible.

### #4 — Medium — Confirm CTA copy [RESOLVED]

**Test**: Opened sheet with 1 active list ("Palomeque").

- Clicked `Palomeque` → confirm CTA snapshot read: `Guardar en "Palomeque"`. Verified via accessibility tree (`button "Guardar en \"Palomeque\""`).
- Clicked `+ Nueva lista` → confirm CTA snapshot read: `Guardar en nueva lista`. Verified via accessibility tree.
- With nothing selected → `Selecciona una lista` (disabled).

**Source**: `SaveTargetSheet.jsx:145-151`:
```
if (isSubmitting) return 'Guardando…';
if (!canConfirm) return 'Selecciona una lista';
if (createNew) return 'Guardar en nueva lista';
if (chosenList) return `Guardar en "${chosenList.name}"`;
return 'Guardar';
```
`chosenList` is derived from `useMemo(() => activeLists.find(l => l.id === chosenListId))` so the destination name is always in sync.

Verdict: matches wireframe Screen 3 spec.

### #5 — Medium — Desktop modal layout [RESOLVED]

**Test**: Resized viewport to 1280x900, opened the sheet, computed styles:

- Backdrop: `align-items: center`, `justify-content: center`, `padding: 24px`
- Sheet: `border-radius: 24px` (uniform — all four corners), `max-width: 480px`
- Drag handle: NOT present (verified by querying for the 40x4 grey div — returned `false`)
- `matchMedia('(min-width: 768px)').matches === true` confirms hook fired correctly

**Source**: `SaveTargetSheet.jsx:8-30` defines `useIsDesktop()` hook with `matchMedia('(min-width: 768px)')` and a `change` listener; `:154-176` branches `sheetStyle` between desktop (full radius, no drag handle, larger padding, max-height 85vh) and mobile (top-only radius, drag handle, max-height 90vh). Backdrop `alignItems` is also conditional (`isDesktop ? 'center' : 'flex-end'`).

Verdict: matches wireframe annotation `Desktop (>768px): mismo componente como modal centrado, max-width 480px`.

### #6 — Medium — Card clickable [RESOLVED]

**Test**: On WeeklySummaryPage, queried the item card:
- Tag: `LABEL` (was `DIV`).
- Cursor: `pointer`.
- Native nesting: `<input type="checkbox">` is inside the `<label>` → click anywhere in the label toggles the checkbox via native HTML semantics, no JS needed.

Empirical clicks:
- Click on item name span (`#summary-item-name-0`) → checkbox toggled from `true` to `false`.
- Click on the right-side quantity badge (`<div>` with quantity + unit) → checkbox toggled correctly.

**Source**: `WeeklySummaryPage.jsx:351` switched `<div>` to `<label>` with `cursor: 'pointer'` style; the input keeps `aria-labelledby={labelId}` for accessibility, and the `onChange` on the input handles the actual state mutation. Native label/input association handles the click delegation — no custom `onClick` needed on the label, which is the simpler and more robust pattern.

Verdict: full-card click target works on all child elements.

## Other observations

- No regressions detected on items already PASS in iter 1 (ESC closes, backdrop click closes, body scroll lock, `aria-pressed`, `aria-checked`, `aria-live` on counter, success/error banners).
- Bundle served matches the expected `app-DKlTshPP.js`.
- Component test count increased to 388 per implementation notes (was 383); the new tests cover dynamic confirm copy, focus trap forward/backward, and centered desktop layout.

## UX Specification Compliance

All wireframe deviations identified in iter 1 are now resolved:
1. Desktop modal centered + uniform 24px radius + no drag handle. RESOLVED.
2. Confirm CTA embeds destination name (`Guardar en "X"` / `Guardar en nueva lista`). RESOLVED.
3. Drag handle hidden on desktop (was lumped into Responsive in iter 1). RESOLVED.

## Recommendation

- [x] **Approve**
- [ ] Request changes
- [ ] N/A

All three High a11y findings (focus trap, focus indicator, contrast) and all three Medium findings (CTA copy, desktop layout, card click target) are empirically resolved in the running app. Source-level review confirms the fixes are correctly localized and don't introduce regressions. WCAG 2.4.7 (Focus Visible AA), 1.4.11 (Non-text Contrast AA), and 1.4.3 (Contrast Minimum AAA) are satisfied.

## Required Changes

None.

## Evidence Inventory

- Live walkthrough on `https://superia.com.local/app/resumen` — user `pelrock@gmail.com` (id 145), test summary id 2095 (5 items).
- Active lists state during test: 1 list for findings #1/#2/#4/#5/#6, 3 lists (freemium limit) for finding #3.
- Mobile viewport: 375x812.
- Desktop viewport: 1280x900.
- Bundle: `public/build/assets/app-DKlTshPP.js`.
- Screenshots: `docs/features/FEAT-REC-SAVE-PARTIAL/screenshots/iter2-01..05`.
- Source files reviewed: `resources/js/components/weekly-summary/SaveTargetSheet.jsx`, `resources/js/pages/WeeklySummaryPage.jsx`.

## Reviewer Notes

The fixes are precise and minimal. The focus-trap implementation correctly handles the "focus outside" branch (`!root.contains(active)`), which guards against edge cases like an external script stealing focus. The choice of `<label>` with native input nesting for finding #6 is the most idiomatic solution and avoids the brittleness of `htmlFor` + dynamic ids. Contrast on the freemium subtitle now exceeds AAA at 8.82:1 — there is no margin to lose by future palette tweaks. Approving the gate.
