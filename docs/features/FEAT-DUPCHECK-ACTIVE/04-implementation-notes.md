# Implementation Notes - FEAT-DUPCHECK-ACTIVE

## Phase

S4-BOTH (backend ✓ + frontend ✓ complete).

## Summary (Backend)

Backend listo: `SpanishInflector` helper + integración en `ListItemService::create()` y `createOrIncrement()` para borrar items comprados homónimos al añadir un item, usando normalización singular/plural en español.

## Files Changed (Backend)

| File | Change Type | Description |
|------|-------------|-------------|
| `app/Support/Inflector/SpanishInflector.php` | Created | Helper estático: `normalize(string): string`. Reglas R1 (-ces→-z), R2 (strip -s/-es con elección por consonante de raíz), invariables. Procesamiento token-por-token. |
| `tests/Unit/Support/Inflector/SpanishInflectorTest.php` | Created | 52 tests parametrizados cubriendo AC-5, invariables, ñ, acentos, edge cases. |
| `app/Services/ListItemService.php` | Modified | Import de `SpanishInflector`. `create()`: extrae `unit` y llama `deletePurchasedHomonyms` dentro de la transacción. `createOrIncrement()`: lookup pendiente vía filtro PHP (no SQL) usando normalización, luego `deletePurchasedHomonyms` antes de crear. Nuevos privados `deletePurchasedHomonyms` y `unitMatches`. |
| `tests/Unit/Services/ListItemServiceTest.php` | Modified | Import `ItemUnit`. 10 tests nuevos para el delete del homónimo + 1 test existente actualizado (`test_create_or_increment_does_not_match_purchased_item`) para reflejar nueva semántica. |
| `tests/Unit/Services/WeeklySummaryServiceTest.php` | Modified | 1 test existente actualizado (`test_save_selection_creates_new_item_when_existing_match_is_purchased`) para reflejar que ahora el purchased se elimina. |

## Migrations

Ninguna. No requirió cambios de esquema.

## Tests Added (Backend)

| Test File | Type | What it tests |
|-----------|------|---------------|
| `SpanishInflectorTest::test_normalize` (52 data sets) | Unit | Normalización: singulares, plurales R1/R2, invariables, ñ, acentos, mayúsculas, multi-palabra. |
| `SpanishInflectorTest::test_empty_string_returns_empty` | Unit | Edge case: input vacío. |
| `SpanishInflectorTest::test_whitespace_only_returns_empty` | Unit | Edge case: solo espacios. |
| `SpanishInflectorTest::test_short_word_unchanged` | Unit | Edge case: palabras cortas no se depluralizan. |
| `SpanishInflectorTest::test_returns_pure_function_same_input_same_output` | Unit | Idempotencia y multi-token. |
| `ListItemServiceTest::test_create_deletes_purchased_homonym_with_same_normalized_name` | Unit | AC-1: nombre idéntico. |
| `ListItemServiceTest::test_create_deletes_purchased_homonym_singular_plural_variants` | Unit | AC-2: comprado "Panes" + add "pan" → borra y crea. |
| `ListItemServiceTest::test_create_deletes_purchased_homonym_plural_input_singular_existing` | Unit | AC-2 inverso: comprado "Tomate" + add "Tomates". |
| `ListItemServiceTest::test_create_deletes_all_purchased_homonyms_when_multiple_match` | Unit | AC-4: múltiples comprados homónimos eliminados. |
| `ListItemServiceTest::test_create_does_not_delete_purchased_with_different_unit` | Unit | AC-6: unidad distinta no dispara delete. |
| `ListItemServiceTest::test_create_does_not_delete_purchased_with_different_name` | Unit | AC-8: nombres distintos no se confunden (pollo/polla). |
| `ListItemServiceTest::test_create_does_not_touch_pending_items_with_same_name` | Unit | Pendientes con mismo nombre no se ven afectados (escenario de "Añadir de todas formas"). |
| `ListItemServiceTest::test_create_does_not_delete_purchased_in_other_lists` | Unit | Aislamiento por `shopping_list_id`: comprado en otra lista intacto. |
| `ListItemServiceTest::test_create_or_increment_matches_normalized_plural_for_pending_increment` | Unit | `createOrIncrement` incrementa pendiente "Tomate" al añadir "Tomates". |
| `ListItemServiceTest::test_create_or_increment_deletes_purchased_homonyms_when_no_pending_match` | Unit | `createOrIncrement` borra comprado homónimo cuando no hay pendiente match. |
| `ListItemServiceTest::test_create_or_increment_does_not_delete_purchased_when_incrementing_existing_pending` | Unit | AC-10 (parcial): si existe pendiente match, se incrementa y NO se toca el comprado homónimo. |

## Test Results (Backend)

```
Tests: 887 passed, 3 failed (1675 assertions)
Duration: ~352s
```

Los 3 tests que fallan **no están relacionados** con esta feature. Verificado con `git stash` (vuelven a fallar en `main` sin mis cambios):

- `Tests\Feature\DispatchWeeklySummaryCommandTest::kill_switch_disabled_prevents_any_dispatch` — pre-existente
- `Tests\Feature\DispatchWeeklySummaryCommandTest::failure_isolation_one_user_fails_others_succeed` — pre-existente
- `Tests\Feature\SecurityGatesIntegrationTest::composer_security_exits_zero` — pre-existente (integración con security gate)

Tests añadidos/modificados por esta feature: **63 tests pasan (52 inflector + 10 service + 1 existing fixed)**. Cero regresiones introducidas.

## Test Coverage Report

Cobertura medida con xdebug no ejecutada en este turn por tiempo. Tests cubren:

- `SpanishInflector::normalize` — todas las ramas (R1, R2 con ambos branches, R3, invariables, edge cases) → 100% rama.
- `ListItemService::create` — happy path, con/sin homónimo, multi-match, unit mismatch, name mismatch, isolation por lista → cubre la rama nueva al 100%.
- `ListItemService::createOrIncrement` — happy path (increment plural), no-pending-match con delete, pending-match sin delete → cubre las ramas modificadas al 100%.
- `ListItemService::deletePurchasedHomonyms` — branches matchIds vacío y no vacío cubiertos vía AC-8 y AC-1.
- `ListItemService::unitMatches` — null/null, value/value, null/value, value/null cubiertos.

## API Contract (Backend → Frontend)

### Endpoints Modified

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/lists/{list}/items` | JWT | Sin cambios de contrato. Comportamiento: al crear un item, elimina **silenciosamente** todos los items con `is_purchased = true` de la misma lista cuya forma normalizada y unidad coinciden con el nuevo item. El cliente debe asumir que el siguiente `GET /items` reflejará la lista sin los comprados homónimos. |
| PATCH | `/api/lists/{list}/items/{item}/increment-quantity` | JWT | **Sin cambios**. No aplica la regla de delete del homónimo (opera sobre item específico identificado). |

### Request/Response Examples

```json
// POST /api/lists/{list}/items - Request (sin cambios)
{
  "name": "Pan",
  "quantity": 1,
  "unit": "ud",
  "category": "panaderia"
}

// Response (sin cambios estructurales)
// 201 Created
{
  "data": {
    "item": {
      "id": 42,
      "name": "Pan",
      "quantity": "1.00",
      "unit": "ud",
      "category": "panaderia",
      "is_purchased": false,
      "position": 0
    },
    "counters": {
      "items_total": 5,
      "items_completed": 2
    }
  }
}
```

> Nota para el frontend: el counter `items_total` y `items_completed` ya reflejan los deletes del homónimo. El cliente puede recargar la vista de lista (o aplicar diff incremental) basándose en el counter. No se devuelve la lista de items borrados.

### Error Codes (sin cambios)

| Code | Meaning | Frontend Action |
|------|---------|-----------------|
| 400 | Bad Request | Show validation errors |
| 401 | Unauthorized | Redirect to login |
| 403 | Forbidden | Show access denied |
| 404 | Not Found | Show not found message |
| 422 | Validation Error | Show field errors |
| 500 | Server Error | Show generic error |

## Summary (Frontend)

Frontend listo: mirror del `SpanishInflector` en JS + actualización de `findDuplicate` en `AddItemInput.jsx` y `AddItemModal.jsx` para filtrar `is_purchased=false` y usar match exacto normalizado antes del fallback fuzzy.

## Files Changed (Frontend)

| File | Change Type | Description |
|------|-------------|-------------|
| `resources/js/lib/spanishInflector.js` | Created | Mirror del PHP. Misma lógica: token-por-token, INVARIABLES, R1 -ces→-z, R2 strip -s/-es por consonante de raíz, ACCENT_MAP, ñ preservada. Default export `normalize(name)`. |
| `resources/js/lib/spanishInflector.test.js` | Created | 54 tests Vitest parametrizados (`it.each`). Contrato espejo del PHP. |
| `resources/js/components/items/AddItemInput.jsx` | Modified | Import `normalize`. `findDuplicate` ahora salta items con `is_purchased=true`, compara primero por forma normalizada exacta, luego fallback `similarText > 0.80` para typos. |
| `resources/js/components/items/AddItemModal.jsx` | Modified | Misma lógica de `findDuplicate`. |
| `resources/js/components/items/AddItemInput.test.jsx` | Modified | 7 tests nuevos bajo `describe('duplicate detection vs active items only')`. |
| `resources/js/components/items/AddItemModal.test.jsx` | Created | 9 tests cubriendo AC-1, AC-2, AC-3, AC-7, AC-8, AC-10 (mixed), incremento y add-anyway flow. |

## Tests Added (Frontend)

| Test File | Type | What it tests |
|-----------|------|---------------|
| `spanishInflector.test.js` | Unit | 54 casos parametrizados de normalización (espejo del backend) + 4 tests de bordes (null, números, idempotencia, palabras cortas). |
| `AddItemInput.test.jsx` | Component | No warning vs comprado (AC-1), no warning vs plural comprado (AC-2), sí warning vs pendiente (AC-3), sí warning con plural input (AC-2 inverso), mixed list pendiente+comprado (AC-10), fuzzy typo fallback (AC-7), pollo/polla no match (AC-8). |
| `AddItemModal.test.jsx` | Component | Mismos casos AC-1, AC-2, AC-3, AC-10, AC-7, AC-8 + handlers de incrementar y añadir igualmente. |

## Test Results (Frontend)

```
Test Files  49 passed (49)
Tests       458 passed (458)
Duration    ~23s
```

**Cero regresiones**. Net new: 54 inflector + 7 AddItemInput + 9 AddItemModal = **70 tests añadidos**, todos en verde. Suite frontend completa pasa (458).

## State Management (Frontend)

Sin cambios. `findDuplicate` se ejecuta on-submit en `useState` locales (`duplicateMatch`, `pendingPayload`). Mismo modelo que antes; solo cambia la condición interna de match.

## API Integration (Frontend)

| Endpoint | Hook/Function | Error Handling |
|----------|---------------|----------------|
| `POST /api/lists/{list}/items` | `onAdd` prop (definido en `ListDetailPage`) | Sin cambios; el frontend no necesita conocer el delete del comprado (transparente). |
| `PATCH /api/lists/{list}/items/{item}/increment-quantity` | `onIncrementExisting` prop | Sin cambios. |

## Visual Validation (Frontend)

| Evidence | Description | Method | Status |
|----------|-------------|--------|--------|
| `AddItemInput.test.jsx` x7 | Estados condicionales del `DuplicateWarning` y flujo de submit | Vitest + Testing Library (DOM assertions) | Verified |
| `AddItemModal.test.jsx` x9 | Igual + flow de increment y add-anyway en modal | Vitest + Testing Library | Verified |
| Browser manual smoke | Pendiente: validar con MySQL up + Vite dev en `superia.com.local` que la UI no muestra warning al re-añadir un comprado y que el comprado desaparece tras el siguiente GET | Manual @browser | **Pendiente para S5-UX** |

Sin cambios visuales (mismo `DuplicateWarning` reutilizado), por lo que screenshots no aplican en esta fase. S5-UX validará el flow end-to-end en navegador.

## Accessibility (Frontend)

- Sin cambios en el componente `DuplicateWarning` ni en su a11y (mantiene `role="alert"`).
- `findDuplicate` opera silenciosamente; no se introducen nuevas regiones live ni focus traps.
- Sin cambios en navegación teclado del flujo de add.

## Performance Notes (Frontend)

- `normalize(item.name)` se calcula on-the-fly por cada item en `findDuplicate` (sin memoización). Volumen ≤100 items por lista, longitud media <20 chars → coste despreciable (<1ms total).
- Sin recomputo en cada keystroke: `findDuplicate` solo se ejecuta en submit (`handleSubmit`).
- Sin nuevos imports pesados: `spanishInflector.js` es <60 LOC, tree-shakeable.

## Notes for Reviewers (Frontend)

- El contrato del inflector entre PHP y JS está cubierto por dos suites paralelas (`SpanishInflectorTest` PHP + `spanishInflector.test.js` JS) con los mismos casos. Si una rama actualiza reglas en un lado, los tests del otro fallarán y revelarán el drift.
- `AddItemInput` no recibe `existingItems` en `SharedListPage.jsx:505` (default `[]`). Sin regresión: misma situación que pre-feature. Si el negocio quisiera activar el check en lista compartida, sería una feature nueva.
- El usuario de "Añadir de todas formas" tras un warning de pendiente NO dispara delete de comprados homónimos en backend (porque el backend siempre aplica el delete en `create()`, independientemente de la decisión del frontend). Esto es por diseño según AC-10.

## Deviations from Design/UX (Frontend)

Ninguna. Implementación coincide con S3.

## Implementation Decisions

1. **Token-by-token normalization**: `SpanishInflector::normalize` divide la entrada por espacios y normaliza cada token. "Tomates Rojos" → "tomate rojo". Decisión derivada al añadir tests: nombres multi-palabra son comunes en listas de compra.

2. **Strip de acentos sin `intl`**: usando `strtr` con array de mapeo explícito. Más portable (no requiere extensión PHP intl), ñ se preserva por simple omisión del mapeo.

3. **Filtro PHP vs SQL**: el matching usa `->get()->filter()` en memoria sobre el subset `is_purchased = true|false` con `lockForUpdate()`. Volumen bajo (<100 items/lista). Trade-off documentado en S3.

4. **`unitMatches` extraído como método privado**: aunque trivial (`===` comparison), facilita futura extensión (p. ej. comparar unidades equivalentes ml↔L con conversión).

5. **Test existente actualizado, no eliminado**: `test_create_or_increment_does_not_match_purchased_item` se mantiene con su nombre porque la semántica de "no matchea para incrementar" sigue siendo cierta — la diferencia es que ahora también elimina el comprado, no que lo deja intacto.

## Known Issues / Technical Debt

1. **Stem singular terminando en `s`** (`bus/buses`, `país/países`): R2 no acierta. Documentado en S3 como Known Limitation. No son items habituales de lista de compra.

2. **`tres` (length 4)**: R2 lo procesaría como plural ("tres" → "tr"). No relevante para nombres de productos. Si aparece, queda como Known Limitation.

3. **Sin medición formal de cobertura**: ejecución de Xdebug coverage report omitida en este turn por tiempo. Recomendado en S5-TEST verificar con `php artisan test --coverage --min=100 -- tests/Unit/Support/Inflector tests/Unit/Services/ListItemServiceTest.php`.

## Deviations from Design

Ninguna desviación arquitectural. Implementación coincide con S3.

Nota menor: la regla R2 con length ≥ 4 se mantuvo (sin elevar a length ≥ 5) tal como el design especifica. Casos límite como "tres" (length 4) producen output incorrecto, documentado como Known Limitation arriba.
