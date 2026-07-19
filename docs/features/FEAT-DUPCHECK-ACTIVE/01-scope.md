# Scope Analysis: FEAT-DUPCHECK-ACTIVE

## Feature Request

Cuando el usuario añade un item cuyo nombre coincide con un **item comprado** que aparece en la sección inferior de la lista, el sistema muestra incorrectamente el aviso "Ya tienes X en la lista" y propone "Aumentar cantidad". Ese check debe operar exclusivamente contra **items pendientes** (`is_purchased = false`).

Comportamiento esperado al añadir un item cuyo nombre coincide con un item comprado:

1. El check de duplicado **no se dispara** (no warning, no propuesta de incremento).
2. El backend **elimina el item comprado** existente que coincida (mismo nombre normalizado y misma unidad).
3. El backend **crea un nuevo item pendiente** con el payload recibido.

Resultado final: en la lista queda únicamente el nuevo item pendiente; el item comprado anterior y su histórico de fila se pierden (el registro en `productos_historial` permanece, ya que `togglePurchased` lo registra al marcar como comprado).

## Bounded Context

| Attribute | Value |
|-----------|-------|
| Context name | default (list-items) |
| Glossary | `docs/contexts/default/00-glossary.md` (exists) |
| New domain terms introduced | Ninguno. Se reutilizan "Item pendiente" e "Item comprado" |

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | MEDIUM |
| Estimated Effort | 4 hours |
| Confidence | High |

## Justification

- Modifica lógica de negocio existente en `ListItemService` (semántica de add/createOrIncrement frente a items comprados).
- Afecta a múltiples componentes frontend (`AddItemInput.jsx`, `AddItemModal.jsx`) y al servicio backend.
- No requiere migración de base de datos ni cambios de esquema.
- Sin integraciones externas. Sin datos sensibles nuevos.
- Cumple criterio MEDIUM: "Business logic modified" + "Multiple components affected".

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Med | Requiere normalizador singular/plural en español compartido frontend/backend (sufijos `-s`, `-es`, casos `-z → -ces`, palabras invariables). Riesgo de over-match si las reglas son demasiado agresivas. Mitigación: lista cerrada de casos de prueba en S2, helper aislado con tests unitarios. |
| Data | Med | Eliminar items comprados al añadir mismos productos descarta la fila histórica de la lista. Mitigación: `productos_historial` ya registra la compra al marcar (`ProductoHistorial::recordPurchase` en `togglePurchased`), por lo que el histórico de compra se preserva fuera de la lista. Confirmar que el flujo "feat purchased-ttl" no dependa de la presencia del registro `ListItem` comprado. |
| Security | Low | El alcance se mantiene en items de listas accesibles al usuario autenticado (mismas policies que el add actual). Sin nuevas superficies. |
| Performance | Low | Una consulta adicional por add (`SELECT ... WHERE is_purchased = true AND LOWER(name) = ?`). Bajo coste; misma lista (índice por `shopping_list_id`). |
| Operational | Low | Cambio sin migración. Despliegue sin downtime. Rollback = revertir commit. |

## Affected Areas

**Backend**
- `app/Services/ListItemService.php` — `createOrIncrement()` y/o `create()`: añadir lógica de delete del item comprado coincidente antes de crear el nuevo pendiente. Operar dentro de transacción existente.
- Tests en `tests/Unit/Services/ListItemServiceTest.php` (si existe) o `tests/Feature/` equivalente.

**Frontend**
- `resources/js/components/items/AddItemInput.jsx` — `findDuplicate()` debe filtrar `existingItems` por `item.is_purchased === false` (o equivalente según shape del item).
- `resources/js/components/items/AddItemModal.jsx` — mismo filtro en `findDuplicate()`.
- Tests asociados (`AddItemInput.test.jsx`, `AddItemModal.test.jsx`) — añadir caso: item comprado homónimo no dispara warning.

**Sin cambios**
- No se modifica `DuplicateWarning.jsx` (sigue válido para duplicados contra activos).
- No se modifica esquema DB ni migraciones.
- No se modifica `togglePurchased` ni `productos_historial`.

## Match Rule (decisión resuelta)

El match debe reconocer **variantes singular/plural en español**: "pan" ↔ "panes", "cebolla" ↔ "cebollas", "tomate" ↔ "tomates", etc. La regla actual del backend (`LOWER(TRIM(name)) = ?`) es demasiado estricta y la `similarText > 0.80` del frontend falla en palabras cortas ("pan"/"panes" ≈ 0.75 → no match).

**Regla acordada**:

1. Normalización determinista: `lowercase` + `trim` + strip de tildes + reducción singular/plural según reglas comunes del español (sufijos `-s` y `-es`).
2. Match positivo si las formas normalizadas son iguales **y** la unidad coincide (null o igual).
3. Se aplica tanto al check de duplicado frontend (warning contra activos) como al delete backend del comprado homónimo.

**Multi-match**: si existen 2+ items comprados que matcheen el nombre normalizado + unidad, se borran todos (estado final limpio).

El stemmer/normalizador concreto y su ubicación (helper compartido frontend/backend) se especifican en S3 Technical Design. En S2 PRD se cierran los casos de prueba aceptados (lista mínima: pan/panes, cebolla/cebollas, tomate/tomates, leche/leches, manzana/manzanas, papel/papeles, lápiz/lápices, agua/aguas).

## Open Questions

Ninguna pendiente para avanzar a S2.

Notas para fases posteriores (no bloquean S1):

- **S3**: decidir si el stemmer vive en `app/Support/Inflector/SpanishInflector.php` y se replica en `resources/js/lib/spanishInflector.js`, o si se centraliza en backend exponiendo endpoint de check (descartado por latencia, esperado client-side).
- **S3**: confirmar lista completa de reglas (terminaciones `-z → -ces`, palabras invariables, acentuación de plurales tipo `lápiz/lápices`).
- **Interacción con FEAT-PURCHASED-TTL**: ambas features tocan items comprados. Orden de implementación: DUPCHECK-ACTIVE primero (no depende de TTL). TTL después.

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1
- Gate Status: PENDING (awaiting user approval)
- Next Step: STEP 2 — PRD Writing (product-owner agent)
