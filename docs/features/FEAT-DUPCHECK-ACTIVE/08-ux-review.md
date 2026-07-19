# UX Review: FEAT-DUPCHECK-ACTIVE

## Scope

UI/UX validation via Chrome DevTools MCP browser at `http://superia.com.local/`.

Componente bajo prueba: `AddItemModal` (única superficie usuario para añadir items en `ListDetailPage`). El componente `AddItemInput` no se ejerce en lista compartida (sin `existingItems`); cubierto por unit tests.

## Pre-conditions encontradas

**Hallazgo bloqueante #1 (resuelto durante S5-UX)**: `public/build/assets/app-*.js` databa de 2026-05-05 — Vite no había compilado los cambios S4 antes de empezar el browser test. Resultado inicial: el warning aparecía contra el comprado (AC-1 falla). Tras `npm run build` (compilación 10.13s, sin errores) y reload con `ignoreCache=true`, comportamiento correcto.

**Recomendación operacional**: el workflow S4 debe incluir `npm run build` antes de S5-UX, o el desarrollador debe ejecutar `npm run dev` (HMR) durante validación browser. Documentado para `FEAT-TEST-INFRA-FIX` o como step explícito en S4 frontend phase.

## Test environment

| Ítem | Valor |
|------|-------|
| URL | `http://superia.com.local/app/listas/40638` |
| Usuario | `dupcheck-uxtest@superlistia.local` (id 53109) |
| Lista | `Dupcheck Test` (id 40638) |
| Browser | Chrome via MCP |
| Backend asset version | tras `npm run build` 2026-06-03 |
| Console errors/warnings | Ninguno durante la sesión |

## ACs validados en browser

### AC-1: comprado homónimo + add mismo nombre → no warning, comprado eliminado

**Setup**: lista con "Pan" comprado en sección "Ya en el carro (1)".

**Acción**: abrir modal Add, escribir "Pan", click "Añadir".

**Resultado observado**:
- `DuplicateWarning` **no aparece**.
- Modal procede directo a POST.
- Sección "Ya en el carro" desaparece (comprado eliminado).
- Contador cambia "1 de 1 items comprados → 0 de 1".
- Sección "OTROS" muestra "Pan" como pendiente con `1.00 ud`.

**Verdict**: ✅ PASS

### AC-2: comprado "Pan" + add variante plural "Panes" → no warning, comprado eliminado

**Setup**: marcar el activo "Pan" como comprado (vuelve a sección "Ya en el carro"). Cerrar modal "¿Cuánto pagaste?" con "Ahora no".

**Acción**: abrir modal Add, escribir "Panes" (plural), click "Añadir".

**Resultado observado**:
- `DuplicateWarning` **no aparece** (normalize("Pan") === normalize("Panes")).
- POST procede.
- Sección "Ya en el carro" desaparece.
- "Panes" creado como pendiente activo.

**Verdict**: ✅ PASS — normalización singular/plural funciona end-to-end frontend.

### AC-3: pendiente homónimo + add → warning sí aparece

**Setup**: lista con "Panes" pendiente (sección "OTROS").

**Acción**: abrir modal Add, escribir "Pan" (singular), click "Añadir".

**Resultado observado**:
- `DuplicateWarning` **aparece** con texto exacto: "Ya tienes Panes en la lista. ¿Aumentar cantidad?".
- El nombre matched mostrado es "Panes" (la forma del pendiente existente, no "Pan" del input).
- Botones "Añadir igualmente" y "Aumentar cantidad" presentes y enabled.
- POST NO se dispara.

**Verdict**: ✅ PASS — match plural detectado contra pendiente.

### ACs adicionales (no ejercidos en browser)

| AC | Cobertura | Estado |
|----|-----------|--------|
| AC-4 | Múltiples comprados homónimos eliminados | Unit test `test_create_deletes_all_purchased_homonyms_when_multiple_match` (backend) |
| AC-5 | Reglas singular/plural exhaustivas | Unit tests parametrizados PHP+JS (52+54 casos) |
| AC-6 | Unit mismatch no dispara delete | Unit test backend |
| AC-7 | Fuzzy fallback typos | Unit tests AddItemInput + AddItemModal (Vitest) |
| AC-8 | pollo vs polla no se confunden | Unit test backend + Vitest |
| AC-9 | Transacción atómica rollback | Estructural en `DB::transaction` |
| AC-10 | Mixto pendiente + comprado homónimo | Unit test backend + Vitest |
| AC-11 | Consistencia colaboradores | Out of unit scope, validar en integración futura |

## A11y observations

- Componente `DuplicateWarning` mantiene `role="alert"` (sin cambios).
- Focus management correcto: tras cancelar el warning, foco permanece en input "¿Qué necesitas?" del modal.
- Botones de warning tienen `data-testid` y texto descriptivo claro.
- Sin regresiones de navegación teclado observadas.

## Visual

- Sin cambios visuales en el componente `DuplicateWarning`.
- Mismo estilo amber (#FFF7ED) sobre fondo blanco, mismo layout, mismo iconografía Material Symbols `warning`.
- Screenshot final: `docs/features/FEAT-DUPCHECK-ACTIVE/evidence-final-state.png` (estado tras AC-3 cancelación: "Panes" pendiente, lista en OTROS, sin warning visible).

## Performance

Sin observaciones de lentitud en el flujo. Submits del Add completan en <1s en local. Sin lag perceptible al normalizar nombres en `findDuplicate` con N=1.

## Issues / Findings

### Blocking
Ninguno.

### Non-blocking
1. **Asset build automation** (operacional): el desarrollador debe compilar Vite antes de S5-UX. Recomendación: hook git-pre-commit o instrucción S4 explícita. → Tech debt para `FEAT-TEST-INFRA-FIX`.
2. **AC-7 fuzzy fallback no validado manualmente en browser**: cubierto por unit tests; aceptable. Si se desea reforzar: añadir test E2E (Playwright) en feature de QA infra.

## Verdict

✅ **PASS** — los ACs core (AC-1, AC-2, AC-3) validados end-to-end en browser real. Sin errores en console. Sin regresiones visuales. La normalización singular/plural funciona consistente entre PHP backend y JS frontend.

## Transition

- Gate: S5-UX
- Status: PENDING (awaiting user approval)
- Next Step: S6 — Completion
