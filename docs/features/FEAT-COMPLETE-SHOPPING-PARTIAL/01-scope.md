# Scope Analysis: FEAT-COMPLETE-SHOPPING-PARTIAL

## Feature Request

Añadir un botón "Lista completada" en `ListDetailPage` que permita al usuario cerrar manualmente el viaje de compra **aunque queden items pendientes**.

Comportamiento esperado:

- El botón es visible solo cuando hay **al menos 1 item comprado** en la lista.
- Convive con el toggle individual de items (que sigue funcionando igual).
- Click → abre el `ConfirmPriceModal` existente (mismo modal que aparece hoy automáticamente al alcanzar 100% comprados — HU-702).
- Tras guardar precios: los items **no cambian de estado**. Comprados siguen comprados, pendientes siguen pendientes en la misma lista.
- Multi-cierre permitido sin límite (varios cierres en la misma lista, por ejemplo "compro lunes lo de oferta, miércoles lo restante").
- Snapshot histórico: reutiliza el endpoint y persistencia actual (`POST /api/lists/{list}/prices/confirm` → `PriceEstimationController::confirmPrices`). **Sin nueva entidad, sin migración.**

Use case core: el usuario va al súper, compra parte de la lista, vuelve a casa, marca los items comprados y pulsa "Lista completada" para capturar el coste real de esta compra parcial. El resto queda en la lista para la próxima salida (1-2 semanas).

## Bounded Context

| Attribute | Value |
|-----------|-------|
| Context name | default (list-items) |
| Glossary | `docs/contexts/default/00-glossary.md` (exists) |
| New domain terms introduced | `Cierre de compra`, `Sesión de compra parcial` (revisar nomenclatura con negocio en S2) |

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | LOW |
| Estimated Effort | 2 hours |
| Confidence | High |

## Justification

- **Frontend-only**: añade un botón + reutiliza modal existente. Sin nuevos endpoints.
- **No DB migration**: `confirmPrices` endpoint actual ya persiste lo necesario.
- **No nueva lógica de negocio**: el flujo backend ya existe (HU-702), solo se cambia el trigger (botón manual además de auto-100%).
- **Sin integraciones externas**, sin datos sensibles, sin colas/jobs nuevos.
- **Decisiones ya cerradas** en sesión de discusión previa (MVP simple, sin entidad nueva, multi-cierre sin límite).

Criterio LOW cumplido en `core.md`: simple frontend change, no migrations, no new business logic, no sensitive data.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | Botón nuevo en `ListDetailPage.jsx` + reutilización de `ConfirmPriceModal` y `confirmPrices` (endpoint existente). Sin cambios en backend services ni DB. |
| Data | Low | Multi-cierre permite que el mismo comprado se incluya en el cálculo de precios de N cierres sucesivos. Riesgo: doble registro de precios per-item si el usuario re-confirma el mismo comprado dos veces. Mitigación: documentar comportamiento en S2 PRD (el modal ya permite "Ahora no" / saltar). Si se considera bug, se trata en feature posterior. |
| Security | Low | El endpoint `confirmPrices` ya tiene auth (write permissions sobre la lista). Sin nueva superficie. |
| Performance | Low | Ninguna nueva consulta backend distinta de la actual. |
| Operational | Low | Cambio sin downtime, rollback = revertir commit. |

## Affected Areas

**Frontend**
- `resources/js/pages/ListDetailPage.jsx` — añadir botón "Lista completada" visible si `items_completed >= 1`. Handler que abre `ConfirmPriceModal` (estado existente `showConfirmPrice`).
- (Posible) `resources/js/components/price/ConfirmPriceModal.jsx` — filtrar la lista per-item para mostrar solo comprados (no pendientes). Decisión en S2 PRD.
- Tests asociados: nuevo test para el botón visible/no visible y para el flujo de apertura.

**Backend**
- **Sin cambios**. Reutiliza `PriceEstimationController::confirmPrices` y `POST /api/lists/{list}/prices/confirm`.

**Sin cambios**
- Esquema DB, migraciones, modelos, servicios backend.
- Toggle individual de items.
- Auto-trigger del modal al 100% (HU-702 sigue funcionando idéntico).
- FEAT-PURCHASED-TTL: sin interacción. Si se descarta TTL en favor de cierre manual, decisión separada.

## Open Questions

Ninguna pendiente — todas resueltas antes de cerrar S1.

### Decisiones cerradas

1. **Modal filtra solo comprados** (`is_purchased=true`). `ListDetailPage` debe filtrar antes de pasar a `ConfirmPriceModal`. Cambio mínimo en el componente (línea `existingItems={Object.values(items).flat()}` → filtrar).

2. **Texto del botón**: `Lista completada`. Coincide con el texto verde existente al 100%, reutiliza vocabulario.

3. **Posición**: junto al `PriceBar` en el footer. Mantiene contexto con totales/coste.

4. **Multi-cierre — último cierre gana**: el endpoint `confirmPrices` sobrescribe precios per-item. Documentado en PRD como comportamiento aceptado. Sin tracking de "ya capturado" en MVP.

## Recommendation

- [x] Proceed directly (LOW → STEP 1b)
- [ ] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

> Nota: aunque es LOW, hay 4 open questions de producto (nomenclatura, posición, filtro modal, doble registro) que conviene resolver. Recomiendo elevarlo a STEP 2 (PRD) en lugar de S1b para cerrarlas formalmente antes de implementar.

**Revisado**: dada la cantidad de open questions de producto/UX, **recomendación final** → require PRD (STEP 2), aunque la complejidad técnica es LOW.

- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1
- Gate Status: PENDING (awaiting user approval)
- Next Step: STEP 2 — PRD Writing (product-owner agent)
