# UI/UX Review: FEAT-REC-SAVE-PARTIAL

## Review Summary

- **Status**: CHANGES REQUIRED (focus trap + focus indicators are blocking; rest is polish)
- **Reviewer**: ui-ux-reviewer (Claude Code, Opus 4.7)
- **Date**: 2026-05-04
- **Tool Used**: Chrome DevTools MCP (`@browser` equivalent) on `http://superia.com.local`, mobile (375x812) and desktop (1280x900); axe-style manual checks via `evaluate_script`; component tests as supplementary evidence (`vitest run` → 30/30 pass).
- **Build note**: Local Vite manifest was serving a pre-S4 bundle (`app-COMxNUU4.js`, still containing `convert-to-list` and the legacy "Crear lista con N productos" copy). Ran `npm run build` to publish the S4 bundle (`app-IhjVDxsN.js`) before reviewing. **Does not affect the implementation under review**, but the user should be aware that local dev needs a fresh build to see the feature.

## Visual Verification (@browser)

Screenshots saved under `docs/features/FEAT-REC-SAVE-PARTIAL/screenshots/`.

| Test | Screenshot | Result |
|------|------------|--------|
| Mobile — default state, all 5 items checked, CTA "Guardar 5 items" | `02-mobile-default-all-checked.png` | OK — matches Wireframe Screen 1 |
| Mobile — partial selection (3 of 5), counter "Guardar 3 items" updates live | `03-mobile-partial-selection.png` | OK — matches Wireframe Screen 2 (dim cards, line-through, counter) |
| Mobile — zero selection, CTA disabled with copy "Selecciona al menos un item" | `04-mobile-zero-selection.png` | OK — matches "CTA Disabled" Component State |
| Mobile — bottom sheet open with 2 active lists + enabled "+ Nueva lista" | `05-mobile-sheet-open.png` | OK overall, but confirm CTA copy deviates from spec (see Findings § Clarity) |
| Mobile — list selected (Cena del finde), confirm CTA enabled | `06-mobile-list-selected.png` | OK selection visual; CTA copy generic "Guardar" instead of `Guardar en "Cena del finde"` |
| Mobile — partial save success banner: `✓ 3 items añadidos a "Cena del finde". Quedan 2 pendientes.` | `07-mobile-after-partial-save.png` | OK — matches Wireframe Screen 5 |
| Mobile — bottom sheet with 3 active lists, "+ Nueva lista" disabled with explanatory text | `08-mobile-sheet-freemium.png` | OK structurally; **contrast issue on disabled subtitle text** (see Findings § Accessibility) |
| Full save → redirect to list `/app/listas/{id}`, items merged correctly | `09-redirect-to-list.png` | OK — AC-10 verified end-to-end |
| Empty state after summary fully consumed | `10-empty-state.png` | OK — copy matches spec |
| Desktop — page layout (max-width 672px content) | `11-desktop-default.png` | OK |
| Desktop — sheet open | `12-desktop-sheet.png` | **Spec deviation**: still rendered as bottom-anchored sheet with top-only rounded corners. Wireframe specified centered modal on >768px. |
| Desktop — keyboard focus on "Cena del finde" list option | `13-desktop-keyboard-focus.png` | **Bug**: no visible focus ring (outline:none, no replacement) |

## Findings

### Discoverability

- **Card area is not interactive — only the 24px checkbox toggles selection.** The card visually reads as a tappable surface (rounded, shadow, padding, name + reason text). New users may try to tap the name and not see anything happen. Wireframe Screens 1/2 also depict interactive checkboxes only, so this is consistent with the spec, but the visual affordance of the card invites a fuller-row tap target.
  - Severity: **Medium** (usability friction, especially on mobile).
  - Suggested fix: make the entire card a `<label>` (or click-on-card delegating to the checkbox via `htmlFor`) so name/reason/quantity also toggle. Alternative: leave click on card area as a no-op but make the touch target on the checkbox 44x44 with a hit slop. Verified via `@browser`: `data-testid="summary-item-0"` has `cursor: auto` and no `onclick`.

- **CTA discoverability is fine.** Sticky bottom CTA "Guardar N items" is visible without scroll on mobile and clearly separated by spacing. `aria-live="polite"` was placed on the CTA `<button>` itself; it correctly announces the new label as the counter changes. Verified.

- **Active lists fetched only on sheet open, no pre-loading indicator.** If `/api/lists` is slow, the user clicks "Guardar 5 items" and nothing visible happens until the request returns. Implementation uses `await fetchActiveLists()` before opening the sheet, no spinner. In LAN it's <100ms (per implementation notes), but on a slow 3G this is a perceived hang.
  - Severity: **Low** (acceptable for a 1-network-hop preload).
  - Suggested fix: optimistic open + skeleton list inside the sheet, OR a brief loading state on the CTA itself.

### Clarity

- **Confirm CTA copy in the sheet is generic "Guardar" instead of "Guardar en \"Cena del finde\"".** Wireframe Screen 3 explicitly shows `Guardar en "Cena del finde"` as the confirm CTA. Implementation hard-codes "Guardar" / "Selecciona una lista" / "Guardando…". This loses a confirmation-step affordance that helps users verify they picked the right destination before committing.
  - Severity: **Medium** (spec deviation, minor clarity loss).
  - Code: `SaveTargetSheet.jsx` line 310 — `{isSubmitting ? 'Guardando…' : canConfirm ? 'Guardar' : 'Selecciona una lista'}`.
  - Suggested fix: when `chosenListId !== null`, show `Guardar en "${listName}"`; when `createNew`, show `Crear nueva lista`.

- **Banner copy deviates slightly from wireframe but improves on it.** Wireframe Screen 5: `Quedan 3 recomendaciones pendientes.` Implementation: `Quedan 2 pendientes.` (i.e., dropped "recomendaciones"). Acceptable; shorter is fine.

- **CTA on the page uses `aria-live="polite"` on the button itself.** Working well in practice (the snapshot reads `button "Guardar 5 items" live="polite"`). Minor concern: announcing every counter change as the user toggles checkboxes is verbose for screen-reader users. Consider scoping the live region to a dedicated counter `<span>` instead of the whole button.
  - Severity: **Low** (polish).

### Safety

- **No destructive actions in this flow.** Confirmed. Cancel button next to confirm is text-only (good — visually subordinate). Backdrop click closes without confirmation, which is acceptable since the user has not committed anything yet.
- **Save button correctly disabled while submitting.** Verified that `closeSheet()` is a no-op when `isSaving` (so users can't dismiss mid-network-call and lose the result). Good.
- **Double-tap protection.** Confirm button disables with `!canConfirm` (which is false while `isSubmitting`). Verified: cannot fire two POSTs.

### Feedback

- **Loading state is correct on confirm.** CTA copy switches to "Guardando…" — verified by reading source (`SaveTargetSheet.jsx`:310). No live-tested due to fast network.
- **Success banner is correct.** `role="status"` + `aria-live="polite"` + green pill background `rgba(111, 251, 190, 0.3)` — verified visually.
- **Error banners are correct (`role="alert"`).** Two error variants verified empirically: a 500 from the backend during my live test produced the generic banner "No se pudieron guardar los items. Inténtalo de nuevo." with the red `#ffdad6` background and `#93000a` text. Banner has `role="alert"` (`aria-live="assertive"`) — appropriate for errors.
- **No specific FREEMIUM_LIMIT / 422 / 404 banners verified live** (those branches were not triggered in the live flow, but the error mapping is correct in `WeeklySummaryPage.jsx` lines 117-127). Component tests cover them.
- **No loading state for the sheet itself when fetching active lists.** Mentioned under Discoverability. Edge case.

### Consistency

- **Design tokens match.** Verified colors via `getComputedStyle`:
  - Primary `#003e54` — used on card check, CTA, sheet header.
  - Accent green `rgba(111, 251, 190, 0.3)` — used on AI tag, success banner, "+ Nueva lista" enabled icon. Consistent.
  - Error `#ffdad6` / `#93000a` — used on error banner. Consistent.
  - Card radius 16px (page) / 14px (sheet rows) — close enough.
  - Font Inter — applied via inline `fontFamily: "'Inter', sans-serif"`. Note: the rest of the app loads Inter via Tailwind/global styles; the inline declaration is OK but redundant — minor polish.
- **Card shadow.** Page items use `0 24px 48px -12px rgba(0,39,54,0.08)`; wireframe used `0 12px 24px -8px rgba(0,39,54,0.08)`. Slightly heavier in implementation. Acceptable.

### Responsive

- **Mobile (375px) — OK.** Bottom-sheet behavior, drag handle, items stack, CTAs full-width. Matches wireframes.
- **Desktop (1280px) — Spec deviation.** The sheet still anchors to the viewport bottom (`alignItems: 'flex-end'`) with only top corners rounded. Wireframe annotation: `Desktop (>768px): mismo componente como modal centrado, max-width 480px`. Implementation does set `maxWidth: 480px` correctly, but does NOT center vertically. The drag handle (a mobile-only affordance) is also visible on desktop, which looks odd in a non-draggable centered modal context.
  - Severity: **Medium** (clear spec deviation; usability still OK because backdrop click + ESC + Cancel all work, but it does not feel like a modal).
  - Suggested fix: media query (or `useMediaQuery` hook) → at `>=768px`, switch `alignItems` to `center`, set `borderRadius: 24px` (all sides), and hide the drag handle.

### Accessibility (high priority)

This is where the implementation has the most concerning issues. Code review explicitly flagged this for S5-UX, and the live audit confirms the concerns.

| Requirement | Verified Status | Severity |
|---|---|---|
| `role="dialog"` | OK (snapshot: `dialog "Guardar en…" modal`) | — |
| `aria-modal="true"` | OK | — |
| `aria-labelledby` linked to `#save-target-sheet-title` | OK | — |
| ESC closes sheet, focus returns to opener | OK — verified live (`focused: BUTTON:convert-to-list` after ESC) | — |
| Backdrop click closes sheet | OK — verified live | — |
| Initial focus moves into the sheet | OK — `activeEl: DIV:save-target-sheet` after open | — |
| Body scroll lock while open | OK — `document.body.style.overflow = 'hidden'` set on open, restored on close | — |
| `aria-pressed` on toggleable list rows | OK — verified live (`pressed` reflected in a11y tree) | — |
| `aria-checked` on summary checkboxes | OK — verified live | — |
| `aria-live` on counter | OK on both page CTA and sheet subtitle | — |
| **Focus trap (forward Tab loops back to first item)** | **FAIL — focus escapes to underlying page after Cancel button**. Tabbing past Cancel moves focus to BODY, then to the page header's "menu" button. This violates WAI-ARIA Authoring Practices for the dialog pattern. | **High** |
| **Focus trap (Shift+Tab from first focusable)** | **FAIL — Shift+Tab from the dialog moves focus to the underlying "Guardar 3 items" CTA on the page (verified live).** | **High** |
| **Visible focus indicator on dialog buttons** | **FAIL — `outline: none` on `save-target-list-*` and `save-target-new-list` buttons; no replacement (`:focus-visible` border, box-shadow, etc.).** Verified via `getComputedStyle` and screenshot 13. | **High** (WCAG 2.4.7 Focus Visible — Level AA) |
| Visible focus indicator on summary checkboxes | OK (browser default outline) | — |
| Color contrast on disabled "+ Nueva lista" subtitle | **FAIL — text `#9ca3af` on `#f7f9fb` background, with the wrapper at `opacity: 0.5` → effective text color ≈ rgb(202,206,213) on rgb(247,249,251), contrast ratio ≈ 1.16:1**. WCAG 1.4.3 technically exempts inactive UI components, but in this case the subtitle is the user's only signal for *why* "+ Nueva lista" is disabled (it's not decorative — it's the explanation). Practically requires legibility per Material 3 disabled-state guidance and inclusive-design best practice. | **High** (practical legibility; WCAG 1.4.3 with the disabled-control caveat) |
| Touch target size on summary checkboxes | **INFO — checkbox is 24×24 CSS px**. WCAG 2.2 SC 2.5.8 Target Size (Minimum) Level AA requires ≥24×24 → this **passes** AA. The argument for upsizing rests on iOS HIG ≥44pt, Material ≥48dp, and WCAG 2.5.5 AAA — best practice rather than a violation. The card row itself is ~88px tall but does not delegate clicks (see Discoverability finding above, which is the actual usability concern). | **Low (best practice)** |
| Semantic radio-group for destination selector | INFO — uses `<button aria-pressed>` instead of `<input type=radio>` / `role="radiogroup"` + `role="radio"`. Both can be acceptable, but radio semantics are stronger for "pick one" UX. | **Low (polish)** |

### UX Specification Compliance

- **AC-1 (default all checked)** — verified live. PASS.
- **AC-2 (toggle, live counter)** — verified live. PASS.
- **AC-3 (CTA disabled at 0)** — verified live. PASS.
- **AC-4 (sheet opens on Save click)** — verified live. PASS.
- **AC-5 (new list creation, redirect)** — not directly tested due to live test environment having `target_list_id` selected; redirect path verified via the full-save case (AC-10).
- **AC-6 (freemium block "+ Nueva lista")** — verified live. PASS structurally; **FAIL on contrast** (above).
- **AC-7+AC-8 (existing-list save, partial banner)** — verified live. PASS.
- **AC-9 (partial mutation, page re-renders with remaining items)** — verified live (5 → 2 after saving 3). PASS.
- **AC-10 (full mutation, redirect, empty state)** — verified live. PASS.

Wireframe deviations:
1. Desktop modal not centered (Responsive § above).
2. Confirm CTA copy in the sheet does not embed the destination name (Clarity § above).
3. Drag handle visible on desktop (Responsive § above).

## Recommendation

- [ ] Approve
- [x] **Request changes** — three blocking a11y issues (focus trap, visible focus, freemium contrast) and one medium spec deviation (desktop centering / CTA copy).
- [ ] N/A

The blocking items are all in `SaveTargetSheet.jsx` and one is in the freemium-disabled block. They are localized fixes; the rest of the implementation is solid and the empirical AC walkthrough passed end-to-end.

## Required Changes

| # | Issue | Screenshot | Severity | Fix |
|---|---|---|---|---|
| 1 | Focus trap missing — Tab past last button (Cancel) escapes to underlying page; Shift+Tab from initial focus also escapes | `13-desktop-keyboard-focus.png` (and behavior verified live) | **High** | Suggested approach: in `SaveTargetSheet.jsx` `useEffect`, extend the existing `keydown` handler with `Tab` / `Shift+Tab` cases that wrap focus to the first/last focusable element inside `dialogRef.current`. The existing test suite does not exercise this; consider supplementing it with focus-trap cases when fixing. |
| 2 | No visible focus indicator on destination buttons (`outline: none`, no replacement) | `13-desktop-keyboard-focus.png` | **High** (WCAG 2.4.7 AA) | Add `:focus-visible` style — e.g. `outline: 2px solid #003e54; outline-offset: 2px;` (or a `boxShadow: 0 0 0 3px rgba(0,62,84,0.4)`) on `save-target-list-*`, `save-target-new-list`, `save-target-confirm`, `save-target-cancel`. Inline styles can switch on `onFocus` / `onBlur` state, or move to a CSS class. |
| 3 | Freemium "+ Nueva lista" subtitle is unreadable (~1.16:1 contrast) when wrapper opacity is 0.5 and text is `#9ca3af` on `#f7f9fb` | `08-mobile-sheet-freemium.png` | **High** (WCAG 1.4.3 AA) | Drop the wrapper `opacity: 0.5` (or move opacity only to the icon and title, not the explanatory subtitle). Alternatively, darken the disabled-subtitle text to `#41484c` or `#71787d`, and lower wrapper opacity to ~0.85. The explanatory text must be readable — it tells users why "+ Nueva lista" is unavailable. |
| 4 | Confirm CTA in sheet shows generic "Guardar" instead of `Guardar en "<list name>"` (wireframe Screen 3) | `06-mobile-list-selected.png` | **Medium** | In `SaveTargetSheet.jsx` line 310, when `chosenListId` is set, look up the matching list from `activeLists` and render `Guardar en "${list.name}"`. When `createNew`, render `Crear nueva lista`. |
| 5 | Desktop sheet anchored to viewport bottom with top-only rounded corners; should be centered modal with full rounded corners (wireframe annotation) | `12-desktop-sheet.png` | **Medium** | Add a media-query / `matchMedia('(min-width: 768px)')` check or simpler: in the backdrop container, switch `alignItems` to `center` at desktop widths, and on the dialog set full `borderRadius: 24px` and hide the drag handle (`<div aria-hidden`). |
| 6 | Whole summary item card not clickable — only the 24px checkbox toggles. Card visually reads as a tappable surface but the rest of the row is inert. | (live, no screenshot) | **Medium** | Wrap the card row in a `<label htmlFor="summary-item-checkbox-${idx}">` (and add an `id` to each checkbox), or attach `onClick={() => toggleItem(idx)}` to the card with `cursor: pointer`. Easiest: `<label>` with `cursor: pointer` and `userSelect: 'none'`. |
| 6b | Checkbox tap target is 24×24 CSS px. Passes WCAG 2.2 AA (SC 2.5.8 Target Size Minimum), but below iOS HIG / Material guidance. | (live) | **Low (best practice)** | If #6 is implemented (whole row toggles), this becomes moot. Otherwise consider adding hit-slop padding around the checkbox to ~44×44. |
| 7 | Drag handle visible on desktop modal (mobile-only affordance) | `12-desktop-sheet.png` | **Low** | Hide handle at `>=768px`. |
| 8 | `aria-live="polite"` on the entire CTA `<button>` re-announces the full label on every counter change. Verbose for screen readers. | (live snapshot) | **Low** | Move `aria-live` to a dedicated `<span>` containing only the counter, leave the button label static-ish. |
| 9 | Inline `fontFamily: "'Inter', sans-serif"` declarations duplicate global font setup. | source | **Low (polish)** | Drop the inline `fontFamily` since the app's root already loads Inter; keep only on the button overrides if there are conflicting global resets. |
| 10 | Local Vite dev environment was serving a stale (pre-S4) bundle. Required `npm run build` to reflect current source. Implementation note: ensure CI / deploy pipeline rebuilds, and consider adding a vite-dev-server hot-reload script to the README so future reviewers don't hit this. | (env note, not a code bug) | **Info** | Document in `04-implementation-notes.md § Known Issues` or in the project README. |

## Evidence Inventory

- Live walkthrough on `http://superia.com.local/app/resumen` — user `pelrock@gmail.com` (id 145), test summary id 1998 (5 items → 3 items after partial save → 0 items after full save).
- Mobile viewport: 375x812.
- Desktop viewport: 1280x900.
- Component tests: `npx vitest run resources/js/components/weekly-summary resources/js/pages/WeeklySummaryPage.test.jsx` → 30/30 PASS (build aligned with source).
- Screenshots: `docs/features/FEAT-REC-SAVE-PARTIAL/screenshots/01-13`.

## Reviewer Notes

The implementation is functionally complete and the test coverage is excellent (30 frontend component tests + 84 backend tests per implementation notes). The blocking issues are concentrated in the new `SaveTargetSheet` component's accessibility — specifically the focus management contract that distinguishes a real ARIA dialog from a styled overlay. The fixes are small and localized; I'd estimate ~2-3 hours including new tests for the focus trap.
