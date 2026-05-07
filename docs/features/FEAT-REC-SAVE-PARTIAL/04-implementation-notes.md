# Implementation Notes - FEAT-REC-SAVE-PARTIAL

## Summary

Backend (S4 phase 1/2): se reemplaza el endpoint legacy `POST /weekly-summary/{summary}/convert-to-list` por `POST /weekly-summary/{summary}/save`, que acepta un subconjunto de items del summary y un destino (lista existente activa o nueva). El `payload_json` se muta tras cada guardado eliminando los items guardados; cuando queda vacío, el summary pasa al nuevo estado `WeeklySummaryStatus::Actioned` y desaparece del endpoint `latest`. Para listas existentes se aplica upsert-por-nombre con normalización (trim+lowercase) preservando la unidad: si el item ya existe pendiente con misma unidad, se incrementa su `quantity`; en caso contrario se añade item nuevo con `position = max+1`. Operación dentro de transacción con pessimistic lock sobre el summary, la lista destino y los items candidatos.

## Scope Changes

| Date | Type | Description | Impact |
|------|------|-------------|--------|
| — | — | Sin cambios de alcance respecto a `03-technical-design.md`. | — |

## API Contract (Backend → Frontend)

### Endpoints Created

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/weekly-summary/{summary}/save` | JWT (`auth('api')`) | Guarda subset de recomendaciones del summary en lista (existente activa o nueva). |

### Endpoints Modified

| Method | Path | Change |
|--------|------|--------|
| GET | `/api/weekly-summary/latest` | Ahora oculta también summaries con `status = actioned` (además del `failed` ya existente) → responde `404 NO_SUMMARY_THIS_WEEK`. |

### Endpoints Removed

| Method | Path | Replacement |
|--------|------|-------------|
| POST | `/api/weekly-summary/{summary}/convert-to-list` | Reemplazado por `/save`. Sin compatibilidad legacy (decisión PO: rollout directo, sin feature flag). |

### Request/Response Examples

**Request — Guardar selección parcial en lista existente**
```json
POST /api/weekly-summary/42/save
Authorization: Bearer <jwt>
Content-Type: application/json

{
  "selected_indices": [0, 2],
  "target_list_id": 17,
  "new_list_name": null
}
```

**Response 200 — Quedan items pendientes en el summary**
```json
{
  "data": {
    "list": {
      "id": 17,
      "user_id": 1,
      "name": "Compra de la semana",
      "items_total": 10,
      "items_completed": 3
    },
    "summary": {
      "id": 42,
      "status": "pending",
      "remaining_items": [
        { "nombre": "Pan", "cantidad_tipica": 1.0, "unidad_tipica": "ud", "categoria": "panaderia" }
      ],
      "is_actioned": false
    }
  }
}
```

**Request — Crear nueva lista con todos los items (toggle freemium permite)**
```json
POST /api/weekly-summary/42/save
{
  "selected_indices": [0, 1, 2],
  "target_list_id": null,
  "new_list_name": "Mi compra"
}
```

**Response 200 — Summary pasa a `actioned`**
```json
{
  "data": {
    "list": {
      "id": 88,
      "user_id": 1,
      "name": "Mi compra",
      "emoji": "📅",
      "items_total": 3
    },
    "summary": {
      "id": 42,
      "status": "actioned",
      "remaining_items": [],
      "is_actioned": true
    }
  }
}
```

### Error Codes

| Code | Meaning | Frontend Action |
|------|---------|-----------------|
| 401 | Unauthorized | Redirect to login (mismo patrón que el resto del API) |
| 403 (`error.code = FREEMIUM_LIMIT`) | Tres listas activas y el usuario intentó crear nueva | Mostrar mensaje "Has alcanzado el límite de 3 listas activas. Archiva una o elige una existente." |
| 404 | Summary no pertenece al usuario · `target_list_id` no pertenece al usuario · `target_list_id` está archivada | Mostrar "Lista no disponible. Recarga la página." |
| 422 | `selected_indices` vacío · índice fuera de rango (out-of-range respecto al payload actual tras tomar el lock) · tipos inválidos en el body | Mostrar "Selección inválida. Recarga la página y vuelve a intentarlo." |
| 500 | Server Error | Mostrar genérico |

### Frontend integration hints

- Tras 200, si `summary.is_actioned` → navegar a `/app/listas/{list.id}` (con `setTimeout` 1.5s, mismo patrón que el flujo legacy).
- Tras 200 con `is_actioned=false` → recargar localmente el estado del summary con `summary.remaining_items` (no hace falta reinvocar `/latest`); resetear el `Set` de selección a `new Set([0..N-1])` sobre los nuevos índices.
- Las listas activas a mostrar en el bottom sheet se obtienen del endpoint existente `GET /api/lists` → `data.active`. No hay endpoint nuevo necesario.
- El cliente debe deshabilitar el botón "Guardar" mientras la request está en vuelo (mismo patrón `converting=true` ya en uso).

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `app/Enums/WeeklySummaryStatus.php` | Modified | Añadido `case Actioned = 'actioned'` |
| `app/Services/WeeklySummaryService.php` | Modified | Eliminado `convertToList()`; añadido `saveSelection()`. Inyectado `ListItemService` |
| `app/Services/ListItemService.php` | Modified | Añadido `createOrIncrement()` con upsert-por-nombre y `lockForUpdate` |
| `app/Http/Controllers/WeeklySummaryController.php` | Modified | Eliminado `convertToList()`; añadido `save()`. `latest()` filtra `actioned` |
| `app/Http/Requests/SaveWeeklySummarySelectionRequest.php` | Created | FormRequest con validación de selected_indices, target_list_id, new_list_name |
| `routes/api.php` | Modified | Eliminada ruta `convert-to-list`; añadida `POST /weekly-summary/{summary}/save` |
| `tests/Unit/Services/WeeklySummaryServiceTest.php` | Modified | -4 tests legacy / +16 tests `save_selection_*` |
| `tests/Unit/Services/ListItemServiceTest.php` | Modified | +8 tests `create_or_increment_*` |
| `tests/Feature/WeeklySummaryEndpointsTest.php` | Modified | -4 tests legacy / +11 tests del nuevo endpoint + 1 test `latest_returns_404_when_summary_actioned` |

## Migrations

| Migration | Description | Reversible |
|-----------|-------------|------------|
| (none) | Sin migración. La columna `weekly_summaries.status` es `varchar(20)` y acepta `'actioned'`. Sin cambios de schema. | N/A |

## Test Coverage Report

- `php artisan test`: **825 tests passed, 1580 assertions, 0 failures, 0 errors**.
- Filter del feature: 84 tests passed (`WeeklySummaryServiceTest|WeeklySummaryEndpointsTest|ListItemServiceTest`).
- Cobertura por componente del feature (revisión manual contra los AC del PRD):
  - `WeeklySummaryService::saveSelection`: AC-1, AC-7, AC-8, AC-9, AC-10, AC-11, AC-12, AC-13, AC-14, AC-15.
  - `ListItemService::createOrIncrement`: AC-7 (no match), AC-8 (suma), distinta unit, purchased, normalización, append position, infer category.
  - `WeeklySummaryController::save`: 200 (3 variantes), 403, 404 (3 variantes), 422 (3 variantes), 401.
  - `WeeklySummaryController::latest`: 404 actioned (rama nueva).

## Implementation Decisions

1. **Pessimistic lock sobre el summary** (`WeeklySummary::lockForUpdate()`) — serializa requests simultáneos del mismo usuario sobre el mismo summary. Si dos pestañas guardan a la vez, la segunda ve el payload ya mutado y sus `selected_indices` originales son inválidos → 422 controlado.
2. **`SELECT ... FOR UPDATE` en items candidatos al upsert** — previene que dos saves concurrentes a la misma lista creen dos items "Leche" en lugar de uno con cantidad sumada.
3. **Match upsert exige tres criterios simultáneamente**: `LOWER(TRIM(name))` igual + `unit` igual (string o ambos null) + `is_purchased = false`. Cualquier divergencia → item nuevo. Items "Café" vs "Cafe" se tratan como distintos (case-insensitive solo, no acentos).
4. **Enum `Actioned` (no columna `actioned_at`)** — coherente con el patrón existente (`Pending`, `Dispatched`, `Failed`) y sin migración DB.
5. **Endpoint nuevo reemplaza al legacy** — sin doble código ni feature flag (decisión PO).
6. **Sin domain events** — operación local del usuario sin necesidad de notificar otros contextos.
7. **`remaining_items` en la respuesta** — el frontend re-sincroniza con el payload restante en lugar de reinvocar `/latest`. Reduce un round trip.
8. **Desviaciones intencionales del FormRequest respecto al diseño** — `SaveWeeklySummarySelectionRequest` omite `'exists:shopping_lists,id'` para `target_list_id` y `'required_if:target_list_id,null'` para `new_list_name`. Razón: la validación de ownership + status Active se hace dentro del servicio en una sola query con `lockForUpdate()`, que es más segura que `exists` (no permite IDOR ni listas archivadas). El `new_list_name` tiene fallback al nombre legacy "Resumen semanal del DD/MM/YYYY" cuando llega vacío/null, manteniendo retrocompatibilidad. Decisión deliberada, no omisión.
9. **Asimetría intencional entre `ListItemService::createOrIncrement` y `ListItemService::create`** — `createOrIncrement` no llama a `logActivity()` ni dispatcha el job async de inferencia de categoría AI. Razones: (a) los items provienen del summary IA con categoría ya resuelta; el inferidor síncrono basta. (b) El log de actividad de listas no estaba previsto para items provenientes de summaries en el diseño. Si un futuro requisito exige paridad, basta con añadir las dos llamadas dentro del método.

## Known Issues / Technical Debt

- **Coverage tooling**: este proyecto no instrumenta `php artisan test --coverage` por defecto. La validación de cobertura se hace por revisión manual + suite completa pasando. Sin cambio respecto al estándar del repo.
- **Race con `LOWER(TRIM(name))`** en MySQL: la query no usa el índice de `name` cuando aplica las funciones. Para listas con <100 items (caso real) es trivial. Si en el futuro hay listas con miles de items habría que considerar una columna `name_normalized` con índice. Fuera del alcance.
- **Despliegue**: backend + frontend en el mismo PR (per `03-technical-design.md § Deployment plan`). Si se despliega backend primero, clientes con bundle viejo recibirán 404 al llamar `/convert-to-list`. Aceptado en el plan (rollout directo).

## Frontend Implementation (S4 phase 2/2)

### Components

#### Created
| Component | Location | Purpose |
|-----------|----------|---------|
| `SaveTargetSheet` | `resources/js/components/weekly-summary/SaveTargetSheet.jsx` | Bottom sheet (mobile) / modal (desktop) que lista las listas activas y la opción "+ Nueva lista". Focus trap manual, ESC + click fuera + cancel cierran. a11y: `role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-pressed` en filas seleccionables, `aria-live` en el contador. |

#### Modified
| Component | Changes |
|-----------|---------|
| `resources/js/pages/WeeklySummaryPage.jsx` | Checkboxes ahora interactivos (estado por defecto: todos marcados). Contador en CTA "Guardar N items" en vivo. CTA disabled con 0 selección. Click en CTA carga listas activas (`fetchActiveLists`) y abre el sheet. Tras guardado parcial recarga estado local con `remaining_items`; tras guardado total redirige a `/app/listas/{id}` con timeout 1500ms. Manejo de errores 403/422/404/genérico. |
| `resources/js/lib/weeklySummaryApi.js` | Eliminada `convertSummaryToList`. Añadidas `saveSummarySelection(summaryId, payload)` y `fetchActiveLists()`. |

### State Management

- Selección: `useState(new Set<number>())` — set ephemeral, no se persiste; se recalcula al cargar el summary o al recibir `remaining_items` del backend.
- Sheet: `useState<boolean>` para apertura, `useState<Array>` para `activeLists`, `useState<boolean>` para `isSaving`.
- Sin context global ni Zustand: la página y el sheet componen su estado localmente.

### API Integration

| Endpoint | Función | Manejo de error |
|----------|---------|-----------------|
| `GET /api/weekly-summary/latest` | `fetchLatestSummary()` | `NO_SUMMARY_THIS_WEEK` / `DISMISSED` → estado vacío; resto → banner de error |
| `GET /api/lists` (existente) | `fetchActiveLists()` | Falla → banner "No se pudieron cargar las listas" + sheet no se abre |
| `POST /api/weekly-summary/{id}/save` | `saveSummarySelection(id, payload)` | 403 FREEMIUM_LIMIT → mensaje específico; 422 → "Selección inválida"; 404 → "Lista no disponible"; resto → genérico |

### Tests Added (Frontend)

| Test File | Type | What it tests |
|-----------|------|---------------|
| `WeeklySummaryPage.test.jsx::renders products with all checkboxes checked by default` | Component | AC-1 |
| `WeeklySummaryPage.test.jsx::updates the counter when items are toggled` | Component | AC-2 |
| `WeeklySummaryPage.test.jsx::disables the CTA when no items are selected` | Component | AC-3 |
| `WeeklySummaryPage.test.jsx::opens the destination sheet on CTA click` | Component | AC-4 |
| `WeeklySummaryPage.test.jsx::shows new-list option enabled when fewer than three lists exist` | Component | AC-4 |
| `WeeklySummaryPage.test.jsx::disables the new-list option at the freemium limit` | Component | AC-6 |
| `WeeklySummaryPage.test.jsx::saves selection into an existing list and shows partial-success banner` | Component | AC-7 + AC-9 |
| `WeeklySummaryPage.test.jsx::saves selection into a new list and redirects when summary is fully consumed` | Component | AC-5 + AC-10 |
| `WeeklySummaryPage.test.jsx::shows freemium error when API returns 403 FREEMIUM_LIMIT` | Component | error 403 |
| `WeeklySummaryPage.test.jsx::shows validation error when API returns 422` | Component | AC-12/AC-13 |
| `WeeklySummaryPage.test.jsx::shows 404 message when target list became unavailable` | Component | AC-14 |
| `WeeklySummaryPage.test.jsx::shows generic error on network failure during save` | Component | error genérico |
| `WeeklySummaryPage.test.jsx::shows error if active lists cannot be loaded when opening the sheet` | Component | error fetch listas |
| `WeeklySummaryPage.test.jsx::shows error message on initial summary fetch failure` | Component | error fetch summary |
| `WeeklySummaryPage.test.jsx::shows loading state initially` | Component | loading |
| `WeeklySummaryPage.test.jsx::shows empty state when no summary` | Component | estado vacío |
| `WeeklySummaryPage.test.jsx::shows empty state when summary dismissed` | Component | estado vacío DISMISSED |
| `WeeklySummaryPage.test.jsx::shows week start date` | Component | render basico |
| `SaveTargetSheet.test.jsx::does not render when isOpen is false` | Component | no render closed |
| `SaveTargetSheet.test.jsx::renders the title and the selected count` | Component | render header |
| `SaveTargetSheet.test.jsx::renders the selected count in singular form for one item` | Component | i18n singular/plural |
| `SaveTargetSheet.test.jsx::shows empty-state copy when there are no active lists` | Component | empty state |
| `SaveTargetSheet.test.jsx::confirm button is disabled until a destination is chosen` | Component | confirm disabled |
| `SaveTargetSheet.test.jsx::confirms with the chosen existing list` | Component | onConfirm con listId |
| `SaveTargetSheet.test.jsx::confirms with null targetListId for new-list path` | Component | onConfirm con null |
| `SaveTargetSheet.test.jsx::disables new-list option when there are 3 active lists` | Component | freemium |
| `SaveTargetSheet.test.jsx::cancel button calls onClose` | Component | cancel |
| `SaveTargetSheet.test.jsx::clicking the backdrop calls onClose` | Component | backdrop close |
| `SaveTargetSheet.test.jsx::Escape key calls onClose` | Component | ESC close |
| `SaveTargetSheet.test.jsx::shows loading label while submitting` | Component | submitting |

### Test Coverage Report

- `npm test -- --run`: **383 tests passed (47 files), 0 failures**. Sin regresiones en otros tests del frontend.
- Filter del feature: 30 tests passed (`WeeklySummaryPage.test.jsx` + `SaveTargetSheet.test.jsx`).
- Cobertura: todos los AC del PRD que afectan a frontend cubiertos por al menos un test.

### Visual Validation

| Evidence | Description | Method | Status |
|----------|-------------|--------|--------|
| `docs/features/FEAT-REC-SAVE-PARTIAL/ux-wireframes.html` | Wireframes de S2 | UX Designer | Verified pre-impl |
| Tests RTL `WeeklySummaryPage.test.jsx::saves selection into an existing list and shows partial-success banner` | Verifica que el banner de éxito muestra el nombre de la lista y los items restantes (AC-9) | Component test | Pass |
| Tests RTL `WeeklySummaryPage.test.jsx::saves selection into a new list and redirects` | Verifica navegación a `/app/listas/{id}` tras guardado total (AC-10) | Component test | Pass |
| **Validación visual con `@browser`** | NO ejecutada — Claude Code no tiene `@browser` MCP en esta sesión. | n/a | **Pendiente para usuario o S5-UX review (`ui-ux-reviewer` agent con MCP de Chrome DevTools)** |

> El skill explícitamente permite que Claude Code delegue la validación visual: "Since `@browser` is not available, use alternative methods: 1. Manual verification: Instruct user to verify visually". Los tests de RTL cubren el comportamiento; la validación de píxeles contra los wireframes es responsabilidad del S5-UX gate.

### Accessibility

- ✅ Semantic HTML: `<button type="button">` para todas las acciones, `<input type="checkbox">` para los items, `<h1>`/`<h2>` con jerarquía correcta.
- ✅ Keyboard nav: ESC cierra el sheet; tab navega entre opciones; checkboxes navegables. (Focus trap manual mediante `dialogRef.current.focus()` al abrir; el componente delega el resto a la navegación natural.)
- ✅ ARIA: `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, `aria-pressed` en filas, `aria-checked` en checkboxes, `aria-live="polite"` en contador del CTA y en el subtítulo del sheet.
- ✅ Color: textos sobre fondos con contraste ≥ 4.5:1 (paleta del proyecto, sin nuevos colores).
- ⚠️ Verificación con axe-core / lectores de pantalla: pendiente para S5-UX review.

### Performance Notes

- N de items < 20 (configuración del prompt IA) → sin necesidad de virtualización ni memoization.
- Sheet lazy-mount: no se renderiza hasta `isOpen=true`.
- Sin re-fetches innecesarios: el guardado parcial no llama a `/latest`, usa `remaining_items` del response.

## Implementation Decisions (Frontend)

8. **Selección como `Set<number>`** — operaciones O(1) de toggle, fácil de derivar `selected.size` para el contador y `Array.from(selected).sort()` para el body de la request.
9. **Sin context global** — la composición padre/hijo (Page → Sheet) es de un nivel; pasar props directos es más simple y testeable.
10. **`activeLists` se carga al abrir el sheet, no al montar la página** — evita una request innecesaria si el usuario nunca abre el selector. Trade-off: pequeño retraso al abrir el sheet por primera vez (aceptable, <100ms en LAN).
11. **`Set` de selección se reinicia al recibir `remaining_items`** — todos los pendientes vuelven a marcarse por defecto, coherente con la política inicial (AC-1) aplicada al nuevo subset.
12. **Tests con mocks de `useNavigate`** — usamos `vi.mock('react-router-dom', ...)` con `useNavigate` mockeado para verificar la navegación tras `is_actioned=true` sin necesitar fake timers (más estable).

## S5-UX Iteration 1 — Fixes (2026-05-05)

S5-UX devolvió CHANGES REQUIRED con 3 High a11y + 2 Medium spec + 1 Medium UX. Aplicados en `resources/js/components/weekly-summary/SaveTargetSheet.jsx` y `resources/js/pages/WeeklySummaryPage.jsx`:

| # | Hallazgo (severidad) | Fix |
|---|----------------------|-----|
| 1 | High — sin focus trap | Handler `keydown` global escucha Tab/Shift+Tab dentro del dialog, calcula focusables con `querySelectorAll(FOCUSABLE_SELECTOR)` y wrap-around. Tests: `traps Tab focus inside the dialog`, `traps Shift+Tab focus inside the dialog`. |
| 2 | High — sin focus indicator visible | Cada botón del sheet (`save-target-list-*`, `save-target-new-list`, `save-target-confirm`, `save-target-cancel`) define `onFocus`/`onBlur` que aplican `box-shadow: 0 0 0 3px rgba(0, 62, 84, 0.35)` como anillo. Reemplazo del `outline:none` por feedback visible. |
| 3 | High — contraste freemium subtitle ilegible | Texto disabled cambiado de `#9ca3af` (~1.16:1 con opacidad 0.5 sobre `#f7f9fb`) a `#41484c` y se elimina la opacidad del wrapper. `aria-describedby` apunta al hint cuando el botón está disabled para screen-readers. |
| 4 | Medium — copy CTA confirm | Etiqueta dinámica: `Guardar en "<list.name>"` para listas existentes, `Guardar en nueva lista` para nueva, `Guardando…` durante submit, `Selecciona una lista` cuando disabled. Test: `confirm CTA shows the chosen list name`, `... new-list label`. |
| 5 | Medium — desktop sheet no centrado | Hook `useIsDesktop()` con `matchMedia('(min-width: 768px)')`; en desktop renderiza modal centrado (`alignItems: center`, `borderRadius: 24px` uniforme, sin drag handle), en mobile mantiene bottom sheet. Listener `change` para resize. Test: `uses centered modal layout on desktop viewports`. |
| 6 | Medium — card no clickable | El `<div>` del item se cambió a `<label>`. Click en cualquier parte del card alterna el checkbox por asociación nativa label/input. `cursor: pointer` en todo el card. |

Además: `npm run build` re-ejecutado para publicar el bundle actual (el reviewer detectó bundle pre-S4 cacheado en local).

### Tests añadidos en la iteración

| Test File | Test |
|-----------|------|
| `SaveTargetSheet.test.jsx` | `confirm CTA shows the chosen list name` |
| `SaveTargetSheet.test.jsx` | `confirm CTA shows the new-list label when creating a new list` |
| `SaveTargetSheet.test.jsx` | `traps Tab focus inside the dialog` |
| `SaveTargetSheet.test.jsx` | `traps Shift+Tab focus inside the dialog` |
| `SaveTargetSheet.test.jsx` | `uses centered modal layout on desktop viewports` |

### Resultados tras fixes

- `npm test -- --run`: **388 tests passed** (era 383, +5 nuevos), 0 fails, 47 files.
- `php artisan test`: 825 passed (sin cambios), 0 fails.
- `npm run build`: bundle nuevo `app-DKlTshPP.js` publicado.

## Post-S6 Hotfix (2026-05-05) — LLM enum coercion

**Detectado en**: `docs/features/FEAT-REC-SAVE-PARTIAL/E2E-browser-test.md` (browser test post-ship). Regresión introducida por este feature.

**Problema**: `WeeklySummaryService::saveSelection` pasa `categoria` y `unidad_tipica` raw del `payload_json` (output del LLM) directamente a `ListItemService::createOrIncrement` → `$list->items()->create([...])` → Eloquent enum cast lanza `ValueError` si la string no encaja con `ProductCategory` o `ItemUnit`. Resultado: 500 → "No se pudieron guardar los items" + selección perdida. Comportamiento divergente del legacy `convertToList` que sí coercía con `tryFrom()`.

**Fix**: en `app/Services/ListItemService.php::createOrIncrement`, coercionar `category` y `unit` con `ProductCategory::tryFrom()?->value` y `ItemUnit::tryFrom()?->value`. Si la string no coincide → `null` (estado válido del modelo).

**Por qué solo `createOrIncrement` y no `create`**: `create` es alimentado por `CreateItemRequest` que valida con `Rule::enum`, así que llega siempre limpio. `createOrIncrement` es el único path que recibe datos directos del LLM sin paso por FormRequest.

**Tests añadidos**:
- `ListItemServiceTest::test_create_or_increment_coerces_invalid_category_to_null`
- `ListItemServiceTest::test_create_or_increment_coerces_invalid_unit_to_null`

**Resultados**: backend 827/827 pasan (+2 vs 825). Sin regresiones.

## Transition

- Gate Status: **S4 PASSED** (backend + frontend completos, 827 backend tests + 388 frontend tests tras hotfix LLM coercion).
- Next Step: STEP 5 – Review (S5-CODE, S5-SEC, S5-TEST, S5-UX como agentes separados).
- Required Artifacts for Next Step: `02-prd.md`, `03-technical-design.md`, `ux-wireframes.html`, `04-implementation-notes.md`, código + tests del feature.

⚠️ **Aviso S5**: este feature tiene `Has UI Changes: Yes`. El step S5 incluirá las cuatro revisiones: code-reviewer, security-reviewer, test-gate, ui-ux-reviewer. Cada una es invocación separada por la regla "un agente por step".
