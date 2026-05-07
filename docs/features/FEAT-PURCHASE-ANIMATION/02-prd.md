# PRD: FEAT-PURCHASE-ANIMATION - Animación al Marcar Item Comprado

## Bounded Context
- **Context**: list-items
- **Glossary**: no aplica (proyecto no usa estructura `docs/contexts/`)

### Vocabulario del dominio

| Término | Definición | Uso en código |
|---------|-----------|---------------|
| Item pendiente | Producto de la lista aún no adquirido | `item.is_purchased = false` |
| Item comprado | Producto marcado como adquirido en la sesión de compra | `item.is_purchased = true` |
| Marcar como comprado | Acción del usuario al pulsar el checkbox de un item pendiente | `onToggle(item.id)` |
| Sink | Desplazamiento del item comprado a la sección inferior de la lista | reordenado visual en el DOM |
| Feedback inmediato | Respuesta visual al instante del tap/click, antes de cualquier acción de red | estado local `justChecked` |

---

## Business Objective

El usuario no percibe que ha marcado un item como comprado porque el cambio ocurre demasiado rápido. Necesitamos confirmar visualmente la acción de forma clara y dar tiempo al ojo para procesar lo que acaba de pasar antes de que el item desaparezca de la sección principal.

## Problem Statement

Al marcar un item como comprado el item se mueve instantáneamente a la sección "Ya en el carro". En listas con muchos items o en pantallas pequeñas, el usuario no distingue si ha marcado el item correcto. Esto genera re-checks, errores y sensación de app poco responsiva.

---

## Scope

### In Scope
- Feedback visual inmediato en `ItemRow` al marcar como comprado: fondo verde suave + tachado del nombre
- Delay configurable (1.5s por defecto) antes de ejecutar el reordenado (sink)
- Animación de salida (fade o slide hacia abajo) al hundirse a la sección de comprados
- El comportamiento aplica tanto en `ListDetailPage` como en `SharedListPage` (ambas usan `ItemRow`)
- El mismo feedback aplica al desmarcar (comprado → pendiente), en dirección inversa

### Out of Scope
- Cambio de comportamiento de la llamada API (sigue siendo optimista, al instante)
- Animación en el undo de borrado (feature separada)
- Sonido o vibración háptica
- Configuración de usuario para desactivar la animación
- Cambios en el backend o en los tests de backend
- Cambios en `SharedListPage` más allá de los que hereda automáticamente de `ItemRow`

---

## Acceptance Criteria

### AC-1: Feedback visual inmediato al marcar comprado
- **Given**: el usuario está en la vista de detalle de lista con al menos un item pendiente
- **When**: el usuario pulsa el checkbox de un item pendiente
- **Then**: el item muestra inmediatamente (< 50ms) fondo verde suave y el nombre aparece tachado, sin esperar respuesta del servidor

### AC-2: Delay antes del sink
- **Given**: el usuario acaba de marcar un item comprado (AC-1 activo)
- **When**: han transcurrido 1.5 segundos
- **Then**: el item se mueve a la sección "Ya en el carro" con una animación suave (fade-out + reposicionamiento)

### AC-3: La llamada API no se retrasa
- **Given**: el usuario pulsa el checkbox
- **When**: ocurre el toggle
- **Then**: la llamada `PATCH /api/lists/{list}/items/{item}/toggle` se dispara inmediatamente (no espera el delay del sink)

### AC-4: Feedback inverso al desmarcar
- **Given**: el usuario está en la sección "Ya en el carro" con al menos un item comprado
- **When**: el usuario desmarca el checkbox
- **Then**: el item muestra feedback visual inverso (fondo gris, tachado desaparece) y tras 1.5s sube a la sección de pendientes con animación

### AC-5: Cleanup si el componente se desmonta durante el delay
- **Given**: el usuario ha marcado un item (delay en curso)
- **When**: el usuario navega fuera de la página antes de que transcurran los 1.5s
- **Then**: no se producen errores de memory leak ni llamadas a `setState` en componente desmontado

### AC-6: Funciona en SharedListPage
- **Given**: un usuario accede a una lista compartida (con token write)
- **When**: marca un item como comprado
- **Then**: el mismo feedback visual y delay se aplican (heredado automáticamente de `ItemRow`)

### AC-7: Tests frontend pasan
- **Given**: el cambio está implementado
- **When**: se ejecuta `npm test`
- **Then**: todos los tests de `ItemRow.test.jsx` pasan; se añaden tests para el nuevo comportamiento

---

## UX Decision
- **UX Designer Required**: No
- **UX Artifacts**: N/A
- **Basic UX Notes**:
  - Color del feedback: verde Tailwind (`bg-green-100` o `bg-green-50`) — consistente con el checkmark de `text-indigo-600` existente
  - Duración del delay: 1.5s (no configurable en V1)
  - Transición de salida: `transition-all duration-300` de Tailwind (fade + height collapse)
  - El checkbox queda visualmente marcado durante el delay (no hay estado intermedio de "cargando")

---

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Memory leak si componente desmontado durante timeout | Technical | `useEffect` cleanup con `clearTimeout` |
| Test de `ItemRow.test.jsx` roto por el delay (async) | Technical | Usar `jest.useFakeTimers()` + `act()` en los tests afectados |
| Doble-click del usuario durante el delay (re-toggle antes del sink) | Technical | Deshabilitar el checkbox durante el delay (`disabled` o ignorar el segundo click) |
| Animación entrecortada en dispositivos lentos | Performance | Usar `will-change: transform` y limitar a propiedades GPU-composited |

---

## Assumptions
- Tailwind CSS está disponible y configurado en el proyecto (confirmado)
- `ItemRow` es el único componente que renderiza el checkbox de toggle (confirmado — `ListDetailPage` y `SharedListPage` lo usan)
- 1.5s es suficiente para que el usuario perciba el cambio (decisión del usuario del producto)

## Open Questions
- Ninguna.

## Approval
- [ ] PRD aprobado
