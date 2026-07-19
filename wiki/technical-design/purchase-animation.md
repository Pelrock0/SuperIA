# Technical Design — FEAT-PURCHASE-ANIMATION

Animation logic 100% frontend, con state machine local en cada página (`ListDetailPage`, `SharedListPage`). La llamada API fire-immediate; el delay aplica solo al sink visual.

## Arquitectura final (post-implementation)

| Componente | Responsabilidad | Patrón |
|------------|-----------------|--------|
| `ListDetailPage.jsx` | Sets `justCheckedItems` + `exitingItems`, lanza API + timer en `Promise.all` | Inline state machine |
| `SharedListPage.jsx` | Idéntico al anterior (duplicado) | Inline state machine |
| Sin nuevo componente compartido | (TD proponía `ItemRow.jsx` — descartado por divergencia) | — |

## State machine por item

```
IDLE
  ├─ user clicks checkbox
  ├─ onToggle(item.id)              → API call inmediato (AC-3)
  ├─ justCheckedItems.add(item.id)  → bg-green-100 + line-through
  └─ setTimeout(1500) ─┐
                       ▼
              ANIMATING (justChecked = true)
                ├─ checkbox disabled (no double-toggle)
                ├─ after 1500ms:
                ├─ exitingItems.add(item.id)
                └─ setTimeout(300) ─┐
                                    ▼
                          EXITING (300ms fade + height collapse)
                            └─ after 300ms:
                              ├─ justCheckedItems.delete
                              ├─ exitingItems.delete
                              └─ refetch list → item moves to "Ya en el carro"
                              ▼
                            IDLE
```

## Decisiones de diseño

- **Inline duplication aceptada** — `ItemRow.jsx` shared component (propuesto en TD) descartado porque `ListDetailPage` y `SharedListPage` divergen en markup, styles y edit-mode logic. Documentado en `04-implementation-notes.md` como deuda explícita.
- **`Promise.all([api.patch, timerPromise(1500)])`** — API y timer arrancan en paralelo. AC-3 satisfecho sin acoplar el delay al network.
- **`isMountedRef` en `useEffect([], [])` aislado** — separado del fetch effect para evitar que el cleanup se dispare en navegación entre listas (el id cambia) y deshabilite todos los toggles.
- **`disabled={isAnimating || isExiting}`** — cubre ambas fases (green 1.5s + exit 300ms) para evitar double-toggle.
- **`exitingItems` separado de `justCheckedItems`** — durante exit el item colapsa en su posición original antes de que el re-fetch reordene.

## Tailwind classes

```
isAnimating
  ? (item.is_purchased ? 'bg-gray-50' : 'bg-green-100')
  : (item.is_purchased ? 'opacity-60 hover:bg-gray-50' : 'hover:bg-gray-50')

(isAnimating || item.is_purchased) ? 'line-through text-gray-400' : 'text-gray-900'

transition-all duration-300
```

## Tests

- Vitest + RTL + `vi.useFakeTimers()` + `act()`
- 5 animation tests por página (10 total) + 4 nuevos AC-4 + failure path (iter2)
- 360/360 passing tras iter2

## Gotchas

- Failure-path tests dicen "shows error" pero no asertan mensaje en DOM (solo checkbox state). Non-blocking, mencionado en CODE review iter2.
- `postcss@8.5.9` transitivo via Vite tiene GHSA-qx2v-qp2m-jg93 — build-time only, no runtime.

## Hallazgos críticos S5

- **iter1 blocking**: animation state machine duplicada sin doc, `isMountedRef` en effect equivocado, missing `disabled` guard en SharedListPage. **Resueltos iter2** — todos verificados.

Origen: `docs/features/FEAT-PURCHASE-ANIMATION/03-technical-design.md`, `04-implementation-notes.md`, `05-review-results.md`.
