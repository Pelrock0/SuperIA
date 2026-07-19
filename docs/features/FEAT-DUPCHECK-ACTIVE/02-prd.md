# PRD: FEAT-DUPCHECK-ACTIVE — Duplicate Check Against Active Items Only

## Bounded Context

- **Context**: default (list-items)
- **Glossary**: `docs/contexts/default/00-glossary.md` (updated en este PRD con `Duplicado`, `Forma normalizada`, `Variante plural`)

## Business Objective

Reducir fricción al añadir items a la lista de compra. Hoy, cuando el usuario añade un producto cuyo nombre coincide con un **item comprado** ya presente en la **sección de comprados**, el sistema muestra el warning "Ya tienes X en la lista. ¿Aumentar cantidad?" — lo que es incorrecto desde el punto de vista del usuario: ese item ya fue comprado, lo natural es añadirlo de nuevo como **item pendiente** para una próxima compra.

Valor: el usuario añade productos que recompra con frecuencia (pan, leche, tomates) sin interrupciones, y la lista refleja el estado real (un nuevo pendiente, no un duplicado contra un histórico).

## Problem Statement

**Afectados**: todos los usuarios que mantienen items comprados en la sección inferior de una lista activa (comportamiento por defecto, los comprados permanecen hasta que el usuario los limpie o expiren por TTL).

**Pain actual**:

1. Añadir un producto recurrente recientemente comprado dispara un warning espurio.
2. La opción "Aumentar cantidad" propuesta no tiene sentido sobre un item comprado: re-activaría una compra ya hecha o crearía un estado ambiguo.
3. Adicionalmente, la regla de detección actual (`similarText > 0.80`) falla en palabras cortas: "pan" vs "panes" da 0.75 → no detecta el duplicado entre dos pendientes. La feature aprovecha para corregir esto.

## Scope

### In Scope

1. **Frontend — filtrar duplicado contra activos**: la función `findDuplicate` en `AddItemInput.jsx` y `AddItemModal.jsx` ignora items con `is_purchased = true`. El warning `DuplicateWarning` solo aparece si el match es contra un item pendiente.

2. **Frontend — detección singular/plural**: la regla de match incluye normalización singular→plural en español. "pan" matchea "panes", "tomate" matchea "tomates", "cebolla" matchea "cebollas" (lista completa en AC-5). Se mantiene el match exacto y el fuzzy `similarText > 0.80` como fallback para typos.

3. **Backend — delete del comprado homónimo al añadir**: en `ListItemService::create()` (que sirve a `POST /api/lists/{list}/items` — endpoint usado tanto por "Añadir" normal como por "Añadir de todas formas"), cuando se crea un nuevo item pendiente, eliminar previamente todos los items con `is_purchased = true` de la misma lista cuya **forma normalizada** (lowercase + sin tildes + reducción singular) y unidad coincidan con el nuevo item. Operación dentro de la transacción del add. `incrementQuantity()` **no** se modifica (opera sobre un item ID concreto; no aplica la regla).

4. **Backend — extender match exacto con normalización en `createOrIncrement`**: este método lo usa `WeeklySummaryService` (guardar selección del resumen semanal). Actualmente matchea con `LOWER(TRIM(name)) = ?`. Extender a forma normalizada (incluye reducción singular/plural) para que "pan" sobre "panes" pendiente incremente la cantidad existente. También debe aplicar la regla de delete del comprado homónimo (consistencia con `create()`).

5. **Helper compartido de normalización**: implementar un normalizador determinista de nombres de producto que reduzca a forma canónica (lowercase + strip tildes + reducción singular en español). Disponible en backend (PHP) y frontend (JS). Sin dependencia de modelos NLP.

6. **Tests**: cobertura 100% sobre helper, frontend `findDuplicate` actualizado y backend `create`/`createOrIncrement` con delete de comprados.

### Out of Scope

- Cambio visual del componente `DuplicateWarning` (mismo diseño, solo cambia cuándo se muestra).
- Stemming general más allá de plurales españoles (no se cubre conjugación verbal, género, diminutivos).
- Sinónimos o traducciones ("milk" vs "leche").
- Limpieza histórica de comprados por edad (cubre FEAT-PURCHASED-TTL).
- Cambio de la regla `similarText` global o del threshold 0.80.
- Match contra items de **otras listas** del mismo usuario.
- Notificación al colaborador cuando se elimina su item comprado por add homónimo.
- Migración o backfill de datos.

## Acceptance Criteria

### AC-1: No warning al añadir item homónimo de un item comprado

- **Given**: una lista con un item comprado "Pan" (`is_purchased = true`) en la sección de comprados, sin items pendientes con ese nombre
- **When**: el usuario escribe "Pan" en el input de añadir y confirma
- **Then**: no se muestra `DuplicateWarning`; el item se crea como pendiente; el item comprado "Pan" se elimina de la lista; tras la operación la lista contiene un único "Pan" pendiente

### AC-2: No warning al añadir variante plural de un item comprado

- **Given**: una lista con item comprado "Panes" y ningún pendiente homónimo
- **When**: el usuario añade "pan"
- **Then**: sin warning; se crea "pan" pendiente; "Panes" comprado se elimina

La relación es **simétrica**: ambas formas (singular/plural) normalizan a la misma raíz, por lo que la dirección input→existente o existente→input es irrelevante. Casos equivalentes (todos deben comportarse igual a AC-2):

- comprado "tomate" + añadir "tomates"
- comprado "tomates" + añadir "tomate"
- comprado "cebollas" + añadir "Cebolla"
- comprado "manzana" + añadir "MANZANAS"

### AC-3: Warning sí se muestra contra item pendiente homónimo

- **Given**: una lista con item pendiente "Pan" (`is_purchased = false`)
- **When**: el usuario añade "panes"
- **Then**: aparece `DuplicateWarning` con `matchedName = "Pan"`; el usuario puede elegir "Añadir de todas formas" o "Aumentar cantidad"

### AC-4: Múltiples comprados homónimos se eliminan todos

- **Given**: una lista con dos items comprados ambos llamados "Pan" (caso residual; mismo nombre+unidad) y sin pendientes
- **When**: el usuario añade "Pan"
- **Then**: ambos comprados se eliminan; queda un único "Pan" pendiente

### AC-5: Reglas de normalización singular/plural (mínimo aceptado)

Para cada par, añadir el primero debe matchear/eliminar al segundo y viceversa:

| Singular | Plural |
|----------|--------|
| pan | panes |
| tomate | tomates |
| cebolla | cebollas |
| leche | leches |
| manzana | manzanas |
| papel | papeles |
| lápiz | lápices |
| agua | aguas |
| limón | limones |
| flor | flores |
| arroz | arroces |
| pez | peces |
| luz | luces |

Regla `-z → -ces` aplica en plurales de palabras terminadas en `z`.

**Invariables — el normalizador NO debe alterarlos** (palabras que no pluralizan en español; stripear la `-s` final rompería el match consigo mismas):

- "lunes" → permanece "lunes"
- "martes" → permanece "martes"
- "crisis" → permanece "crisis"
- "tesis" → permanece "tesis"
- "atlas" → permanece "atlas"

Criterio: palabras terminadas en `-s` cuya forma sin la `-s` no es una raíz válida (≤2 caracteres) o coincide con un set conocido de invariables. La implementación concreta se define en S3; los tests cubren los casos listados.

### AC-6: Unidad distinta no dispara delete

- **Given**: lista con item comprado "Leche" unidad `L` y sin pendientes
- **When**: el usuario añade "Leche" unidad `ml`
- **Then**: el comprado NO se elimina; se crea nuevo pendiente "Leche" `ml`; ambos coexisten

### AC-7: Match exacto y fuzzy siguen funcionando para typos en pendientes

- **Given**: lista con pendiente "Tomate cherry"
- **When**: usuario añade "Tomate cheery" (typo, similarText ≈ 0.92)
- **Then**: aparece `DuplicateWarning` matchando contra "Tomate cherry" (regla fuzzy original conservada)

### AC-8: Diferentes nombres no matchean por coincidencia parcial corta

- **Given**: lista con comprado "pollo"
- **When**: usuario añade "polla"
- **Then**: no se elimina nada; se crea "polla" como pendiente; el comprado "pollo" permanece (existing behavior — la normalización no introduce falsos positivos)

### AC-9: Transacción atómica en backend

- **Given**: cualquier escenario de add que requiera eliminar comprados homónimos
- **When**: ocurre un fallo durante el delete del comprado o el create del pendiente
- **Then**: la transacción hace rollback; el estado de la lista no cambia; se devuelve error 500 al cliente; los counters de la lista no se actualizan

### AC-10: Caso mixto — pendiente y comprado homónimos coexisten

- **Given**: una lista con pendiente "pan" y comprado "panes" simultáneamente; el usuario añade "pan"
- **When**: aparece `DuplicateWarning` matchando contra el pendiente "pan" (AC-3 aplica)
- **Then**:
  - Si el usuario elige **"Aumentar cantidad"**: se incrementa la cantidad del pendiente "pan" vía `PATCH /items/{id}/increment-quantity`. El comprado "panes" **permanece intacto** (la acción del usuario es sobre el pendiente identificado; el comprado es irrelevante a esa operación).
  - Si el usuario elige **"Añadir de todas formas"**: se crea un nuevo pendiente "pan" vía `POST /items`. El comprado "panes" **se elimina** (el endpoint `create` aplica la regla de delete homónimo descrita en AC-1). El pendiente original "pan" permanece sin modificar. Estado final: dos pendientes "pan" + cero comprados homónimos.

Justificación: la semántica de cada endpoint determina el alcance del delete del comprado.

- `incrementQuantity` opera sobre un item específico ya identificado → no toca otros items.
- `create` (la operación de "añadir") aplica siempre la regla de limpieza del comprado homónimo, sin importar si fue precedido por un warning o no.

### AC-11: Colaboradores ven el resultado consistente

- **Given**: lista compartida vista por dos colaboradores; user A tiene un comprado "Pan"
- **When**: user B añade "Pan"
- **Then**: tras refresh/realtime sync ambos ven un único "Pan" pendiente; el log de actividad registra el `ItemAdded` (no se registra explícitamente el delete del comprado homónimo en activity log — el delete es side effect del add)

## UX Decision

- **UX Designer Required**: NO
- **UX Artifacts**: N/A (sin cambio visual; mismo componente `DuplicateWarning` se reutiliza)
- **Basic UX Notes**:
  - `DuplicateWarning` solo se muestra cuando el match es contra item pendiente.
  - Sin cambios en estilos, textos, layout, accesibilidad o flujo de teclado del componente.
  - El delete del comprado homónimo es **invisible** al usuario: no aparece toast, ni confirmación, ni undo. El usuario ve la lista actualizada con solo el nuevo pendiente.
  - Cambio observable en la sección de comprados: el item homónimo desaparece tras añadir. Es coherente con la acción del usuario ("estoy comprando este producto de nuevo").

> ⚠️ S5-UX review necesaria: aunque no hay cambio visual, hay cambio de comportamiento UI (warning suprimido en cierto caso, delete silencioso de comprados). Validar en S5-UX que la experiencia es coherente con el modelo mental del usuario.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Stemmer over-match: "pollo"/"polla", "casa"/"casas"/"caso", etc. | Technical | Reglas conservadoras de depluralización: solo aplicar si la forma corta resulta en una raíz de ≥3 caracteres y la diferencia es exclusivamente sufijo `-s`/`-es`/`-ces`. Test exhaustivo (AC-5, AC-8). |
| Stemmer divergente entre PHP y JS produce comportamiento inconsistente | Technical | Definir las reglas como tabla de casos en `02-prd.md` (AC-5) que actúa como contrato de tests. Ambos helpers comparten los mismos casos de test (idénticos inputs/outputs). |
| Delete silencioso de comprados confunde al usuario que esperaba ver el histórico | Data / UX | El histórico de compra real vive en `productos_historial` (vía `togglePurchased`), no en el `ListItem` comprado. La fila del comprado en la lista no es histórico autoritativo. Confirmar en S5-UX. |
| Race condition: dos colaboradores añaden el mismo item simultáneamente | Technical | `createOrIncrement` ya usa `lockForUpdate` sobre la lista. La operación de delete del comprado homónimo va dentro de la misma transacción. |
| Interacción con FEAT-PURCHASED-TTL (limpieza por edad) | Technical | FEAT-DUPCHECK-ACTIVE se implementa primero. TTL opera por edad sobre comprados; no entra en conflicto. Documentar orden en el technical design de TTL. |
| Performance: query adicional por add | Performance | Una sola consulta `WHERE shopping_list_id = ? AND is_purchased = true AND normalized_name = ?`. Volúmenes esperados: <100 items por lista. Coste despreciable. Sin nuevos índices necesarios (lookups por `shopping_list_id`). |
| Test coverage de combinaciones lingüísticas | Technical | AC-5 fija la lista mínima. Stem aislado y testable; tests parametrizados por par singular/plural. |

## Assumptions

- Los items comprados de la lista **no** son la fuente de verdad del histórico de compra. La fuente es `productos_historial` (alimentada por `ProductoHistorial::recordPurchase` al marcar como comprado).
- El comportamiento actual de `clearCompleted` (borrar todos los comprados) es aceptado por el usuario, así que el delete silencioso por add homónimo está dentro del mismo modelo mental.
- Existe en el backend un mecanismo para normalizar acentos (intl extension PHP o helper propio).
- La lista de pares singular/plural de AC-5 cubre el 95% de los productos de compra habituales en español. Para casos no cubiertos por las reglas, el comportamiento degrada a match exacto sin error.

## Open Questions

Ninguna. Decisiones cerradas en S1.

## Approval

- [ ] PRD approved by [user] on [pending]

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design (architect)
- Required Artifacts for Next Step: 02-prd.md
