# Scope Analysis: FEAT-LISTS-MOVE-ITEMS

## Feature Request

Permitir mover **uno o varios items** de una lista de compra a otra lista. La operación debe:

- Eliminar el item de la lista origen.
- Crear el item en la lista destino, o **incrementar `quantity`** si ya existe un item compatible (mismo nombre normalizado + misma unidad + no comprado).
- Conservar el estado `is_purchased` del item original (mover items comprados está permitido).
- Permitir como destino solo listas **activas** sobre las que el usuario tiene **permiso de escritura** (owner o colaborador con write). Excluir la lista origen del listado.
- Reutilizar el componente `SaveTargetSheet` ya creado en FEAT-REC-SAVE-PARTIAL para selección de destino.

> Origen del cambio: necesidad funcional. Hoy no existe ninguna acción "mover"; el usuario tendría que copiar manualmente y borrar.

## Bounded Context

| Attribute | Value |
|-----------|-------|
| Context name | default (list-items / shopping-lists) |
| Glossary | `docs/contexts/default/00-glossary.md` (existe; se extenderá en S2 con el término "Mover item") |
| New domain terms introduced | "Mover item" (acción), "Lista origen" / "Lista destino" en este contexto. Reutiliza: `ShoppingList`, `ListItem`, `ListCollaborator`. |

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **MEDIUM** |
| Estimated Effort | 6–9 horas (backend + frontend + tests) |
| Confidence | High |

## Justification

- **Modifica lógica de negocio existente**: introduce un nuevo flujo en `ListItemService` que combina delete (origen) + upsert (destino) en una sola transacción.
- **Nuevo endpoint**: `POST /lists/{list}/items/move` o equivalente; cambia contrato API.
- **UI con cambios moderados**: introduce "modo selección" en el detalle de lista y reutiliza `SaveTargetSheet`. No es redesign completo de la página.
- **Sin migración de schema**: solo movimientos entre filas existentes (`list_items.shopping_list_id` cambia o se borra/recrea según implementación).
- **No es HIGH**: sin cross-system, sin integraciones externas nuevas, sin datos sensibles nuevos. Riesgo contenido.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | Reutiliza `ListItemService::createOrIncrement` ya existente y patrón de `lockForUpdate`. La operación move = delete + createOrIncrement dentro de transacción. |
| Data | **Medium** | Pérdida potencial: si se mueven items comprados con `producto_historial` ya registrado (creado en `togglePurchased`), el movimiento NO debe duplicar histórico. Hay que asegurar que mover ≠ marcar comprado de nuevo. Verificar relaciones de `ListItem` con `ProductoHistorial` en S3. |
| Security | **Medium** | (1) **IDOR sobre lista origen Y destino**: ambas deben pertenecer al usuario con permiso de write. (2) **Items que no pertenecen a la lista origen** (cliente envía IDs cruzados): validar que cada `item_id` pertenece a `source_list_id`. (3) Listas archivadas como destino → rechazar. |
| Performance | Low | Operación O(N) sobre items seleccionados. Cap razonable (e.g. max:50). Reutiliza el patrón de transacción única + locks de FEAT-REC-SAVE-PARTIAL. |
| Operational | Low | Sin cambios en jobs, mailers, cron. No requiere feature flag. Rollout directo. |

## Affected Areas

**Backend (Laravel):**
- `app/Services/ListItemService.php` — nuevo método `moveItems(User $user, ShoppingList $source, ShoppingList $target, array $itemIds)`.
- `app/Http/Controllers/ListItemController.php` — nuevo endpoint.
- `app/Http/Requests/MoveListItemsRequest.php` — nueva FormRequest.
- `routes/api.php` — nueva ruta.
- `tests/Unit/Services/ListItemServiceTest.php` — tests del nuevo método.
- `tests/Feature/` — tests del endpoint.

**Frontend (React):**
- `resources/js/pages/ListDetailPage.jsx` — modo selección + barra de acción "Mover".
- `resources/js/components/weekly-summary/SaveTargetSheet.jsx` — extender para aceptar `excludeListId` (excluir lista origen) y un `mode` opcional ("save"/"move") que cambie textos. Mejor: refactor a componente más genérico, p.ej. mover a `resources/js/components/lists/TargetListSheet.jsx` y dejar `SaveTargetSheet` como wrapper. Decisión final en S3.
- `resources/js/lib/listsApi.js` (o equivalente) — nueva función `moveItems(sourceId, targetId, itemIds)`.
- Tests Jest+RTL.

**Sin cambios:**
- `WeeklySummary` y su flujo.
- `ProductoHistorial` (asumido — verificar en S3).
- Schema DB.

## Resolved Decisions

1. **Selección múltiple**: long-press o botón "Seleccionar" en header del detalle activa modo selección. Marcar varios items, aparece barra inferior con "Mover N items".
2. **Destino**: solo listas **activas** existentes. NO se ofrece "crear nueva" desde este flow (el usuario tiene el botón estándar de crear lista en el dashboard).
3. **Items comprados**: se permiten mover. Conservan `is_purchased=true`.
4. **Duplicados en destino**: reusar `ListItemService::createOrIncrement` — suma `quantity` si match (name normalizado + misma unit + no purchased). Items con distinta unit o purchased=true se crean como nuevos. **Coherente con FEAT-REC-SAVE-PARTIAL**.
5. **Listas colaborativas**: permitido solo entre listas donde el usuario tiene **write permission** (owner directo, o colaborador con `mode->allowsWrite() === true`). Reusar `authorizeListWrite` ya existente del controller.
6. **UI trigger**: bottom sheet reutilizando `SaveTargetSheet` (extendiéndolo o refactorizando a componente compartido). Misma UX que el flow de recomendaciones, consistente.
7. **Mínimo de selección**: ≥1 item para activar el botón "Mover".
8. **Lista origen excluida** del listado de destinos en el sheet.
9. **Rollout**: directo, sin feature flag.

## Open Questions (a resolver en S2/S3)

Ninguna abierta. Detalles técnicos diferidos a S3:
- Si refactorizamos `SaveTargetSheet` o lo extendemos con props.
- Si reutilizamos endpoint de listas activas existente (`GET /lists`) o creamos uno scoped.
- Lock granularity: `lockForUpdate` sobre origen + destino + items, o solo sobre items.
- Comportamiento del `position` en destino (al final, igual que `createOrIncrement`).

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] **Require PRD (MEDIUM → STEP 2)**
- [ ] Escalate to architect

> Justificación: nuevo contrato de endpoint, modifica servicio con lógica transaccional, afecta UI con nuevo modo de selección. PRD necesario para fijar ACs antes del diseño técnico.

## Transition

- Gate: **S1 PASSED** (sin TBDs activos)
- Next Step: **STEP 2 (PRD Writing)**
