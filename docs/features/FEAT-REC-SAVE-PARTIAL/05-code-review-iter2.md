## Code Review: FEAT-REC-SAVE-PARTIAL (Iteration 2 — S5-UX delta)

### Summary
- **Status**: PASS
- **Reviewer**: code-reviewer (S5-CODE, iteration 2)
- **Date**: 2026-05-05
- **Scope**: delta only — `SaveTargetSheet.jsx`, `SaveTargetSheet.test.jsx`, `WeeklySummaryPage.jsx` (item-card `<label>` change). Backend untouched in this iteration.

### Justification
Los seis fixes de S5-UX (focus trap, focus ring, contraste freemium, copy CTA dinámica, modal centrado en desktop, card clickable) están aplicados de forma localizada y correcta. El focus trap respeta la semántica WAI-ARIA dialog (Tab/Shift+Tab con wrap), el hook `useIsDesktop` es SSR-safe con polyfill de `addListener`, y la mutación inline de `boxShadow` no introduce fugas. Los 5 tests nuevos cubren las rutas críticas (focus trap forward/back, copy CTA en ambos paths, layout desktop). 388/388 frontend + 825/825 backend en verde, sin regresiones.

### Findings

#### Readability
- `SaveTargetSheet.jsx:5-6` — `FOCUSABLE_SELECTOR` extraído como constante de módulo con la lista canónica WAI-ARIA. Buena documentación implícita.
- `SaveTargetSheet.jsx:145-151` — `confirmLabel` con IIFE encadenando `if`/`return` es legible y cubre los 4 estados (submitting, disabled, createNew, existing). Preferible a un ternario anidado.
- `SaveTargetSheet.jsx:153` — constante `focusRing` reutilizada por los 4 botones. Evita duplicación del valor `box-shadow`.
- `SaveTargetSheet.jsx:154-176` — `sheetStyle` ramificado por `isDesktop` es legible aunque verboso. Aceptable: extraer a un helper externo añadiría indirección sin valor.
- `WeeklySummaryPage.jsx:349-426` — el cambio `<div>` → `<label>` con `aria-labelledby={labelId}` mantiene la accesibilidad para screen-readers y añade el toggle nativo de label/input. Buen tradeoff.

#### Maintainability
- `SaveTargetSheet.jsx:54-109` — el `useEffect` ahora carga 4 responsabilidades (initial focus, ESC, focus trap, body scroll lock, restore focus on unmount). Sigue siendo manejable porque todas comparten el ciclo de vida `isOpen`. **No bloqueante**, pero si en futuras iteraciones se añade gestión de aria-live o portal, conviene partir en hooks separados (`useFocusTrap`, `useBodyScrollLock`, `useEscapeClose`).
- `SaveTargetSheet.jsx:78-80` — el filtro `el.hasAttribute('disabled') && el.tabIndex !== -1` es **redundante** con `FOCUSABLE_SELECTOR` (que ya excluye `:disabled` y `[tabindex="-1"]` para input/select/textarea/button con sintaxis CSS). Redundancia defensiva, no es bug. Aceptable.
- `SaveTargetSheet.jsx:245-250, 329-336, 401-408, 429-434` — patrón `onFocus`/`onBlur` mutando `e.currentTarget.style.boxShadow` repetido en 4 botones. Es una micro-duplicación; refactor a `useFocusRing()` hook o componente envoltorio sería deseable si crece a 6+ botones. Para 4 instancias y un solo valor, aceptable.
- `useIsDesktop` (líneas 8-30) está acoplado al componente; si se necesita en otros sheets futuros, mover a `resources/js/hooks/useIsDesktop.js`. **Tech debt menor anotada**, no bloqueante.
- Convenciones del repo respetadas: inline styles, data-testid, hooks con `use` prefix, sin nuevas dependencias.

#### Tests
- `SaveTargetSheet.test.jsx:78-88` — los dos tests de copy dinámica (`shows the chosen list name`, `new-list label`) son específicos: aserciones por texto exacto via `toHaveTextContent`. No superficiales.
- `SaveTargetSheet.test.jsx:90-111` — los focus-trap tests son **genuinos**: focusan manualmente el último/primero, ejecutan `userEvent.tab(...)` y verifican `document.activeElement`. La selección del "último focusable" en el Shift+Tab test depende de que `confirm` esté disabled (no hay lista seleccionada al inicio del test) — la suposición está correctamente codificada y es estable. Cubre el caso de wrap-around real.
- `SaveTargetSheet.test.jsx:113-134` — el desktop-modal test mockea `window.matchMedia` correctamente, verifica que el drag handle no aparece y que `borderRadius` es `'24px'` (uniforme). Aserciones específicas que detectarían regresión si alguien quita el branch `isDesktop`.
- **Gap menor (no bloqueante)**: no hay test que verifique que al cerrar el sheet el focus regresa al opener (`previouslyFocusedRef`). El comportamiento es estándar y la lógica es trivial (`previouslyFocusedRef.current?.focus()`), pero no está cubierto por test. Anotado.
- **Gap menor (no bloqueante)**: no hay test del comportamiento de `<label>` wrapping en `WeeklySummaryPage` (ej. clic en el badge o en `reason` toggle el checkbox). El test existente toca el checkbox directamente. La asociación `<label>` con `<input>` anidado es comportamiento nativo del navegador, no de React, así que JSDOM debería propagarlo, pero no está verificado.
- 388/388 frontend + 825/825 backend verificados en la iteración. Sin regresiones detectadas.

#### Performance
- `SaveTargetSheet.jsx:65-109` — el handler `keydown` se rebinde cada vez que `onClose` cambia de referencia (deps `[isOpen, onClose]`). Si el padre re-renderiza con `onClose` inline, el listener se desbinda y rebinda cada render. **No bloqueante** (operación O(1)), pero envolver `onClose` con `useCallback` en el padre o usar un ref evitaría la rebindeada. Patrón ya presente en el código original; no es regresión.
- `SaveTargetSheet.jsx:78` — `querySelectorAll(FOCUSABLE_SELECTOR)` se ejecuta en cada Tab keypress. Para un dialog con ≤6 focusables el costo es despreciable (<0.1ms). Aceptable.
- `useIsDesktop` — un único listener `change` por instancia del componente, cleanup correcto. Sin leaks en remount/unmount.
- `<label>` wrapper en `WeeklySummaryPage.jsx`: ningún cambio de complejidad render. El click en el badge ahora dispara click nativo de label que delega al `<input>` — una llamada DOM extra negligible.

#### Architecture
- **Focus trap correcto** según WAI-ARIA Authoring Practices Dialog Pattern: Tab desde el último elemento → primero, Shift+Tab desde el primero → último, ambos con `event.preventDefault()`. Verificado por inspección de líneas 86-97. ✓
- **Listener cleanup correcto**: el `removeEventListener` se ejecuta en el cleanup function del useEffect (líneas 102-108) y restaura `document.body.style.overflow` y el focus previo. No hay escape de listeners. ✓
- **Coexistencia ESC/Tab en mismo handler**: el handler hace early-return tras ESC (`return;` en línea 69), así que no hay interferencia con la lógica Tab. Correcto. ✓
- **`useIsDesktop` SSR-safe**: el initial state usa `typeof window !== 'undefined' && typeof window.matchMedia === 'function'` antes de invocar matchMedia. El effect tiene la misma guarda. Polyfill path para `addListener`/`removeListener` (Safari <14) presente. Correcto. ✓
- **Inline `onFocus`/`onBlur` mutating `e.currentTarget.style.boxShadow`**: idiomático en React funciona bien aquí porque (a) `e.currentTarget` siempre referencia el elemento actual, no captura stale; (b) React sincroniza estilos vía la prop `style`, pero `style.boxShadow` no está en la prop, así que la mutación imperativa persiste sin que React la pise; (c) no requiere state ni re-render. **Risk anotada**: si la prop `style.boxShadow` se añade en el futuro, React podría sobreescribir la mutación imperativa al re-renderizar. Solución preventiva: migrar a `useState` o a clases CSS con `:focus-visible`. **No bloqueante** dado que el comportamiento actual es correcto.
- **Risk: focus ring queda visible cuando el botón pasa de enabled→disabled mientras tiene foco** (ej. usuario foca confirm enabled, deselecciona todos los items → confirm disabled, no se dispara onBlur, el `box-shadow` persiste). Cosmético, no funcional. **No bloqueante**, anotado para futura migración a `:focus-visible` CSS.
- **`<label>` wrapper en item card**: clicar en checkbox (anidado) dispara onChange una sola vez (comportamiento nativo HTML — el browser dedupe el evento label→input cuando el input está dentro). No hay double-toggle. ✓
- **`<label>` wrapper risk**: si alguien añade un `<button>` u otro control interactivo dentro del `<label>` futuro, clicar ese control también disparará el toggle del checkbox (label propaga clicks a su control asociado). Hoy solo hay texto y un badge no interactivo, así que está OK. **Anotado** para que el próximo cambio en el card item lo tenga presente.
- **Tests no exhiben regresiones** en los 30 tests previos (388 = 383 + 5 nuevos). Verificado en notas de iteración.
- Sin violaciones arquitectónicas: el componente sigue siendo un dumb component que recibe `onConfirm`/`onClose` por props, sin acoplamiento a fetch ni lógica de negocio. ✓

### Recommendation
- [x] Approve
- [ ] Request changes

### Required Changes
Ninguna requerida. Tech debt menor anotada, **no bloqueante**:

1. **Tech debt** — `SaveTargetSheet.jsx:78-80`: filtro redundante (`hasAttribute('disabled') && tabIndex !== -1`) ya cubierto por `FOCUSABLE_SELECTOR`. Limpieza opcional.
2. **Tech debt** — `SaveTargetSheet.jsx:245-250, 329-336, 401-408, 429-434`: patrón inline `onFocus`/`onBlur` para focus ring repetido 4 veces. Migrar a clase CSS con `:focus-visible` (más robusto contra el caso enabled→disabled descrito) o a un componente `<FocusableButton>` si crece. No bloqueante con 4 instancias.
3. **Tech debt** — `useIsDesktop` (líneas 8-30) está embebido en `SaveTargetSheet.jsx`. Si se necesita en otros sheets/modals, mover a `resources/js/hooks/useIsDesktop.js`. No bloqueante hasta segunda ocurrencia.
4. **Test coverage gap menor** — añadir test que verifique que al cerrar el sheet el focus regresa al elemento opener (`previouslyFocusedRef`). Y opcionalmente, test que clicar en el badge/reason del item card alterna el checkbox (vía `<label>`). No bloqueante: el comportamiento es nativo del navegador.
5. **Para el S5-UX gate** — confirmar visualmente:
   - El focus ring (`box-shadow: 0 0 0 3px rgba(0,62,84,0.35)`) cumple WCAG 2.4.7 contraste con backgrounds `#f7f9fb` (rows) y `#ffffff` (sheet).
   - El subtitle freemium (`#41484c` sin opacidad) cumple WCAG 1.4.3 (≥4.5:1) sobre `#f7f9fb`.
   - El layout desktop centrado se ve correcto en `>=768px`.
6. **Para el S5-TEST gate** — revisar que la cobertura del focus-trap branch (filter cuando `focusable.length === 0`, líneas 81-85) está cubierta. El test actual asume ≥1 focusable; el branch defensivo no se ejerce.
