# Scope Analysis: FEAT-REC-SAVE-PARTIAL

## Feature Request

Cuando llegan las recomendaciones semanales (Weekly Summary), la única acción disponible hoy es "convertir todo el resumen en una lista nueva". Se necesita permitir al usuario:

1. **Seleccionar qué items concretos** del resumen quiere guardar (subset, no todo o nada).
2. **Elegir destino**:
   - Lista **nueva** (comportamiento actual), o
   - Una **lista existente** del usuario (añadir items a una lista activa ya creada).

> Origen del cambio: feedback de usuario (UX). El flujo actual fuerza a crear una lista por cada resumen, lo que multiplica listas activas y choca con el límite freemium de 3 listas.

## Bounded Context

| Attribute | Value |
|-----------|-------|
| Context name | default (list-items / shopping-lists) |
| Glossary | `docs/contexts/default/00-glossary.md` (existe; extendido en S2 con términos de recomendaciones) |
| New domain terms introduced | Resumen semanal, Recomendación, Guardar selección, Lista destino, Conversión parcial, Resumen actuado, Recomendación pendiente. Reutiliza: `WeeklySummary`, `ShoppingList`, `ShoppingListItem`. |

> Decisión: el feature pertenece al mismo Bounded Context de listas-compra que ya está representado en `default`. No se separa en un contexto `listas` aparte porque los términos nuevos referencian `Lista de compra` y `ShoppingList`, que viven en `default`. Crear un contexto separado introduciría imports cross-context prohibidos por DDD.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **MEDIUM** |
| Estimated Effort | 8–12 horas (backend + frontend + tests) |
| Confidence | Medium |

## Justification

- **Modifica lógica de negocio existente**: `WeeklySummaryService::convertToList()` debe aceptar un subconjunto de items y un destino (nueva lista vs lista existente).
- **Endpoint API afectado**: `POST /weekly-summary/{id}/convert-to-list` (o nombre actual) cambia su contrato — pasa de "todo o nada" a aceptar `selected_indices` y `target_list_id?`.
- **UI con cambios no triviales**: la página `WeeklySummaryPage.jsx` hoy renderiza checkboxes `readOnly` (decorativos) y un único botón "Crear lista con N productos". Hay que volverlos interactivos, añadir selector de destino (modal/dropdown con listas existentes + opción "nueva"), y reaccionar a estado de selección.
- **No requiere migración de schema**: los datos de items siguen viniendo de `payload_json` del summary; no hay nuevas tablas ni columnas.
- **No es HIGH**: no hay cross-system, no hay integraciones externas nuevas (Claude no se reinvoca), no hay datos sensibles nuevos. Riesgo contenido al servicio + endpoint + 1 página React.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | Reutiliza `ShoppingListService::create()` / repos existentes y la lógica de `convertToList()`. Refactor localizado. |
| Data | Low | No hay nuevas columnas ni migración. `payload_json` sigue siendo source of truth para los items del summary. Los índices seleccionados por el frontend deben validarse contra el payload en backend (no confiar en el cliente). |
| Security | **Medium** | (1) El endpoint debe verificar que `target_list_id` (si se pasa) pertenece al usuario autenticado — IDOR. (2) Los `selected_indices` deben validarse contra el rango real del `payload_json` para evitar errores o inyección de items inexistentes. (3) Autorización ya existente sobre el summary (`$summary->user_id !== $user->id`) debe mantenerse. |
| Performance | Low | Operación síncrona, escritura puntual en BD. Sin impacto en queries de larga ejecución. Tests deben verificar que no se introducen N+1 al recargar items de la lista destino. |
| Operational | Low | Sin cambios en jobs, mailers ni cron. El cambio es en endpoint y UI. **Decisión de PO: rollout directo a todos los usuarios, sin feature flag.** |

## Affected Areas

**Backend (Laravel):**
- `app/Services/WeeklySummaryService.php` — método `convertToList()` (rename o nuevo método `saveSummaryItems()`).
- `app/Http/Controllers/WeeklySummaryController.php` — endpoint convert.
- `app/Http/Requests/*` — nueva o modificada FormRequest para validar `selected_indices[]` y `target_list_id?`.
- `tests/Unit/Services/WeeklySummaryServiceTest.php` — nuevos casos.
- `tests/Feature/WeeklySummaryEndpointsTest.php` — nuevos casos.

**Frontend (React):**
- `resources/js/pages/WeeklySummaryPage.jsx` — checkboxes interactivos + selector de destino.
- `resources/js/lib/weeklySummaryApi.js` (o equivalente) — actualizar `convertSummaryToList` para enviar selección + destino.
- Tests: `WeeklySummaryPage.test.jsx`.
- Posible nuevo componente `<ListPicker />` (bottom sheet / modal) — la decisión de reutilizar o crear se toma en S3 (Technical Design) tras inspeccionar el repo.

**Sin cambios:**
- Generación del summary (Claude, scheduling, mail).
- Modelo `WeeklySummary` y migraciones.

## Resolved Decisions

1. **Selección por defecto**: todos los items marcados al abrir la página (mantiene affordance actual de `defaultChecked`).
2. **Lista destino — qué mostrar**: solo listas con `status = Active`. Archivadas excluidas. La opción "Nueva lista" respeta el límite freemium de 3 listas activas: si ya hay 3, el botón "Nueva lista" queda deshabilitado con feedback `FREEMIUM_LIMIT` (mismo error existente).
3. **Items duplicados**: al añadir a lista existente, si ya existe un item con mismo `name` (case/trim normalizado, según convención actual de listas), se **incrementa `quantity`** sumando la cantidad del summary. Mismo comportamiento que ya existe al añadir items en listas — reutilizar la lógica del `ShoppingListService` correspondiente (verificar en S3 qué método aplica).
4. **Posición en lista existente**: al final (mayor `position` + 1, append). Decisión de bajo impacto.
5. **Mutación del payload + visibilidad del summary** *(cambio de semántica respecto al actual)*:
   - `WeeklySummary.payload_json` deja de ser inmutable. Tras cada guardado (parcial o total), se eliminan del payload los items efectivamente guardados.
   - Si el payload queda con items pendientes → el summary sigue visible y permite nuevas conversiones a otras listas (nueva o existente).
   - Si el payload queda vacío → el summary se oculta del endpoint `latest`. El mecanismo de marcado (nuevo estado `WeeklySummaryStatus::Actioned` vs columna `actioned_at`) es decisión técnica que se concreta en S3 — ambas opciones cumplen el requisito funcional. El flag `weekly_summary_in_app_dismissed_at` (botón descartar manual) se mantiene como mecanismo paralelo independiente.
   - Esto corrige un bug latente actual: hoy convertir no marca nada y el summary sigue mostrando los mismos items, permitiendo re-conversión silenciosa.
6. **Mínimo de selección**: se exige ≥1 item. El botón "Guardar" queda deshabilitado con 0 seleccionados.
7. **UX picker destino**: **bottom sheet** desplegable desde abajo (mobile-first, coherente con design language Stitch actual de la página). En desktop el mismo componente se renderiza como modal centrado. Contenido: header "Guardar en…", lista de listas activas con item-count, botón "+ Nueva lista" (deshabilitado si hay 3 activas), botón confirmar.

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] **Require PRD (MEDIUM → STEP 2)**
- [ ] Escalate to architect

> Justificación: cambia contrato de endpoint, modifica servicio con lógica de negocio, afecta UI con redesign de interacción (checkboxes + selector destino). PRD necesario para fijar respuestas a TBDs antes de Tech Design.

## Transition

- Gate: **S1 PASSED** (todos los TBDs resueltos por el usuario antes de avanzar)
- Next Step: **STEP 2 (PRD Writing)**
