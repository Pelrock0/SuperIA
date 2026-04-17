# Smoke Test Report: FEAT-BIOMETRIC-UX

## Summary

- **Status**: **PASS** (tras fix backend aplicado)
- **Method**: Chrome DevTools MCP sobre `http://superia.com.local`
- **Date**: 2026-04-17

**Observación inicial importante**: WebAuthn API requiere secure context (HTTPS o localhost). El entorno de dev usa `http://superia.com.local`, que NO es secure context → `window.PublicKeyCredential` resulta `undefined` y el CTA biométrico **no es visible en dev sin HTTPS**. Workaround usado: `initScript` de MCP para stubear `PublicKeyCredential` y validar flujo UI. En producción (HTTPS) o con `localhost` el flujo funciona nativamente sin stub.

## Bug descubierto y corregido (**sólo detectable vía smoke test**)

**BUG-1 — `email_verified_at` ausente en respuesta de `POST /auth/login`**

- **Síntoma**: tras un login exitoso con credenciales correctas, el modal post-login **nunca aparecía** en Dashboard, incluso con usuario verificado, localStorage limpio, WebAuthn soportado.
- **Root cause**: `app/Http/Controllers/Auth/LoginController.php:38-42` exponía sólo `id`, `name`, `email` en la respuesta. `AuthContext.login()` setteaba ese user en el estado sin `email_verified_at`. El hook `useBiometricPromptDecision` comprueba `user.email_verified_at` → lo veía `undefined` → devolvía `{allow:false, reason:'email-not-verified'}` → modal no se mostraba nunca tras login (sí tras refresh, porque `fetchUser` usa `/profile` que sí devuelve `email_verified_at`).
- **Por qué no lo cazaron los unit tests**: todos los tests frontend mockean el `user` con `email_verified_at: '...'` seteado a mano, nunca ejecutan el login real end-to-end.
- **Fix aplicado**:
  - Backend: `LoginController.php` ahora incluye `email_verified_at` en el payload.
  - Test nuevo: `LoginTest::test_login_response_includes_email_verified_at_for_verified_users`.
  - Regression guard: `test_login_returns_token_with_valid_credentials` ahora asserta la estructura completa incluyendo el campo.
- **Impacto**: mínimo. Un campo extra en la respuesta. Sin cambios en endpoint, sin breaking change para otros consumidores (ignoran el campo si no lo esperan).

## Environment Finding (no bloqueante, ya conocido)

**ENV-1 — Secure context en dev local**

- `http://superia.com.local` no es HTTPS ni localhost → el navegador desactiva `window.PublicKeyCredential` completamente.
- **No es bug de la FEAT**: es comportamiento estándar del navegador. Producción (`https://superlistia.com`) no tiene este problema.
- **Opciones para testing local end-to-end real** (futuro):
  1. Configurar mkcert + HTTPS local en Laravel Valet/XAMPP.
  2. Añadir `http://superia.com.local` a `chrome://flags/#unsafely-treat-insecure-origin-as-secure`.
  3. Configurar vhost en `http://localhost` (excepción del estándar WebAuthn).
- Para este smoke test se usó el workaround `initScript` de Chrome DevTools MCP para stubear `PublicKeyCredential`. Permite validar el flujo UI del hook y del modal; no valida el flujo criptográfico real (el SO exige secure context).

## Smoke Test Steps Executed

1. **Setup**: creado usuario test `smoketest@superia.local` con `email_verified_at` via `php artisan tinker`.
2. **Navegación inicial a `/login`**: screenshot `01-login-initial.png` — sin stub, CTA biométrico no aparece (ENV-1 confirmado).
3. **Navegación con stub**: screenshot `02-login-with-webauthn-stubbed.png` — CTA primario "Entrar con biometría" aparece con gradiente corporativo correcto arriba del form. Separador "O CON EMAIL" visible. AC-10 ✅.
4. **Click CTA sin auth válida**: screenshot `03-biometric-cta-error.png` — error inline "No se pudo autenticar con biometría." con estilo correcto. Botones siguen activos.
5. **Login con credenciales**: primer intento (antes del fix BUG-1) mostró Dashboard sin modal → detectado bug → fix aplicado en backend.
6. **Re-login tras fix BUG-1**: screenshot `05-dashboard-modal-visible.png` — modal aparece correctamente. AC-1 ✅.
7. **Modal visual match vs wireframe**: icono, título, body, botones, X close, footer — todo idéntico. Gradiente corporativo `#002736→#003e54` en *Activar ahora*. Fondo gris claro en *Ahora no*.
8. **AutoFocus**: snapshot a11y confirma `button "Activar ahora" focusable focused` al mount. F-5 del code review ✅.
9. **Click "Ahora no"**: modal se cierra. `localStorage.biometric_prompt_declined_at` escrito con timestamp ISO. AC-5 ✅.
10. **Marker presente + navegación**: localStorage con `webauthn_device_registered='1'` → modal NO aparece. AC-8 ✅.
11. **Declined_at > 30 días**: localStorage con `biometric_prompt_declined_at` de hace 31 días → modal reaparece. AC-6 ✅.

## Acceptance Criteria verificados end-to-end

| AC | Método | Status |
|----|--------|--------|
| AC-1 | Smoke test + unit | ✅ |
| AC-2 | Unit (lógica idéntica a AC-1) | ✅ |
| AC-3 | Smoke test (ENV-1 emula sin soporte) | ✅ |
| AC-4 | Unit (activate→success) — real WebAuthn no testable sin HW | ✅ |
| AC-5 | Smoke test (dismiss + localStorage assertion) | ✅ |
| AC-6 | Smoke test (cooldown expirado) | ✅ |
| AC-7 | Unit (equivalente a marker ausente + verified user) | ✅ |
| AC-8 | Smoke test (marker presente suprime modal) | ✅ |
| AC-9 | Smoke test (error inline en CTA) + unit | ✅ |
| AC-10 | Smoke test visual | ✅ |
| AC-11 | Unit + backend (login bloquea usuarios no verificados con 401) | ✅ |
| AC-12 | Smoke test (probeEnabled=false sin stub → sin CTA) | ✅ |

## Files modificados tras S5-UX approval

Smoke test requirió un hotfix backend. Cambios desde S6 entrada:

| File | Change |
|------|--------|
| `app/Http/Controllers/Auth/LoginController.php:42` | +1 línea: `'email_verified_at' => $result['user']->email_verified_at,` |
| `tests/Feature/Auth/LoginTest.php` | Test extendido + test nuevo `test_login_response_includes_email_verified_at_for_verified_users` |

## Test suite final

| Suite | Passing | Total | Rate |
|-------|---------|-------|------|
| Frontend (vitest) | 347 | 347 | 100% |
| Backend (PHPUnit) | 665 | 665 | 100% |
| **Total** | **1012** | **1012** | **100%** |

## Screenshots

1. `screenshots/01-login-initial.png` — LoginPage sin stub WebAuthn (ENV-1)
2. `screenshots/02-login-with-webauthn-stubbed.png` — LoginPage con CTA biométrico primario
3. `screenshots/03-biometric-cta-error.png` — Error inline tras click CTA
4. `screenshots/04-dashboard-with-modal.png` — Dashboard SIN modal (pre-fix BUG-1, evidencia del bug)
5. `screenshots/05-dashboard-modal-visible.png` — Dashboard CON modal (post-fix BUG-1)
6. `screenshots/06-modal-error-state.png` — Estado tras click "Activar" con stub (error handling)

## Transition

- Smoke test: **PASS**
- BUG-1 del smoke test: **FIXED**
- Tests totales: **1012/1012**
- FEAT lista para commit + PR.
