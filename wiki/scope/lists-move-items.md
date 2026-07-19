# Scope — FEAT-LISTS-MOVE-ITEMS

Mover uno o varios items de una lista a otra: elimina del origen, crea o incrementa `quantity` en destino. Conserva `is_purchased`. Destino debe ser lista **activa** con permiso de escritura del usuario (owner o colaborador write).

## Clasificación

| Atributo | Valor |
|----------|-------|
| Complexity | MEDIUM |
| Effort | 6–9 h |
| Status | **S1 PASSED** (sólo scope; S2-S5 pendientes) |

## Historias / ACs (preliminares — definitivos en S2)

- Mover 1+ items entre listas propias activas
- Modo selección (long-press o botón) en `ListDetailPage`
- Bottom sheet reutiliza `SaveTargetSheet` (extender o refactorizar a componente compartido)
- Excluir lista origen del listado de destinos
- Items duplicados en destino: sumar `quantity` si match (name normalizado + unit + no purchased)
- Lock pesimista sobre items origen + destino

## Dependencias clave

- `ListItemService` — añadir `moveItems(User, sourceList, targetList, itemIds)` que combina delete + `createOrIncrement` en una transacción
- Reuso de `ListItemService::createOrIncrement` (creado en [[rec-save-partial]])
- Reuso de autorización write (`authorizeListWrite` controller helper)
- Frontend: `SaveTargetSheet` con prop `excludeListId` y `mode` (save/move)

## Decisiones de producto

- Solo listas **activas** existentes — no se ofrece "+ Nueva lista" en este flow
- Items comprados se pueden mover, mantienen `is_purchased=true`
- Listas colaborativas permitidas si user tiene write permission
- Rollout directo, sin feature flag
- Mínimo 1 item para activar "Mover"

## Riesgos identificados

- **Security**: IDOR sobre origen Y destino; validar pertenencia + items pertenecen a lista origen
- **Data**: items comprados con `producto_historial` ya registrado — mover ≠ marcar comprado de nuevo (verificar relación con `ProductoHistorial`)
- **Performance**: cap razonable (max:50 items)

## Estado actual

Solo `01-scope.md` existe. Próximo paso: S2 (PRD Writing). Tech design y implementación pendientes.

Origen: `docs/features/FEAT-LISTS-MOVE-ITEMS/01-scope.md`.
