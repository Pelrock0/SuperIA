## Code Review: FEAT-REC-SAVE-PARTIAL

### Summary
- **Status**: PASS
- **Reviewer**: code-reviewer (S5-CODE)
- **Date**: 2026-05-04

### Justification
La implementación cumple el diseño aprobado en `03-technical-design.md`: transacción única con `lockForUpdate` en summary, lista destino e items candidatos; upsert por nombre normalizado + unidad + `is_purchased=false`; mutación atómica del `payload_json` y transición a `WeeklySummaryStatus::Actioned` cuando el payload queda vacío; ownership/IDOR validados en una única query combinada. Tests backend (825) y frontend (383) en verde, cubriendo todos los AC del PRD. Las desviaciones detectadas son menores y no comprometen la seguridad ni la corrección.

### Findings

#### Readability
- `app/Services/WeeklySummaryService.php:233-336` — `saveSelection()` está bien comentado, con un docblock explícito sobre comportamiento, condiciones de fallo y excepciones tipadas (404/422/403). Las fases del flujo (lock summary → validar índices → resolver lista destino → upsert items → reescribir payload → actualizar contadores) son legibles y siguen el orden del data-flow del diseño.
- `app/Services/ListItemService.php:87-129` — `createOrIncrement()` tiene docblock claro sobre la regla de match (trim+lowercase + unit + not purchased) y advierte explícitamente: "Caller is responsible for wrapping this in a transaction when atomicity matters". Buena disciplina.
- `app/Http/Controllers/WeeklySummaryController.php:64-96` — el controller es delgado, traduce `OverflowException` a 403 FREEMIUM_LIMIT y serializa `summary` con un campo `is_actioned` derivado. Sin lógica de negocio.
- `resources/js/pages/WeeklySummaryPage.jsx:50-131` — el manejo de errores por código (`FREEMIUM_LIMIT` / 422 / 404 / genérico) es directo y mapea uno a uno con la tabla de códigos en `04-implementation-notes.md`.
- `resources/js/components/weekly-summary/SaveTargetSheet.jsx:67-82` — `handleConfirm` y la elección de target son explícitos (booleano `createNew` + `chosenListId`). Sin estado redundante.

#### Maintainability
- `app/Services/WeeklySummaryService.php:36` — la inyección de `ListItemService` por constructor sigue la convención de los demás services del repo. Sin acoplamiento oculto.
- `app/Services/WeeklySummaryService.php:326-329` — `syncCounters()` se reimplementa inline en lugar de reutilizar el helper privado `ListItemService::syncCounters`. Es duplicación menor (4 líneas) y consciente: exponer el helper exigiría cambiar visibilidad o crear un método público en `ListItemService`. **Aceptable como deuda menor**; documentado aquí para que el próximo cambio de contadores no introduzca divergencia.
- `app/Services/ListItemService.php:87-129` — `createOrIncrement()` **no llama** a `logActivity()` (a diferencia de `create()` en línea 63). Items guardados via weekly-summary no aparecerán en `list_activity_log`. El diseño no exige esto explícitamente, pero la asimetría puede sorprender. **Flag para PO**, no bloqueante.
- `app/Services/ListItemService.php:113-117` — el path "no match" infiere categoría síncrono (`categoryInference->infer`) pero **no dispatch** del job AI (que sí hace `create()` línea 70). Aceptable porque el payload del summary trae `categoria` desde Claude; cuando no la trae, el inferidor síncrono ya cubre los casos comunes. Asimetría documentada en este review.
- `app/Http/Requests/SaveWeeklySummarySelectionRequest.php` — desviaciones del diseño (`03-technical-design.md §Input Validation`):
  - El diseño especificaba `'target_list_id' => [..., 'exists:shopping_lists,id']` y la implementación lo omite. La validación de existencia + ownership + status se hace **dentro del servicio** en una query única (`app/Services/WeeklySummaryService.php:276-285`), que es **más segura** que `exists` (no permite IDOR ni listas archivadas). Aceptable.
  - El diseño especificaba `'new_list_name' => [..., 'required_if:target_list_id,null']` y la implementación lo omite. El service tiene fallback al nombre legacy "Resumen semanal del DD/MM/YYYY" cuando `new_list_name` está vacío (`WeeklySummaryService.php:287-289`). Mantiene retrocompatibilidad. Aceptable.
  - **Acción recomendada**: documentar estas desviaciones en `04-implementation-notes.md §Implementation Decisions` para que no se interpreten como omisiones accidentales.
- Convenciones del repo (FormRequest, namespace, Eloquent direct en services, `auth('api')`, JSON envelope `data`/`error.code`): respetadas.

#### Tests
- `tests/Unit/Services/WeeklySummaryServiceTest.php:260-517` — 16 tests cubren: nueva lista total → `Actioned`, parcial → `Pending`, append a lista existente, suma de cantidades por nombre+unit, distinta unit → item nuevo, purchased → item nuevo, normalización trim+case, freemium 3 listas, IDOR (summary y target_list de otro user), archivada, indices vacío, indices fuera de rango, dedup de índices repetidos, custom name, fallback default, payload entries sin `nombre`. Cubre todos los AC del PRD.
- `tests/Unit/Services/ListItemServiceTest.php:237-388` — 8 tests del nuevo método `createOrIncrement`: no match, append position correcto (`max+1`), match same unit, normalización, distinta unit, purchased, ambos null unit, inferencia categoría. Cobertura exhaustiva.
- `tests/Feature/WeeklySummaryEndpointsTest.php:135-318` — 11 tests endpoint + 1 nuevo `latest_returns_404_when_summary_actioned`. Cubre 200 (3 variantes), 403 FREEMIUM_LIMIT, 404 (3 variantes), 422 (3 variantes), 401.
- `resources/js/pages/WeeklySummaryPage.test.jsx` (18 tests) y `SaveTargetSheet.test.jsx` (12 tests) — todos los AC frontend cubiertos.
- **Gap menor (no bloqueante)**: el diseño listaba "Race: simular dos transacciones, una espera el lock, segunda ve payload mutado" (`03-technical-design.md §Test Cases`). No hay test concurrente determinista. La corrección estructural (`lockForUpdate` en summary + items candidatos) es verificable por inspección y por la prueba `out_of_range_indices` que cubre el caso "selección obsoleta tras mutación previa". Aceptable dado que tests de race en PHPUnit son frágiles.
- Resultado de ejecución validado: `php artisan test` 825/0, `npm test -- --run` 383/0.

#### Performance
- `app/Services/WeeklySummaryService.php:266-272` — validación O(N) de índices con `count(payload)`; N ≤ 50 (validado por FormRequest `max:50`) y típicamente < 20 (cap del prompt IA). Sin problema.
- `app/Services/ListItemService.php:94-105` — la query `LOWER(TRIM(name))` no usa el índice de `name`, pero existe el índice `(shopping_list_id, is_purchased)` (`database/migrations/2026_04_10_223716_create_list_items_table.php:26`) que filtra el dataset por lista. Para listas <100 items (caso real del freemium) es trivial. Documentado correctamente en `04-implementation-notes.md §Known Issues`.
- `app/Services/ListItemService.php:119` — `$list->items()->max('position')` se ejecuta dentro del loop de `saveSelection`, por cada item insertado. Es O(N) queries en el peor caso. **Optimización sugerida en el diseño** (`03-technical-design.md §Performance`: "cargar todos los items de la lista destino una vez + mapa en memoria") **no se implementó**. Para N<20 items es <40ms en MySQL local; aceptable. **No bloqueante**, anotado como mejora futura si se elimina el cap freemium.
- Sin N+1 visible en endpoints (`latest`, `save`). El response del controller serializa `$result['list']` directamente: si en algún momento se añadiera eager-loading de `items` ahí, conviene revisarlo.
- Frontend: `Set` para selección, sheet lazy-mount al abrir, sin re-fetches innecesarios (usa `remaining_items` del response). Correcto.

#### Architecture
- **Hexagonal estricto**: el diseño aceptó explícitamente seguir el patrón Service-with-Eloquent existente del repo (`03-technical-design.md §Trade-offs` fila 1: "Rechazada — se sigue el patrón establecido del proyecto"). El gate de S3 ya aprobó esta deuda colectiva. **No es una regresión introducida por este feature**; no se re-litiga aquí.
- **DDD checklist**:
  - Bounded context único (`default`/list-items): no hay imports cross-context. ✓
  - Aggregate root: `WeeklySummary`. La invariante "payload vacío ⇒ status Actioned" se enforza dentro de `saveSelection` (`WeeklySummaryService.php:319-323`). ✓
  - Sin events de dominio (decisión documentada en `03-technical-design.md`). ✓
  - Glossary extendido en S2 (`docs/contexts/default/00-glossary.md`). ✓
- **Controllers thin**: `WeeklySummaryController::save` solo delega al service; única lógica HTTP es traducir `OverflowException` a 403 (`WeeklySummaryController.php:77-81`). ✓
- **Validation en FormRequest**: tipos básicos (rangos, requerido, tamaños). Validación de negocio (índices ≤ count(payload), ownership, status Active) en el service tras tomar el lock. Coherente con la decisión de seguridad del diseño. ✓
- **Transaction boundaries**: una sola `DB::transaction` que arranca en el service (`WeeklySummaryService.php:252`) abarca lock + validación + upsert + payload mutate + counters update. ✓
- **Pessimistic lock** sobre summary (`whereKey()->lockForUpdate()->first()` línea 254) y target list (línea 280). El item-level `lockForUpdate` está dentro de `createOrIncrement` (`ListItemService.php:104`). Coincide con el flujo descrito en `03-technical-design.md §Data Flow`. ✓
- **Authorization**: `auth('api')` (route group), summary ownership en service (`WeeklySummaryService.php:240-242` → 404 vía `abort()`), target list ownership + status Active en una sola query (`WeeklySummaryService.php:276-285` → 404). Cubre AC-11 e AC-14. ✓
- **Quantity overflow** (riesgo en `03-technical-design.md §Risks`): no se implementó el cap defensivo "si suma > 99999, no sumar y crear item nuevo". Columna `decimal(8,2)` → máximo 999.999,99 (verificado en migración). Riesgo realista despreciable. **Aceptado como residual**.
- **A11y del sheet**: focus trap real para Tab/Shift+Tab no implementado (sólo focus inicial al abrir). El propio `04-implementation-notes.md §Accessibility` lo declara: "delega el resto a la navegación natural". **Es competencia del S5-UX gate** (`ui-ux-reviewer` con axe-core/MCP), no de code review. Mencionado aquí para que el reviewer de UX no lo omita.

### Recommendation
- [x] Approve
- [ ] Request changes

### Required Changes
Ninguna requerida para aprobación. Recomendaciones no bloqueantes documentadas en su lugar correspondiente:

1. **Doc only** — `docs/features/FEAT-REC-SAVE-PARTIAL/04-implementation-notes.md §Implementation Decisions`: añadir entrada explicando por qué `SaveWeeklySummarySelectionRequest` omite `exists` y `required_if` que el diseño mencionaba (sustituidos por validación de servicio más fuerte y por fallback de nombre).
2. **Doc only** — mismo archivo: registrar la asimetría intencional `createOrIncrement` vs `create` (sin `logActivity`, sin dispatch de AI category inference) o, alternativamente, añadir las llamadas en `createOrIncrement` para paridad. Decisión del PO/architect.
3. **Tech debt menor** — `WeeklySummaryService.php:326-329` duplica la lógica de `ListItemService::syncCounters` (privado). Si en el futuro se cambia la fórmula de contadores, recordar tocar ambos sitios. No bloquea.
4. **Tech debt menor** — `ListItemService::createOrIncrement` ejecuta `MAX(position)` en cada llamada. Aceptable para N<20; revisar si se elimina el cap freemium.
5. **Para el S5-UX gate** — verificar focus trap real (Tab/Shift+Tab) en `SaveTargetSheet`, contraste y axe-core. No es parte del code review.
6. **Para el S5-TEST gate** — confirmar la cobertura 100% por componente del feature (los reportes manuales de `04-implementation-notes.md §Test Coverage Report` parecen suficientes pero el test gate decide).
