# E2E Browser Test (Iter 2) — FEAT-REC-SAVE-PARTIAL

- **Date**: 2026-05-04
- **Tester**: Claude Code (Opus 4.7) via Chrome DevTools MCP
- **Environment**: `http://superia.com.local` (APP_URL is plain http; brief said https. Discrepancy carried over from iter1; tested against http successfully.)
- **Bundle served**: `app-DKlTshPP.js` (matches iter1; frontend untouched as expected)
- **Test user**: `pelrock@gmail.com` (id=145, password `TestQA-2026!`)
- **Backend test gate**: 827/827 passing (full suite re-run during this session)
- **Hotfix unit tests**: both pass (`test_create_or_increment_coerces_invalid_category_to_null`, `test_create_or_increment_coerces_invalid_unit_to_null`)
- **Viewports tested**: 1280×900 (primary), 375×812 (mobile spot check)

> Type: post-hotfix functional E2E retest. Not a workflow gate.

## Verdict

**PASS — ship-ready.** All 17 scenarios passed cleanly. **Test 17 (the hotfix verification) confirms the iter1 P1 regression is fixed**: invalid LLM `categoria` and `unidad_tipica` values now coerce to NULL via `tryFrom()` at the `ListItemService` boundary instead of triggering a 500 from the Eloquent enum cast. End-to-end: HTTP 200, items persisted, summary correctly transitioned to `actioned`. No new regressions, no console errors, no a11y or behavior drift from iter1.

## Results matrix

| # | Scenario | Result | Evidence |
|---|---|---|---|
| 1 | Initial load: 5 items, all checked, CTA "Guardar 5 items" with `aria-live="polite"` | **PASS** | `screenshots/iter2-01-initial-5items.png` |
| 2 | Toggle 2 off → CTA "Guardar 3 items"; live region updates | **PASS** | snapshot-only (visual identical to iter1) |
| 3 | Click CTA → sheet opens, `role=dialog` `aria-modal=true`, primary CTA disabled until selection, item-counter live region | **PASS** | `screenshots/iter2-03-sheet-open.png` |
| 4 | Pick existing list, partial save (2 of 3) → success banner with name + remaining count, page re-syncs locally | **PASS** | `screenshots/iter2-04-partial-save-banner.png` — banner reads `✓ 2 items añadidos a "Resumen semanal del 04/05/2026". Quedan 1 pendiente.` (singular "pendiente" correct) |
| 5 | Save full payload to new list → redirect to `/app/listas/{id}` after ~1.5s with all items in correct categories | **PASS** | implicit during Test 17 — redirected to list 36505 with all 5 items |
| 6 | After `actioned` summary, reload `/app/resumen` → empty state | **PASS** | snapshot showed "No hay resumen disponible esta semana." after save |
| 7 | Quantity-sum upsert: pre-existing pending Leche 1L + summary Leche 2L → single row Leche 3.00 L | **PASS** | `screenshots/iter2-07-08-09-upsert.png` |
| 8 | Different unit: Leche 2 ml from summary appears as separate row alongside Leche 3.00 L | **PASS** | same screenshot — `Leche 3.00 L` + `Leche 2.00 ml` side by side |
| 9 | Purchased item not modified: pre-existing Leche 1L purchased remains untouched in "Ya en el carro" section | **PASS** | same screenshot — purchased Leche 1L visible at bottom, untouched |
| 10 | Freemium 3-list limit: "+ Nueva lista" disabled with hint; existing lists still selectable; hint contrast `#41484c` on `#f7f9fb` (AAA) | **PASS** | `screenshots/iter2-10-freemium-disabled.png` — `aria-describedby="save-target-new-list-hint"`, computed `color: rgb(65, 72, 76)`, `bg: rgb(247, 249, 251)`, opacity 1 |
| 11 | Empty selection → CTA disabled, copy "Selecciona al menos un item" | **PASS** | snapshot — `disabled` attribute + correct text |
| 12 | ESC / backdrop click / Cancel all close dialog and restore focus to original CTA | **PASS** | verified via `document.activeElement` after each close — `BUTTON` "Guardar 1 item" each time |
| 13 | Keyboard Tab traps focus inside dialog with wrap-around; disabled "+ Nueva lista" skipped; focus ring `rgba(0,62,84,0.35) 0 0 0 3px` | **PASS** | `screenshots/iter2-13-keyboard-focus-ring.png` — Tab sequence: Lista Freemium 2 → Palomeque → Resumen → Cancelar → wrap-back to Lista Freemium 2 |
| 14 | Regression: dashboard / list-detail / item toggle continue to work | **PASS** | toggled Huevos pending↔purchased; counters updated; restored to original |
| 15 | 422 error path: payload mutated empty mid-flight → banner "Selección inválida. Recarga la página y vuelve a intentarlo." | **PASS** | `screenshots/iter2-15-422-error.png` — `role="alert" aria-live="assertive"`, dialog stays open |
| 16 | 404 error path: target list archived mid-flight → banner "Lista no disponible. Recarga la página." | **PASS** | snapshot — same `role="alert"` pattern, dialog stays open |
| **17** | **LLM enum coercion (the hotfix)** | **PASS** | `screenshots/iter2-01-initial-5items.png`, `screenshots/iter2-17-test17-destination-list.png`, plus DB query below |

## Test 17 — detailed evidence (the hotfix verification)

### Setup
Seeded `weekly_summaries.payload_json` for user 145 with 5 items; the last 2 deliberately violate enum constraints:

```json
[
  { "nombre": "Tomates cherry",          "cantidad_tipica": 1, "unidad_tipica": "kg",      "categoria": "frutas_verduras",   "reason": "qa-valid-1" },
  { "nombre": "Pollo entero",            "cantidad_tipica": 1, "unidad_tipica": "ud",      "categoria": "carnes_pescados",   "reason": "qa-valid-2" },
  { "nombre": "Yogur natural",           "cantidad_tipica": 4, "unidad_tipica": "ud",      "categoria": "lacteos_huevos",    "reason": "qa-valid-3" },
  { "nombre": "AceiteRaroPostFix",       "cantidad_tipica": 1, "unidad_tipica": "L",       "categoria": "aceites_no_existe", "reason": "qa-invalid-cat" },
  { "nombre": "CosaConUnidadInvalida",   "cantidad_tipica": 1, "unidad_tipica": "galones", "categoria": "otros",             "reason": "qa-invalid-unit" }
]
```

### Network evidence

```
POST /api/weekly-summary/2202/save → 200
GET  /api/lists/36505              → 200
GET  /api/lists/36505/items        → 200
```

The save returned **HTTP 200** (vs the iter1 HTTP 500 with `"aceites" is not a valid backing value for enum App\Enums\ProductCategory`). No 5xx anywhere in the network log. Single console 404 logged elsewhere is the deliberate Test 16 archive case, not from Test 17.

### DB-level evidence (post-save query against `list_items` for the destination list)

```
Tomates cherry             qty=1.00 unit=kg   cat=frutas_verduras  purchased=0
Pollo entero               qty=1.00 unit=ud   cat=carnes_pescados  purchased=0
Yogur natural              qty=4.00 unit=ud   cat=lacteos_huevos   purchased=0
AceiteRaroPostFix          qty=1.00 unit=L    cat=NULL             purchased=0
CosaConUnidadInvalida      qty=1.00 unit=NULL cat=otros            purchased=0
```

Per-row analysis vs expected hotfix behavior:

| Item | unit | category | Coercion check |
|---|---|---|---|
| Tomates cherry | `kg` (valid passthrough) | `frutas_verduras` (valid passthrough) | OK |
| Pollo entero | `ud` (valid passthrough) | `carnes_pescados` (valid passthrough) | OK |
| Yogur natural | `ud` (valid passthrough) | `lacteos_huevos` (valid passthrough) | OK |
| AceiteRaroPostFix | `L` (valid passthrough) | **NULL** — `aceites_no_existe` was rejected by `ProductCategory::tryFrom`, then `CategoryInferenceService` did not match the synthetic name "AceiteRaroPostFix", leaving `null` | **HOTFIX OK** |
| CosaConUnidadInvalida | **NULL** — `galones` was rejected by `ItemUnit::tryFrom` | `otros` (valid passthrough) | **HOTFIX OK** |

### UI-level evidence

In the destination list (`/app/listas/36505`):
- **AceiteRaroPostFix** rendered under **OTROS** (📦) with quantity `1.00 L`. The "OTROS" placement is the frontend's default-bucket-for-null-category behavior in `ListItemService::getItemsForList:36` (`$item->category?->value ?? 'otros'`); the DB stores `NULL` truthfully. Not a bug.
- **CosaConUnidadInvalida** rendered under **OTROS** (📦) with quantity `1.00` and **no unit badge** (compare to "1.00 kg" / "1.00 ud" siblings). This visually confirms `unit=NULL` in the DOM.

### Summary state

```
Summary 2202 status=actioned, remaining payload count=0
```

All items selected → payload empties → status transitions to `Actioned` → `/api/weekly-summary/latest` returns 404 NO_SUMMARY_THIS_WEEK on subsequent fetch → empty state shown.

## Console / network errors observed

- One expected 404 from Test 16 (deliberate archive mid-flight) — handled gracefully by the UI banner
- **No 500s, no JS errors, no other XHR failures** across the 17-test session

## Findings

### A. Hotfix is correct and minimally invasive

The fix at `app/Services/ListItemService.php:91-117` does exactly what was needed:
- Line 94: `$unit = $rawUnit !== null ? ItemUnit::tryFrom((string) $rawUnit)?->value : null;` — invalid unit → `null` instead of `ValueError`
- Line 117: `$category = $rawCategory !== null ? ProductCategory::tryFrom((string) $rawCategory)?->value : null;` — invalid category → `null` instead of `ValueError`
- Both branches still allow valid values to pass through unchanged
- The downstream `categoryInference->infer($name)` fallback at line 119 still runs when category resolves to null (regardless of whether it was null-input or invalid-input), preserving the inference behavior for organic items

This is a strict superset of the legacy `convertToList` behavior the regression introduced — the legacy did `tryFrom` for category but not for unit; the hotfix covers both. The two unit tests pin the contract.

### B. No new regressions

All 14 iter1 PASS scenarios still PASS in iter2 with identical observed behavior (banner copy, focus management, contrast colors, freemium hint, keyboard trap). Bundle is unchanged so no frontend-side surprises.

### C. Environmental note

- **APP_URL in `.env` is `http://`**, not `https://` as the brief mentioned. Tested against http successfully (consistent with iter1). No follow-up needed; just noting the discrepancy.
- Two backup unit tests in `tests/Unit/Services/ListItemServiceTest.php` cover the hotfix at the unit-test layer; the full backend suite re-ran clean (827/827) before the browser session started.

## Cleanup state

### Created during this session
- 1 weekly summary (id=2202) — re-used across all tests
- 2 new shopping lists: 36505 ("Resumen semanal del 04/05/2026", from Test 5/17), 36506 ("QA Lista Freemium 2", from Test 10 freemium setup)
- Several test items in Palomeque (118): Leche 3L pending (12509, from upsert), Leche 2 ml pending (12510)

### Deleted at end of session
- Lists 36505 and 36506 (and all their items) — DELETED
- Palomeque test items 12509 and 12510 — DELETED
- Summary 2202 — DELETED

### Final state for user 145

```
Lists for user 145:
  list [118] Palomeque status=active

Palomeque items:
  [7970] Agua mineral qty=1.00 ud purchased=1
  [7971] Pan integral qty=1.00 ud purchased=1
  [7972] Huevos       qty=1.00 ud purchased=0
  [12045] Leche       qty=1.00 L  purchased=1

Summaries for user 145: 0
```

This **exactly matches the original baseline** from before the iter1 session began (per the iter1 report's "original list pre-test" note). Cleanup contract fully satisfied.

### Side effects untouched (intentional)
- User 145's password remains `TestQA-2026!` (changed by iter1; restoring it is out of scope for this iter and the brief did not request it)

## Recommendation

- **PASS for ship.** The hotfix is correct, the iter1 P1 regression is gone, no new regressions emerged, and all iter1 acceptance criteria continue to hold on the same `app-DKlTshPP.js` bundle. The two new unit tests guard against re-introduction of the bug.
- **No follow-up tickets needed** beyond what iter1 already flagged (the `ClaudeClient` parsers themselves still don't bind incoming categoria/unit to enums at the ingestion layer — that's a defense-in-depth concern, but the service-layer fix ships in the critical path so the user-facing 500 is no longer reachable from this code path).
