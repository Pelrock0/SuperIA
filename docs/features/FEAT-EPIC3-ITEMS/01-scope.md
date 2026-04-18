# Scope Analysis: FEAT-EPIC3-ITEMS

## Feature Request

Epic 3 — Items dentro de una Lista. 6 user stories:

- **HU-301**: Ver detalle de lista con items (pendientes arriba, comprados abajo, agrupados por categoria, contador progreso X de Y, gestos tactiles movil)
- **HU-302**: Añadir item (nombre obligatorio max 80 chars, cantidad/unidad/categoria/precio opcional, unidades: kg/g/L/ml/ud/pack)
- **HU-303**: Marcar item como comprado (toggle checkbox, tachado visual, baja al final, registrar en tabla `producto_historial` para alimentar Epic 5)
- **HU-304**: Editar item (panel edicion: nombre, cantidad, unidad, categoria, precio)
- **HU-305**: Eliminar item (swipe o boton, sin confirmacion, undo 5 segundos via snackbar)
- **HU-306**: Limpiar items comprados (accion en menu, confirmacion, solo elimina marcados)

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 35-45 hours |
| Confidence | High |

## Justification

HIGH because:
1. **Cross-feature data pipeline**: HU-303 alimenta tabla `producto_historial` que es combustible para Epic 5 (sugerencias, reposicion, resumen semanal). Decisiones de schema aqui afectan multiples features futuras.
2. **Database migrations**: 2 tablas nuevas (`list_items`, `producto_historial`). Items con relaciones a lista y usuario. Historial independiente de listas.
3. **Business logic compleja**: Agrupacion por categoria, reordenamiento (comprados al final), progreso count que actualiza `shopping_lists.items_total/items_completed`, undo temporizado.
4. **Multiple UI components**: ListDetailPage, AddItemSheet, ItemRow, EditItemPanel, UndoSnackbar, ProgressBar. Interacciones complejas (swipe, toggle, undo timer).
5. **Real-time counters**: `items_total` e `items_completed` en `shopping_lists` deben mantenerse sincronizados con las operaciones de items.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Medium | Counter sync (items_total/items_completed) must be atomic. Undo mechanism needs temporary soft-delete or frontend-only approach. Swipe gesture on mobile. |
| Data | High | `producto_historial` schema impacts Epics 5-9. Must design for: frequency analysis, co-occurrence, seasonal patterns. Wrong schema now = expensive migration later. |
| Security | Low | All behind existing JWT auth. Ownership via list->user_id chain. |
| Performance | Medium | Items grouped by category requires sorting. Lists with many items (50+) need efficient rendering. Counter updates on every check/uncheck. |
| Operational | Low | No external dependencies. No background jobs. |

## Affected Areas

- **database/migrations/** — New `list_items` table, new `producto_historial` table
- **app/Models/** — New `ListItem`, `ProductoHistorial` models. Update `ShoppingList` (hasMany items)
- **app/Enums/** — New `ItemUnit` enum (kg, g, L, ml, ud, pack), `ProductCategory` enum
- **app/Services/** — New `ListItemService`, `ProductoHistorialService`
- **app/Http/Controllers/** — New `ListItemController`
- **app/Http/Requests/** — New FormRequests for item CRUD
- **routes/api.php** — Item endpoints nested under lists
- **resources/js/pages/** — New `ListDetailPage`
- **resources/js/components/items/** — AddItemInput, ItemRow, EditItemPanel, UndoSnackbar, ProgressBar
- **resources/js/app.jsx** — Replace list detail placeholder route

## Resolved Questions

1. **producto_historial schema**: Campos del HU doc + `precio_real` (nullable decimal). Evita migracion extra en Epic 7 (HU-701/702 estimacion de precios).
2. **Categorias de producto**: Las 10 categorias de HU-802: Frutas y verduras, Carnes y pescados, Lacteos y huevos, Panaderia, Bebidas, Congelados, Limpieza, Higiene personal, Conservas, Otros.
3. **Undo mechanism (HU-305)**: Frontend-only. El backend borra inmediatamente. El frontend guarda el estado del item durante 5 segundos y lo re-crea via POST si el usuario pulsa "Deshacer". Sin soft-delete, sin cleanup job.

## Open Questions

None. All resolved.

## Recommendation

- [ ] Proceed directly (LOW -> STEP 1b)
- [x] Require PRD (MEDIUM/HIGH -> STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)
