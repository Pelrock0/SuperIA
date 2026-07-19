# Technical Design — FEAT-PURCHASED-ITEM-SINK

Cambio puro frontend. `SharedListPage` deriva dos valores (`pendingCategories`, `purchasedItems`) de su state `items` existente y reorganiza el render: pendientes agrupados por categoría arriba + sección "Ya en el carro" abajo. Replica el patrón ya en producción en `ListDetailPage`.

## Arquitectura

| Layer | Responsabilidad | Módulo |
|-------|----------------|--------|
| Backend | Sin cambios — endpoint ya ordena por `is_purchased, category, position` | — |
| API | Sin cambios | — |
| Frontend (state) | Derivar `pendingCategories` y `purchasedItems` de `items` | `SharedListPage.jsx` |
| Frontend (render) | Sección pendiente + sección comprados | `SharedListPage.jsx` |

## Flujo

```
API response → setItems(data.items)         [sin cambios]
     ↓
items: { category: [ListItem, ...], ... }   [sin cambios]
     ↓
pendingCategories = categoryKeys.filter(k => items[k].some(i => !i.is_purchased))
purchasedItems    = categoryKeys.flatMap(k => items[k].filter(i => i.is_purchased))
     ↓
Render:
  [pending section]
    pendingCategories.map(category =>
      items[category].filter(i => !i.is_purchased).map(renderItem)
    )
  [purchased section — solo si purchasedItems.length > 0]
    "Ya en el carro" header
    purchasedItems.map(renderItem)
```

## Decisiones de diseño

| Opción | Decisión |
|--------|----------|
| Replicar patrón de `ListDetailPage` verbatim | **Seleccionada** — reference implementation probada en prod |
| CSS-only (`order` property por `is_purchased`) | Rechazada — DOM order persiste, screen readers leen original |
| Optimistic update (mover item antes del re-fetch) | Rechazada — out of scope, riesgo nuevo |

## Performance

- O(n) sobre la lista de items en cada render — despreciable para listas <100 items
- Sin nuevas queries

## Seguridad

Sin cambios. `SharedListPage` sigue bajo share-token context. No expone datos nuevos.

## Gotchas

- Tests existentes de `SharedListPage` rompen por nueva estructura DOM — actualizar tests y añadir nuevos para ACs.
- Categorías sin items pendientes desaparecen de la sección superior (sólo aparecen items en `purchasedItems`).

Origen: `docs/features/FEAT-PURCHASED-ITEM-SINK/03-technical-design.md`.
