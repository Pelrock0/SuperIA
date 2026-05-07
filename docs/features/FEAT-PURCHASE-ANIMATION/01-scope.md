# Scope Analysis: FEAT-PURCHASE-ANIMATION

## Feature Request

Al marcar un item de la lista de la compra como comprado, el cambio ocurre demasiado rápido y el usuario no percibe el feedback. Se requiere:
1. Feedback visual inmediato y claro al marcar (green flash + tachado)
2. Delay de ~1.5s antes de que el item "se hunda" a la sección de comprados
3. Animación suave (slide/fade) al moverse a la sección inferior

## Bounded Context

| Attribute | Value |
|-----------|-------|
| Context name | list-items |
| Glossary | N/A (no docs/contexts/ en el proyecto) |
| New domain terms introduced | ninguno |

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | LOW |
| Estimated Effort | 2-4 horas |
| Confidence | High |

## Justification

- Cambio 100% frontend — cero modificaciones de backend, API, ni base de datos
- El toggle API sigue llamándose de forma optimista al instante
- Solo el reordenado visual (sink) se retrasa 1.5s en el cliente
- Cambio concentrado en `ItemRow.jsx` (componente compartido por `ListDetailPage` y `SharedListPage` automáticamente)
- Sin lógica de negocio nueva; es gestión de estado local temporal en el componente

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | `useState` local para "pending-sink" + `setTimeout` + CSS transition. Patrón estándar de React. |
| Data | N/A | Sin cambios en schema ni API |
| Security | N/A | Sin nuevas superficies de ataque |
| Performance | Low | Un `setTimeout` por toggle — negligible. Cleanup necesario en `useEffect` para evitar memory leak si el componente se desmonta durante el delay. |
| Operational | N/A | Sin cambios de despliegue |

## Affected Areas

- `resources/js/components/items/ItemRow.jsx` — estado local "pending-sink", clases de animación
- `resources/js/pages/ListDetailPage.jsx` — posiblemente el delay del reordenado si se gestiona en el padre
- `resources/js/pages/SharedListPage.jsx` — idem (comparte el mismo patrón de toggle)
- CSS (Tailwind) — clases de transición existentes, sin nuevos archivos

## Design Decision (pre-PRD)

Implementar el delay en `ItemRow.jsx` directamente:
1. Checkbox checked → estado local `justChecked = true` → clases CSS: fondo verde suave + tachado
2. `setTimeout(1500ms)` → llama `onToggle(item.id)` → padre actualiza estado real → item se mueve
3. El sink en el padre usa transición CSS para animar el reordenado

Esto centraliza el cambio en un único componente y beneficia automáticamente a `ListDetailPage` y `SharedListPage`.

## Open Questions

Ninguna. El comportamiento está suficientemente definido.

## Recommendation

- [x] Proceder directamente (LOW → STEP 1b)

## Transition

- Gate: S1
- Next Step: STEP 1b (Quick Scope para LOW)
