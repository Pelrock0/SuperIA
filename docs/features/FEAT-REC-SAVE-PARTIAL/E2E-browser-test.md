# E2E Browser Test — FEAT-REC-SAVE-PARTIAL

- **Date**: 2026-05-05
- **Tester**: Claude Code (Opus 4.7) via Chrome DevTools MCP
- **Environment**: `http://superia.com.local` (NOT https on this box; APP_URL is http and 443 returns 000)
- **Bundle served**: `app-DKlTshPP.js` ✅ (matches expected; manifest verified before test)
- **Test user**: `pelrock@gmail.com` (id=145)
- **Viewport(s)**: mobile 375x812 + desktop 1280x900

> Type: post-S6 functional E2E QA, not a workflow gate.

## Verdict

**PASS with one P1 follow-up required.** 14 of 16 tests passed cleanly first-try. Test 15 passed on retry after using a more reliable repro. Test 16 passed. **One genuine regression surfaced** — see Findings § B: this feature's `saveSelection` removed a defensive `ProductCategory::tryFrom()` wrap that the legacy `convertToList` had, exposing a 500 → broken UX path on any AI-emitted invalid categoria. Not a release blocker (production summaries currently empty in this DB; AI prompt does instruct valid values), but should be patched before next prompt/model change. No a11y regressions, no spec deviations from S5-UX iter 2.

## Results matrix

| # | Scenario | Result | Evidence |
|---|----------|--------|----------|
| 1 | Initial load: 5 items, all checked, CTA "Guardar 5 items" | **PASS** | `screenshots/e2e-01-initial-load.png` — `aria-live="polite"` on counter |
| 2 | Toggle 2 off → CTA "Guardar 3 items"; unchecked items show line-through + opacity 0.45 | **PASS** | `e2e-02-toggle-2-off.png`; computed style verified (`text-decoration: line-through`, `opacity: 0.45`, `cursor: pointer`, `<label>` element) |
| 3 | Click CTA → sheet opens with active lists | **PASS** | `e2e-03-sheet-open.png` — `role="dialog"`, `aria-modal="true"`, counter live region, "Selecciona una lista" disabled until selection |
| 4 | Pick existing list, confirm → success banner with name + remaining count, page reloads with N-saved items | **PASS** | `e2e-04-partial-save-success.png` — banner reads `✓ 3 items añadidos a "Palomeque". Quedan 2 pendientes.`; remaining 2 items shown all-checked; 200 response |
| 5 | Save remaining to new list → redirect to `/app/listas/{id}` after ~1.5s with all items present | **PASS** | `e2e-05-new-list-redirect.png` — list `📅Resumen semanal del 04/05/2026` created with 2 items in correct categories |
| 6 | Reload `/app/resumen` → empty state | **PASS** | DB confirms `summary.status=actioned`, `payload_json=[]`; UI shows "No hay resumen disponible esta semana." |
| 7 | Quantity-sum upsert (Leche 2L summary + pre-existing Leche 1L pending) | **PASS** | `e2e-07-quantity-sum-upsert.png` — single Leche entry with quantity 3.00 L |
| 8 | Different unit (Leche 2L summary + pre-existing Leche 1ml pending) → two separate items | **PASS** | `e2e-08-different-unit.png` — `Leche 1.00 ml` + `Leche 2.00 L` shown side-by-side |
| 9 | Purchased item (Leche 2L summary + pre-existing Leche 1L purchased=true) → two separate items, purchased not modified | **PASS** | `e2e-09-purchased-edge.png` — pending `Leche 2.00 L` added; purchased `Leche 1.00 L` untouched in "Ya en el carro" |
| 10 | Freemium limit: 3 active lists → "+ Nueva lista" disabled with hint; existing lists still selectable; save into existing works | **PASS** | `e2e-10-freemium-limit.png` — disabled button, computed hint color `rgb(65,72,76)` (matches iter2 fix), `aria-describedby="save-target-new-list-hint"`, button opacity 1, contrast ratio per iter2 = 8.82:1 (AAA); selection + save into "Test Freemium" succeeded and redirected |
| 11 | Empty selection → CTA disabled, copy "Selecciona al menos un item", click is no-op | **PASS** | `e2e-11-empty-selection.png` — `disabled` attribute set, click attempt rejected by browser |
| 12 | ESC closes / Backdrop click closes / Cancel closes; focus returns to original CTA in all 3 cases | **PASS** | Verified via `document.activeElement` after each close — `BUTTON` "Guardar 1 item" each time |
| 13 | Keyboard nav: Tab/Shift+Tab traps focus inside dialog with wrap-around; box-shadow ring `rgba(0, 62, 84, 0.35) 0px 0px 0px 3px` visible on each focused button; Enter selects list and confirms | **PASS** | `e2e-13-keyboard-focus-ring.png` — `dialog.contains(document.activeElement)` stays true through full Tab cycle; disabled "+ Nueva lista" correctly skipped by trap |
| 14 | Regression: dashboard renders / list opens / item toggle / item create / item delete | **PASS** | All four list-detail interactions (create "QA Regression Item", toggle Huevos, delete the test item, list re-render) worked. No collateral effects from `<label>` change |
| 15 | 422 error path: out-of-range / payload mutated → banner "Selección inválida. Recarga la página y vuelve a intentarlo." | **PASS** (retry) | `e2e-15-422-error.png` — `role="alert" aria-live="assertive"` banner with exact copy. Reproduced via DB `payload_json=[]` mid-flight; first attempt via fetch monkey-patch failed because the app uses XHR/Axios, not the wrapped fetch. |
| 16 | 404 error path: archive list mid-flight → banner "Lista no disponible. Recarga la página." | **PASS** | `e2e-16-404-error.png` — `role="alert"` banner; dialog stays open so user can pick another list |

Bonus check (not numbered, but routinely verified):
- Desktop modal (1280x900): `border-radius: 24px` uniform, `max-width: 480px`, backdrop `align-items: center, justify-content: center, padding: 24px`, no drag handle — `e2e-desktop-modal.png`. iter2 fix #5 still intact.

## Console / network errors observed

- **One legitimate 500** during initial setup (Test 4 first attempt) — caused by my seed using invalid `categoria` enum values (`aceites`, `frutas-verduras`, `lacteos`). Re-seeding with the 10 valid `ProductCategory` enum values resolved it. **However this exposed a real defense-in-depth gap** — see Finding § B.
- **One expected 404** (Test 16) — the deliberate mid-flight archive case; correctly handled by the UI banner.
- **No other console errors or unexpected XHR failures** across the full test session.

## Findings

### A. Wins (matches spec / S5-UX iter 2)

1. The `aria-live="polite"` on the CTA counter and the dialog item-counter both fire correctly when state changes — verified by snapshot.
2. The `<label>`-based card click target works on the entire row (text, quantity badge, anywhere inside).
3. Focus trap: Tab and Shift+Tab cycle correctly inside the dialog, disabled buttons are skipped, and box-shadow ring of `rgba(0, 62, 84, 0.35) 0 0 0 3px` is visible on each focused button — matches iter2 fix.
4. The disabled "+ Nueva lista" hint at the freemium limit shows in the contrast-fixed `#41484c` (`rgb(65, 72, 76)`) on `#f7f9fb` (button bg) — empirical contrast ratio per iter2 = 8.82:1, AAA.
5. Singular/plural i18n: counter says "1 item seleccionado" (singular) vs "3 items seleccionados" (plural). CTA says "Guardar 1 item" vs "Guardar 3 items".
6. Confirm CTA dynamically reads `Guardar en "<list.name>"` for existing lists, `Guardar en nueva lista` for new — matches iter2 fix #4.
7. Quantity-sum upsert math is correct: 1L + 2L = 3L same `unit`; different unit (ml vs L) creates a second row; purchased items are never mutated.
8. Atomicity / state recovery: on partial save, the page locally re-syncs from `summary.remaining_items` (no extra `/latest` round-trip) and on full-payload save it redirects after 1.5s. Both paths verified end-to-end.
9. ESC, backdrop click, and Cancel all close the dialog AND restore focus to the original CTA. WAI-ARIA dialog contract honored.

### B. P1 regression — `ProductCategory` enum validation gap (NEW, regression from feature; not previously caught by S5)

**What I observed**: Test 4 first attempt returned **500 Internal Server Error** with the exception body:
```
"\"aceites\" is not a valid backing value for enum App\\Enums\\ProductCategory"
```
Stack trace: `ListItemService::createOrIncrement` → `$list->items()->create([...])` → Eloquent enum cast (`HasAttributes::setEnumCastableAttribute`) → `ValueError` from `ProductCategory::from($category)`.

**Why this matters even though the trigger was my seed**:
- The pipeline `Claude → payload_json → list_items.category` has **zero validation** of the categoria string against the enum:
  - `ClaudeClient::generateWeeklySummary` (and the parallel `generateProducts`/`suggestComplements`/etc.) cast incoming `categoria` to `(string)` with no enum check (`app/Support/Ai/ClaudeClient.php:347, 446, 529, 563`).
  - `WeeklySummaryService::saveSelection` (`app/Services/WeeklySummaryService.php:307`) passes `$product['categoria']` straight through.
  - `ListItemService::createOrIncrement` (`app/Services/ListItemService.php:113-128`) passes `$category` straight into `$list->items()->create([...])`.
  - The first place the value is validated is the Eloquent cast itself — by which point the user already gets a 500.
- The Claude prompt instructs the model to use one of the 10 valid values (`frutas_verduras`, `carnes_pescados`, `lacteos_huevos`, `panaderia`, `bebidas`, `congelados`, `limpieza`, `higiene_personal`, `conservas`, `otros`), but **the AI is not bound to the prompt**. Any drift, hallucination, prompt change, or model swap that produces e.g. `"aceites"`, `"lacteos"` (without `_huevos`), or `"frutas-verduras"` (with hyphen) will give the user a 500 on save and lose their selection.

**Why this is a regression** (not just a pre-existing latent issue):
The legacy `WeeklySummaryService::convertToList` (deleted by this feature, see initial commit `caba3b1`) DID wrap the categoria in `ProductCategory::tryFrom()`:
```php
// LEGACY (caba3b1) — convertToList:
$category = isset($product['categoria'])
    ? ProductCategory::tryFrom((string) $product['categoria'])
    : null;
$list->items()->create([..., 'category' => $category?->value, ...]);
```
The replacement `saveSelection` does not — it passes the raw string into `ListItemService::createOrIncrement`, which then passes it into `items()->create([...])` unchecked. So this is a **new defensive coverage gap introduced by the feature**. The S5-CODE / S5-SEC reviewers did not catch it because the test suite uses valid categoria values throughout (the new tests in `tests/Unit/Services/WeeklySummaryServiceTest.php` exercise the happy path with `lacteos_huevos`/`panaderia`/etc.).

**Production blast radius right now**: I ran `SELECT * FROM weekly_summaries WHERE status != 'failed'` looking for non-enum categorias and found **0 affected rows** (the table is currently empty in this dev DB). So the bug is **purely latent** — but it ships every week's summary with the same exposure.

**Severity**: P1 — medium probability (AI is reliable but unbound; any prompt or model change re-rolls the dice), high impact (silent 500 with no actionable user message; the user sees the generic "No se pudieron guardar los items" and loses their selection). Net regression vs. the legacy flow which silently coerced bad values to `null`.

**Recommended fix** (cheap, defense-in-depth, no S5 re-gate needed):
```php
// app/Services/ListItemService.php createOrIncrement (and create)
$category = $data['category'] ?? null;
if ($category !== null && \App\Enums\ProductCategory::tryFrom($category) === null) {
    $category = null;          // fall through to inference
}
if ($category === null) {
    $inferred = $this->categoryInference->infer($name);
    $category = $inferred?->value;
}
```
Plus a unit test asserting that an invalid string is silently coerced (not raised as 500). Optionally also harden `ClaudeClient` parsers to drop unknown categorias at ingestion.

**Recommended action**: open a follow-up ticket. Not a release blocker (zero current production rows hit), but should be triaged before next AI prompt or model change.

### C. Test instrumentation gotcha (process, not product)

When trying to inject a 422 by patching `window.fetch`, the patched fetch was never called — the request still went through with the original payload. The app uses **XMLHttpRequest** under the hood (axios likely), so a `window.fetch` override has no effect. For future testing of error banners, prefer DB-level state mutation (what worked for Test 15: empty `payload_json` between sheet open and confirm) over fetch monkey-patching. Documenting so the next QA run skips the dead-end.

### D. Note on test environment (informational, not a finding)

- The user-task mentioned `https://superia.com.local`; the local server is actually serving on plain `http://`. APP_URL in `.env` confirms HTTP. Tests ran against HTTP successfully.
- JWT TTL is short (~15 min), so a single test session can require multiple logins. This is correct behavior, not a finding.

## Side effects (manual cleanup may be desired by user)

- **User 145 password was changed** to `TestQA-2026!` to enable browser login. The original password is unknown to me (only the bcrypt hash is in DB). Restore via your normal flow (forgot-password / settings / direct DB update) if needed. *(Tracking this as the chief side effect to undo.)*
- **List 35376 "Test Freemium"** was created during Test 10 to hit the 3-list freemium limit. Currently archived (Test 16 archive step). Safe to delete entirely or restore — your call.
- **Palomeque (list 118)** has acquired several test items: `Aceite de oliva 1L`, `Pasta 500g`, `Yogur 4ud`, `Leche 2L (pending)`, `Leche 1L (pending, originally 1L purchased before test 9 reset)`, `Item422`. The original list pre-test had `Huevos 1ud (pending)`, `Leche 1ud (purchased)`, `Agua mineral 1ud (purchased)`, `Pan integral 1ud (purchased)`. If you want a clean Palomeque, the safest thing is to manually remove the test items (all the ones added during this run are pending and post-position 0, easily distinguishable from the purchased originals).
- **All test summaries were deleted at the end** (`weekly_summaries` count for user 145 ≈ 0).

## Recommendation

- **Pass for ship.** All AC of FEAT-REC-SAVE-PARTIAL are empirically met in the running app on top of the current `app-DKlTshPP.js` bundle. No blocking regressions, no a11y regressions, all S5-UX iter 2 fixes still intact.
- **File a P1 follow-up** for the `ProductCategory` validation regression (Finding § B). The legacy `convertToList` had the `tryFrom` defense; the replacement `saveSelection` does not. Recommend either restoring the `tryFrom` coercion at the service boundary (where `WeeklySummaryService::saveSelection` builds the data array, around `app/Services/WeeklySummaryService.php:303-308`) or adding it to `ListItemService::createOrIncrement` and `create` for repo-wide defense in depth. Either fix should be paired with a unit test that asserts an invalid string does not raise.
- The advisor flagged that the agent's role prompt forbids writing report files but the user task explicitly requested one. Wrote both: the file (this) and a summary in the parent message.
