# Architecture — Bounded Contexts (DDD)

Lenguaje ubicuo del dominio. Fuente de verdad: `docs/contexts/`.

| Contexto | Descripción | Términos clave | Features asociados | Glosario |
|----------|-------------|----------------|--------------------|----------|
| default (list-items) | Gestión de items en listas de compra: ciclo pendiente↔comprado, sink visual, conversión de recomendaciones del resumen semanal | item pendiente, item comprado, sink, feedback inmediato, toggle, resumen semanal, conversión parcial, lista destino | FEAT-EPIC3-ITEMS, FEAT-EPIC5C-SUMMARY, FEAT-PURCHASED-ITEM-SINK, FEAT-PURCHASE-ANIMATION, FEAT-REC-SAVE-PARTIAL | [docs/contexts/default/00-glossary.md](../../docs/contexts/default/00-glossary.md) |

## Glosario: default

> Última actualización origen: 2026-05-04

### Items y ciclo de compra

- **Item** — Producto individual dentro de una lista, con nombre, cantidad y unidad. Código: `ListItem`.
- **Item pendiente** — Producto aún no adquirido. Código: `item.is_purchased = false`.
- **Item comprado** — Producto marcado como adquirido durante una sesión de compra. Código: `item.is_purchased = true`.
- **Marcar como comprado** — Acción del usuario al pulsar checkbox de un item pendiente. Código: `onToggle(item.id)`.
- **Desmarcar** — Acción inversa: revertir un item comprado a pendiente. Código: `onToggle(item.id)` cuando `is_purchased = true`.
- **Toggle** — Cambio de estado entre pendiente y comprado. Endpoint: `PATCH /api/lists/{list}/items/{item}/toggle`.

### Atributos de items

- **Posición** — Orden numérico de un item dentro de su lista. Código: `item.position`.
- **Cantidad** — Número de unidades a comprar. Código: `item.quantity`.
- **Unidad** — Medida del producto: kg, g, L, ml, ud, pack. Código: `item.unit`.

### Experiencia de compra

- **Sink** — Desplazamiento visual del item comprado hacia la sección inferior. Implementado como reordenado DOM tras delay de 1.5s.
- **Feedback inmediato** — Respuesta visual al instante del tap/click, antes de confirmación de red. Implementado con estado local `justChecked = true` en `ItemRow`.
- **Sección de comprados** — Área inferior de la vista donde se agrupan items adquiridos. UI: "Ya en el carro".

### Listas

- **Lista de compra** — Colección nombrada de items para una sesión de compra. Código: `ShoppingList`.

### Resumen semanal y recomendaciones

- **Resumen semanal** — Conjunto de productos sugeridos por IA al inicio de cada semana basado en historial. Código: `WeeklySummary`.
- **Recomendación** — Producto sugerido dentro de un resumen. Código: item dentro de `WeeklySummary.payload_json`.
- **Recomendación pendiente** — Item del resumen aún no guardado en ninguna lista. Permanece en `payload_json`.
- **Guardar selección** — Acción de elegir subconjunto de recomendaciones y enviarlas a una lista. Endpoint: `POST /weekly-summary/{id}/save`.
- **Lista destino** — Lista a la que se envían recomendaciones (existente o nueva). Código: `target_list_id` (null = nueva).
- **Conversión parcial** — Operación de guardar subconjunto manteniendo el resto pendiente. Código: `selected_indices` ⊂ `payload_json`.
- **Resumen actuado** — Resumen sin recomendaciones pendientes tras conversiones; oculto en la vista. Código: `WeeklySummaryStatus::Actioned` (nombre exacto por verificar).
