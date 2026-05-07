# PRD: FEAT-REC-SAVE-PARTIAL — Guardado parcial de recomendaciones semanales

## Bounded Context

- **Context**: default (list-items)
- **Glossary**: `docs/contexts/default/00-glossary.md` — extendido en este PRD con: Resumen semanal, Recomendación, Guardar selección, Lista destino, Conversión parcial, Resumen actuado, Recomendación pendiente.

## Business Objective

Permitir al usuario aprovechar las recomendaciones semanales generadas por la IA con flexibilidad real:

- **Problema**: hoy la única acción disponible al recibir un resumen semanal es "convertir todo en lista nueva". El usuario no puede elegir qué le interesa ni añadirlo a una lista que ya tiene en marcha.
- **Importancia**: el flujo actual fuerza a crear una lista por cada resumen → multiplica listas activas y choca con el límite freemium (3 activas). Resultado: usuarios descartan resúmenes en lugar de usarlos. La IA produce valor que se desperdicia.
- **Valor**: recuperar la utilidad del resumen semanal como fuente de sugerencias accionables. Reduce fricción para el usuario freemium y aumenta engagement con la lista activa existente.

## Problem Statement

Afectados: todos los usuarios con resúmenes semanales generados (eligibles según `WeeklySummaryService::eligibleUsers`). Actualmente:

1. La página `/weekly-summary` muestra checkboxes decorativos (`readOnly`) y un único botón "Crear lista con N productos".
2. Convertir genera siempre una lista nueva; no hay opción de añadir a lista existente.
3. Tras convertir, el resumen no se marca actuado: si el usuario vuelve a la página puede re-convertir y crear listas duplicadas (bug latente).

## Scope

### In Scope

- Hacer **interactivos** los checkboxes de cada recomendación en `WeeklySummaryPage` (estado por defecto: todos marcados).
- Añadir **selector de lista destino** (componente nuevo: bottom sheet en mobile / modal en desktop) que permite:
  - Elegir una lista activa existente del usuario, o
  - Elegir "+ Nueva lista" (deshabilitado si ya hay 3 listas activas — límite freemium).
- Modificar el endpoint de guardado para aceptar:
  - `selected_indices: number[]` — subset de items del `payload_json`.
  - `target_list_id: number | null` — null = crear nueva, número = añadir a lista existente.
- Validación backend de seguridad:
  - `selected_indices` dentro del rango del `payload_json` real.
  - `target_list_id` (si llega) pertenece al `auth()->user()` y está en estado `Active`.
- Lógica de items duplicados al añadir a lista existente: si ya existe item con mismo `name` (normalizado), **incrementar `quantity`** (reutilizar lógica existente del `ShoppingListService`/items).
- **Mutación del `payload_json`** tras cada guardado: eliminar del payload los items efectivamente guardados.
- **Auto-marcado de "resumen actuado"** cuando el `payload_json` queda vacío → el endpoint `latest` lo oculta (mismo comportamiento que un summary descartado).
- Endpoint `POST /weekly-summary/{id}/save` (nuevo, reemplaza `convertToList` o lo complementa — decisión técnica para S3) que devuelve la lista destino actualizada.
- Tests backend (servicio + endpoint) y frontend (página + selector) con cobertura 100%.

### Out of Scope

- Reordenar manualmente los items dentro del resumen antes de guardar.
- Editar nombre/cantidad/unidad de una recomendación antes de guardarla (se guarda tal cual viene del payload).
- Cambiar la generación del resumen, el prompt de Claude, el scheduling o el mail de resumen.
- Modificar el botón "descartar" actual (`weekly_summary_in_app_dismissed_at`) — sigue funcionando como hoy en paralelo.
- Soporte para listas archivadas como destino (solo activas).
- Migrar resúmenes generados antes de este cambio (los existentes siguen el comportamiento legacy si se accede a ellos; nueva lógica aplica a partir del despliegue).
- Notificación / toast diferente al actual al guardar (se usa el mismo patrón `data-testid="convert-success"` ya en uso).
- Soporte para "deshacer guardado" (undo).

## Acceptance Criteria

### AC-1: Selección por defecto al abrir el resumen

- **Given**: el usuario tiene un resumen semanal con N recomendaciones pendientes.
- **When**: navega a `/weekly-summary`.
- **Then**: se muestran las N recomendaciones, **todas marcadas** por defecto. El botón "Guardar" muestra el contador "Guardar N items".

### AC-2: Desmarcar y marcar recomendaciones

- **Given**: el usuario ve el resumen con todos los items marcados.
- **When**: pulsa el checkbox de un item para desmarcarlo.
- **Then**: el item queda visualmente desmarcado, el contador del botón se actualiza, y volver a pulsarlo lo marca de nuevo. La operación no llama al backend; es estado local.

### AC-3: Botón Guardar deshabilitado con 0 selección

- **Given**: el usuario ha desmarcado todos los items.
- **When**: el contador llega a 0.
- **Then**: el botón "Guardar" queda deshabilitado, no permite click, y no abre el selector de destino.

### AC-4: Abrir el selector de lista destino

- **Given**: el usuario tiene ≥1 item seleccionado.
- **When**: pulsa "Guardar".
- **Then**: se abre el bottom sheet (mobile) / modal (desktop) titulado "Guardar en…", con la lista de sus listas activas (incluye contador de items por lista) y un botón "+ Nueva lista" en la parte superior.

### AC-5: Crear nueva lista con la selección

- **Given**: el usuario tiene <3 listas activas y N≥1 items seleccionados.
- **When**: pulsa "+ Nueva lista" en el selector y confirma.
- **Then**: se crea una lista nueva con nombre `Resumen semanal del DD/MM/YYYY` (preservando convención actual), emoji 📅, y los N items seleccionados como items pendientes (`is_purchased = false`, posiciones consecutivas desde 0). El usuario es redirigido a `/app/listas/{id}` tras 1.5s.

### AC-6: Bloqueo de "Nueva lista" por límite freemium

- **Given**: el usuario tiene 3 listas activas.
- **When**: abre el selector.
- **Then**: el botón "+ Nueva lista" aparece **deshabilitado** con mensaje visible "Has alcanzado el límite de 3 listas activas". Las listas existentes siguen seleccionables como destino.

### AC-7: Añadir a lista existente sin duplicados

- **Given**: el usuario selecciona "Aceite de oliva" del resumen y elige una lista existente que **no contiene** ese producto.
- **When**: confirma.
- **Then**: se crea un item nuevo en la lista existente con el nombre, cantidad y unidad del payload, posición = `max(position) + 1` de la lista, `is_purchased = false`. El usuario es redirigido a la lista.

### AC-8: Añadir a lista existente con duplicado → suma de cantidades

- **Given**: el usuario selecciona "Leche" (cantidad 2, unidad L) y la lista existente ya contiene "Leche" (cantidad 1, unidad L, pendiente).
- **When**: confirma.
- **Then**: el item existente queda con cantidad 3 (1+2). No se crea un item nuevo. El comportamiento es idéntico al que ya aplica al añadir items en la vista de lista (reutilización de lógica existente).

### AC-9: Mutación parcial del payload — quedan items pendientes

- **Given**: el resumen tiene 5 recomendaciones pendientes y el usuario selecciona 2 para guardar.
- **When**: la operación de guardado completa con éxito.
- **Then**: el `payload_json` del summary queda con las 3 recomendaciones no seleccionadas. Si el usuario vuelve a `/weekly-summary`, ve solo esas 3, todas marcadas, y puede hacer otra conversión.

### AC-10: Mutación total del payload — resumen queda actuado

- **Given**: el resumen tiene 3 recomendaciones pendientes y el usuario selecciona las 3.
- **When**: la operación completa con éxito.
- **Then**: el `payload_json` queda vacío y el summary se marca como **actuado** (estado nuevo o `actioned_at` — decisión técnica en S3). Al volver a `/weekly-summary`, el endpoint `latest` responde con `NO_SUMMARY_THIS_WEEK` o equivalente, y la página muestra el estado vacío "No hay resumen disponible esta semana".

### AC-11: Aislamiento de seguridad — IDOR sobre target_list_id

- **Given**: existe una lista activa de OTRO usuario con `id=999`.
- **When**: el usuario actual envía `POST /weekly-summary/{id}/save` con `target_list_id=999`.
- **Then**: el backend responde `404` (o `403`), no modifica la lista del otro usuario, no muta el payload del summary. Test negativo obligatorio.

### AC-12: Validación de selected_indices fuera de rango

- **Given**: el resumen tiene 3 items (indices válidos: 0,1,2).
- **When**: el cliente envía `selected_indices = [0, 5]`.
- **Then**: el backend responde `422` con error de validación, no crea ni modifica nada. Test negativo obligatorio.

### AC-13: Validación de selected_indices vacío

- **Given**: el cliente intenta forzar el endpoint con `selected_indices = []`.
- **When**: llega al backend.
- **Then**: responde `422`, no muta nada. Coherente con AC-3 que ya impide el caso desde el frontend.

### AC-14: target_list_id de lista archivada

- **Given**: existe una lista del usuario con `status = Archived`.
- **When**: envía `target_list_id` apuntando a esa lista.
- **Then**: backend responde `422` o `404`, no muta nada. Test negativo obligatorio.

### AC-15: Idempotencia en caso de fallo intermedio

- **Given**: el guardado se interrumpe entre la creación/actualización de items y la mutación del payload.
- **When**: la transacción no commitea.
- **Then**: ni se modifican items de la lista destino ni el payload del summary. La operación es atómica (transacción única). Si el cliente reintenta, no hay duplicados parciales.

## UX Decision

- **UX Designer Required**: **YES**
- **Razón**:
  - Modifica significativamente un componente UI existente (`WeeklySummaryPage`): los checkboxes pasan de decorativos a interactivos; el botón cambia su CTA y comportamiento.
  - Introduce un componente nuevo (selector de lista destino: bottom sheet / modal) que no existe hoy en la app.
  - Afecta el flujo principal del resumen semanal — no es un cambio cosmético.
- **UX Artifacts**: `docs/features/FEAT-REC-SAVE-PARTIAL/ux-wireframes.html` — **PENDIENTE**. Requiere invocar al ux-designer agent antes de aprobar S2.
- **Notas UX para el ux-designer**:
  - Mobile-first (Stitch designs). Desktop = mismo componente como modal centrado.
  - Mantener design language actual de `WeeklySummaryPage` (cards `borderRadius: 16px`, sombras suaves, `#003e54` primary, `#ffdad6` error, fuente Inter).
  - Bottom sheet: header "Guardar en…", lista vertical de listas activas (cada fila: emoji + nombre + contador de items), divisor, botón "+ Nueva lista" arriba o abajo con estado disabled si hay 3 listas activas (con texto explicativo).
  - Botón principal "Guardar N items" debe reflejar contador en tiempo real.
  - Estado vacío sin selección: botón disabled, texto "Selecciona al menos un item".
  - Considerar accesibilidad (focus trap en modal, aria-label en checkboxes, aria-live en contador) — referencia `.cursor/references/accessibility.md`.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| IDOR sobre `target_list_id` permitiría escribir items en listas de otros usuarios | Security | FormRequest valida pertenencia al `auth()->user()` antes de aceptar el ID. Test negativo obligatorio (AC-11). |
| `selected_indices` fuera de rango podría provocar excepción o inserción de items vacíos | Security / Data | Validación explícita: cada índice ∈ [0, count(payload)-1]. Tests AC-12, AC-13. |
| Mutación del `payload_json` rompe semántica histórica (era inmutable). Resúmenes existentes generados antes del despliegue mantienen el comportamiento legacy | Data | El cambio es retrocompatible: `payload_json` ya es JSON mutable a nivel de schema. La nueva lógica solo escribe sobre summaries que entran al nuevo flujo. Documentar en S3 la migración (si se decide algo más). |
| Pérdida de atomicidad: items insertados pero payload no actualizado (o viceversa) | Data | Transacción única (`DB::transaction`) que envuelve: (a) insert/update de items en lista destino, (b) actualización de `payload_json`, (c) marcado de `actioned_at` si payload queda vacío. Test AC-15. |
| Conflicto de cantidades al sumar duplicados con diferente unidad (ej. existing en `g`, nuevo en `kg`) | Data | Si el `unit` difiere, se trata como item distinto (no se suma) — comportamiento idéntico al actual al añadir items en la vista de lista. Confirmar en S3 cuál es la lógica exacta del existente y reusar. |
| Race condition: dos pestañas guardan a la vez sobre el mismo summary | Data | Lock pesimista o `update ... where payload_json = ?` (compare-and-swap) en la mutación del payload. Detalle a resolver en S3. |
| Re-render frecuente del contador en `WeeklySummaryPage` con muchos items | Performance | N de recomendaciones es típicamente <20 (limitado por prompt IA). Usar `useState` simple, no requiere optimización adicional. |
| Bottom sheet con muchas listas escala mal (scroll) | Performance / UX | En el alcance actual el límite freemium garantiza ≤3 listas activas, no es un riesgo. Si en el futuro se elimina el límite (planes premium), aplicar `max-height` al sheet con scroll interno; fuera del alcance de este feature. |
| Tests E2E faltantes para cobertura de flujo completo (selección + selector + guardado) | Operational | S5-TEST gate exige cobertura. Tests Jest + RTL para frontend; PHPUnit feature tests para endpoint. |
| El estado nuevo del summary (`Actioned` o `actioned_at`) podría requerir migración | Data | Si se opta por enum nuevo (`WeeklySummaryStatus::Actioned`), requiere migración. Si se opta por `actioned_at` timestamp, también. Decisión y migración corresponden a S3. Mitigación: usar enum (más explícito y consistente con la convención del proyecto). |

## Rollout

- **Directo a todos los usuarios**, sin feature flag. Decisión del PO. El cambio reemplaza el flujo actual de "convertir todo a lista nueva" — no se mantiene el comportamiento legacy en paralelo. Resúmenes generados antes del despliegue siguen visibles y entran al nuevo flujo en cuanto el usuario los abra.

## Assumptions

- La lógica de "mismo nombre = sumar cantidades" ya existe en el flujo de añadir items a una lista (a verificar y reusar en S3). Si no existe exactamente, se documenta la regla de normalización (trim + lowercase) en S3.
- El componente bottom sheet/modal será nuevo; no hay un componente reutilizable equivalente en el repo (a confirmar en S3 con búsqueda).
- El campo `payload_json` en `weekly_summaries` admite escrituras (es JSON mutable, no append-only). Confirmado por inspección de `WeeklySummaryService::generateForUser` que ya lo escribe.
- El usuario está autenticado vía `auth('api')` (mismo guard que el resto de endpoints `/weekly-summary/*`).
- El frontend ya tiene mecanismo para listar las listas activas del usuario (a confirmar en S3; si no, hay que añadir endpoint o reutilizar uno existente).

## Open Questions

Ninguna pendiente. Todos los TBDs del scope quedaron resueltos en `01-scope.md § Resolved Decisions`. Los detalles técnicos restantes (nombre exacto del estado, lock concurrente, endpoint de listas activas, lógica exacta de duplicados) son competencia de S3 (Technical Design) y no del PRD.

## Approval

- [ ] PRD approved by [user] on [date]
- [ ] `ux-wireframes.html` generated by ux-designer agent

## Transition

- Gate Status: **S2 PENDING** — bloqueado hasta que se genere `ux-wireframes.html`.
- Next Step: STEP 3 – Technical Design
- Required Artifacts for Next Step: `02-prd.md`, `ux-wireframes.html`
