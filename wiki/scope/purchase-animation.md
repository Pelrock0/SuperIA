# Scope — FEAT-PURCHASE-ANIMATION

Feedback visual al marcar item como comprado: green flash inmediato + delay 1.5s antes del "sink" hacia la sección de comprados, evitando que la transición sea imperceptible.

## Clasificación

| Atributo | Valor |
|----------|-------|
| Complexity | LOW |
| Effort | ~2 h (real: ~3h con iter2) |
| Status | S5 PASSED 2026-04-25 (CODE+SEC+TEST+UX) |

## Historias / ACs

| AC | Descripción |
|----|-------------|
| AC-1 | Feedback inmediato (<50ms): `bg-green-100` + line-through al marcar |
| AC-2 | Delay 1.5s antes del sink; 300ms exit animation (fade+height collapse) |
| AC-3 | PATCH API dispara al instante, NO espera el delay |
| AC-4 | Feedback inverso al desmarcar (`bg-gray-50`, sube tras 1.5s) |
| AC-5 | Cleanup en unmount: `clearTimeout` + `isMountedRef` aislado |
| AC-6 | Comportamiento idéntico en `SharedListPage` |
| AC-7 | `npm test` pasa (360 tests) |

## Dependencias clave

- 100% frontend, cero cambios backend
- Llamada API toggle (`PATCH /lists/{list}/items/{item}/toggle`) sin modificación
- Estado local: `justCheckedItems`, `exitingItems`, `pendingTimersRef`, `isMountedRef`

## Decisiones de producto

- Duración delay 1.5s no configurable en V1
- Color: Tailwind `bg-green-100` (verde suave, consistente con indigo-600 del checkmark)
- Checkbox `disabled` durante animation phases para evitar double-toggle
- Out of scope: sonido, haptic, undo, config por usuario

## Desviaciones scope → implementación

| Desviación | Impacto |
|------------|---------|
| `ItemRow.jsx` propuesto en TD **no se creó** — `ListDetailPage` y `SharedListPage` tienen markup divergente | **Deuda técnica**: lógica duplicada entre ambas pages. Extraer a hook compartido `usePurchaseAnimation` cuando converja markup. |
| `1.5s green + 300ms exit = 1.8s total` antes del re-fetch (TD decía solo 1.5s) | AC-2 satisfecho con exit animation más suave |

## Hallazgos S5

- **Code review iter1 → blocking**: duplicación + design pivot no documentado → fixed en iter2 con notas explícitas en `04-implementation-notes.md`
- **Security**: PASS WITH NOTES — `postcss@8.5.9` build-time only (no runtime)
- **UX**: PASS — 9/9 checks @browser (mobile 375px + desktop)

Origen: `docs/features/FEAT-PURCHASE-ANIMATION/01-scope.md` → `05-review-results.md`.
