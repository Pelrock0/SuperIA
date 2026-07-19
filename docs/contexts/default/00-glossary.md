# Ubiquitous Language — Default Context

> Context: list-items (shopping list item management)
> Last updated: 2026-05-04

| Term | Definition (domain expert language) | Example in code |
|------|--------------------------------------|-----------------|
| Item pendiente | Producto de la lista aún no adquirido por el usuario | `item.is_purchased = false` |
| Item comprado | Producto marcado como adquirido durante una sesión de compra | `item.is_purchased = true` |
| Marcar como comprado | Acción del usuario al pulsar el checkbox de un item pendiente | `onToggle(item.id)` |
| Desmarcar | Acción inversa: revertir un item comprado a pendiente | `onToggle(item.id)` (cuando `item.is_purchased = true`) |
| Sink | Desplazamiento visual de un item comprado hacia la sección inferior de la lista | Reordenado DOM tras delay de 1.5s |
| Feedback inmediato | Respuesta visual al instante del tap/click, antes de cualquier confirmación de red | Estado local `justChecked = true` en `ItemRow` |
| Toggle | Cambio de estado de un item entre pendiente y comprado | `PATCH /api/lists/{list}/items/{item}/toggle` |
| Lista de compra | Colección nombrada de items que un usuario gestiona para una sesión de compra | `ShoppingList` |
| Item | Producto individual dentro de una lista de compra, con nombre, cantidad y unidad | `ListItem` |
| Sección de comprados | Área inferior de la vista de lista donde se agrupan los items ya adquiridos | "Ya en el carro" |
| Posición | Orden numérico de un item dentro de su lista | `item.position` |
| Cantidad | Número de unidades del producto a comprar | `item.quantity` |
| Unidad | Medida del producto (kg, g, L, ml, ud, pack) | `item.unit` |
| Resumen semanal | Conjunto de productos sugeridos por la IA al inicio de cada semana basado en historial de compra | `WeeklySummary` |
| Recomendación | Producto individual sugerido dentro de un resumen semanal | Item dentro de `WeeklySummary.payload_json` |
| Guardar selección | Acción del usuario al elegir un subconjunto de recomendaciones y enviarlas a una lista de compra | `POST /weekly-summary/{id}/save` |
| Lista destino | Lista de compra a la que el usuario decide enviar las recomendaciones seleccionadas (existente o nueva) | `target_list_id` (null = nueva) |
| Conversión parcial | Operación de guardar un subconjunto de recomendaciones manteniendo el resto pendientes en el resumen | `selected_indices` ⊂ `payload_json` |
| Resumen actuado | Resumen sin recomendaciones pendientes tras una o varias conversiones; queda oculto en la vista | `WeeklySummaryStatus::Actioned` (TBD nombre exacto) |
| Recomendación pendiente | Item del resumen aún no guardado en ninguna lista | Permanece en `payload_json` |
| Duplicado | Item cuyo nombre normalizado (lowercase, sin tildes, reducido a singular) y unidad coinciden con otro item de la misma lista | `findDuplicate(name)` |
| Forma normalizada | Versión canónica del nombre de un producto: minúsculas, sin tildes, sin sufijo de plural en español | "Tomates" → "tomate", "Cebollas" → "cebolla" |
| Variante plural | Nombre que difiere de su forma singular solo por el sufijo `-s` o `-es` en español | "panes" es variante plural de "pan" |
