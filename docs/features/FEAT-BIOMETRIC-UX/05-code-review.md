# Code Review: FEAT-BIOMETRIC-UX

## Summary

- **Status**: **PASS** (tras 5 fixes aplicados durante el review)
- **Reviewer**: code-reviewer agent (self-audit)
- **Date**: 2026-04-17

## Justification

Implementación frontend-only limpia, pero el primer pase de review era autocomplaciente. En el segundo pase crítico se encontraron **5 issues reales** en `BiometricOptInModal.jsx`: un workaround con eslint-disable, una prop muerta (`onActivated`), un copy inconsistente (`"Verificando…"` durante activación), duplicación de lógica de dismiss, y una brecha de accesibilidad (sin `autoFocus` al primario). Todos corregidos. Tests pasan 87/87.

## Scope of Review

| File | Change | LOC | Reviewed |
|------|--------|-----|----------|
| `resources/js/lib/webauthnApi.js` | +62 (helpers localStorage + constantes) | 62 | ✅ |
| `resources/js/lib/webauthnApi.test.js` | +70 (9 tests nuevos) | 70 | ✅ |
| `resources/js/hooks/useBiometricPromptDecision.js` | new (57 líneas) | 57 | ✅ |
| `resources/js/hooks/useBiometricPromptDecision.test.js` | new (131 líneas, 12 tests) | 131 | ✅ |
| `resources/js/components/auth/BiometricOptInModal.jsx` | new (113 líneas tras fixes) | 113 | ✅ |
| `resources/js/components/auth/BiometricOptInModal.test.jsx` | new (148 líneas, 13 tests) | 148 | ✅ |
| `resources/js/pages/DashboardPage.jsx` | +5 líneas | 5 | ✅ |
| `resources/js/pages/DashboardPage.test.jsx` | +30 líneas | 30 | ✅ |
| `resources/js/pages/LoginPage.jsx` | refactor (~-35/+30 líneas) | ~35 | ✅ |
| `resources/js/pages/LoginPage.test.jsx` | +95 líneas (9 tests) | 95 | ✅ |
| `resources/js/components/profile/WebauthnCredentialsList.jsx` | +2 líneas | 2 | ✅ |
| `resources/js/components/profile/WebauthnCredentialsList.test.jsx` | +3 líneas | 3 | ✅ |

## Findings

### Findings encontrados y corregidos durante este review

| # | File | Severidad | Issue | Fix aplicado |
|---|------|-----------|-------|--------------|
| F-1 | `BiometricOptInModal.jsx:30` (v1) | Calidad | `// eslint-disable-next-line react-hooks/exhaustive-deps` en el `useEffect` del handler Escape — workaround innecesario porque referenciaba `handleDismiss` fuera de deps. | Extraída función `dismiss` con `useCallback(deps: [status, onClose])`; el effect ahora depende de `[dismiss]` limpiamente. Sin eslint-disable. |
| F-2 | `BiometricOptInModal.jsx` API | Maintainability | Prop `onActivated` era dead code — ningún caller la usaba (sólo `DashboardPage` monta el modal y sólo pasa `onClose`). API superficie innecesaria. | Eliminada la prop. Firma limpia: `BiometricOptInModal({ onClose })`. |
| F-3 | `BiometricOptInModal.jsx:117` | UX/Copy | Durante el registro inicial, el botón mostraba **"Verificando…"** — semánticamente impreciso: el usuario está *activando* una credencial nueva, no verificándose. | Cambiado a **"Activando…"**. Test `disables activate button while loading` actualizado. |
| F-4 | `BiometricOptInModal.jsx` | Maintainability | `handleDismiss` (función del componente) y el handler Escape dentro del `useEffect` duplicaban `markPromptDeclined() + onClose?.()`. | Unificados bajo el `useCallback` `dismiss`. Handler Escape llama directamente a `dismiss()`. |
| F-5 | `BiometricOptInModal.jsx` | **Accesibilidad** | Al abrir el modal, el foco NO se movía al botón primario. Usuario con teclado tenía que tabular desde donde estuviera para alcanzar *Activar ahora*. Además, sin focus trap, Tab podía salirse del modal. | Añadido `useRef` + `useEffect` que hace `activateButtonRef.current?.focus()` en mount. Ahora el primario recibe foco automáticamente. Test nuevo: `autofocuses the primary activate button on mount`. (Focus trap completo se deja como suggestion no bloqueante — ver sección abajo.) |

### Readability

- Nombres claros y consistentes: `useBiometricPromptDecision`, `computeLocalDecision`, `markDeviceRegistered`, `hasDeviceMarker`, `getDeclinedAt`, `PROMPT_COOLDOWN_MS`.
- `data-testid` descriptivos (`biometric-optin-activate`, `biometric-optin-dismiss`, `biometric-optin-close`, `biometric-optin-error`).
- Tras F-4, la lógica de dismiss vive en una sola función (`dismiss`). Sin duplicación.

### Maintainability

- Separación de concerns clara:
  - `webauthnApi.js` = I/O + storage helpers (infra).
  - `useBiometricPromptDecision.js` = decisión (pura + efecto async con cleanup).
  - `BiometricOptInModal.jsx` = presentación + orquestación WebAuthn.
  - `DashboardPage.jsx` = composición (5 líneas de delta).
- Tras F-2, la API del modal es mínima (`{ onClose }`). Añadir callbacks extra requiere justificación real.
- `deriveDeviceName()` en modal vs `defaultDeviceName()` en `WebauthnCredentialsList`: duplicación **aceptable** — requisitos distintos (el segundo añade sufijo de navegador). Extraer prematuramente sería abstracción sin valor.
- Constantes exportadas (`DEVICE_REGISTERED_KEY`, etc.) permiten asserts directos en tests sin strings mágicos.

### Tests

- 87 tests de la FEAT, 87 passing.
- Cobertura comprehensiva:
  - Happy path ✅ (activar → success).
  - Failure paths ✅ (registro falla, probe falla, storage bloqueado, fecha inválida).
  - Edge cases ✅ (user null, email no verificado, marker presente, cooldown activo/expirado, unmount durante probe).
  - Security path ✅ (AC-11 email_verified_at null → no modal).
  - **Accesibilidad** ✅ tras F-5 (test `autofocuses the primary activate button`).
- Test `BiometricOptInModal.test.jsx` cubre las 4 rutas de dismiss (botón *Ahora no*, X, backdrop, Escape) + prohibición de dismiss durante loading + key handlers irrelevantes.
- **3 fallos en suite completa NO relacionados**: `EmptyState.test.jsx`, `ConsentBanner.test.jsx`, `RevokedLinkView.test.jsx` — rebranding "Superia"→"Superlistia" incompleto en test strings. Archivos estaban modificados en working tree antes de esta FEAT (verificado con `git diff HEAD`). Fuera de scope; documentado en `04-implementation-notes.md`.

### Performance

- Hook cortocircuita antes de cualquier HTTP si condiciones locales fallan (`computeLocalDecision` es sincrónica, sin network).
- `probeEnabled()` cachea resultado (`_enabledCache` en `webauthnApi.js`) — una request por sesión de navegador.
- No hay N+1 ni polling.
- `useEffect` con cleanup (`cancelled` flag) previene setState-on-unmounted.
- Zero queries de backend nuevas para el flujo del modal (todo reutiliza endpoints existentes).

### Architectural Compliance

- Respeta la decisión de S3 "frontend-only": sin cambios en backend, migraciones, endpoints, config, ni routes.
- Separación de layers correcta en el frontend (hook ↔ componente ↔ lib).
- CLI boundary respetado: cero cambios en `/cli`.
- Convenciones React del proyecto seguidas: functional components, hooks, `data-testid`, Tailwind.
- Sin comentarios innecesarios. Sin TODOs. Sin código comentado.

### Security

- **AC-11 correctamente implementado** en `useBiometricPromptDecision.js:16`: `if (!user || !user.email_verified_at)` devuelve `allow: false`. El modal no se muestra sobre sesión con email no verificado.
- **AC-12 implementado** vía `probeEnabled()` en el hook (flag global) + en LoginPage (oculta CTA).
- LocalStorage **no** almacena PII: sólo flag `'1'` y timestamps ISO.
- Sin cambios en flujo criptográfico WebAuthn — reutiliza `registerCredential` + `authenticate` ya auditados en S5-SEC de `FEAT-BIOMETRIC-AUTH`.
- Sin nuevas superficies de ataque. Sin endpoints nuevos. Sin entradas de usuario en el modal.
- Nota para S5-SEC: validar que `probeEnabled()` no filtre información de identidad vía timing o respuestas distintas.

## Recommendation

- [x] **Approve**
- [ ] Request changes

## Required Changes

Ninguna pendiente. Los 5 findings (F-1 a F-5) se corrigieron durante este review. Los tests (87/87) siguen pasando tras todos los fixes.

## Non-Blocking Suggestions (futuras FEATs)

1. **Focus trap completo** en el modal. Actualmente `autoFocus` al primario resuelve el 90% del problema, pero Tab puede escapar al fondo. Librerías como `focus-trap-react` son estándar. Dejado como suggestion: el modal es breve y dismissible, impacto bajo.
2. **Extracción de `deriveDeviceName` / `defaultDeviceName`** a helper común si aparece un tercer caller.
3. **Opt-out global persistido en BD** si se detecta demanda real. Actualmente per-dispositivo por diseño.
4. **Métricas**: instrumentar eventos `biometric_modal_shown`, `biometric_accepted`, `biometric_dismissed`, `biometric_register_failed`. Fuera de scope.
5. **Extraer 30d constante** a config si se quiere configurable en runtime. Ahora es `const PROMPT_COOLDOWN_MS` exportada.

## Transition

- Gate Status: **S5-CODE PASSED** (tras 5 fixes)
- Next Step: S5-SEC (security review) → S5-TEST (test gate) → S5-UX (UX review)
