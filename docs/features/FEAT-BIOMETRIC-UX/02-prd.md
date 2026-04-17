# PRD: FEAT-BIOMETRIC-UX - Onboarding biométrico natural

## Business Objective

Aumentar la adopción de WebAuthn/biometría entre los usuarios de Superia convirtiendo el registro de credencial en un paso natural tras el primer login/registro, en lugar de una configuración manual escondida en ajustes.

**Por qué importa**:
- La adopción de passkeys es hoy cercana a cero porque el usuario no sabe que existe la opción.
- Los logins repetidos con email+password tienen fricción (memoria, errores de tipeo, reset flow).
- Passkeys reducen superficie de phishing y mejoran el tiempo de acceso.

**Valor esperado**: % significativo de usuarios activos con ≥1 credencial registrada tras la primera semana de uso de la cuenta (métrica concreta a definir en instrumentación, fuera del scope de esta FEAT).

## Problem Statement

**A quién afecta**: todos los usuarios autenticados de la web app (móvil + escritorio) que tienen soporte de WebAuthn en su dispositivo/navegador.

**Limitación actual**:
- Tras login exitoso, el usuario aterriza en `/app` sin ninguna sugerencia de activar biometría.
- La pantalla de login muestra dos botones biométricos con jerarquía confusa (uno requiere email, el otro no — `LoginPage.jsx:208` y `LoginPage.jsx:219`).
- El registro de credencial sólo es descubrible si el usuario visita perfil/ajustes por iniciativa propia.
- Los usuarios que declinan no tienen forma prevista de reconsiderar sin buscar manualmente.

## Scope

### In Scope

1. **Modal `BiometricOptInModal`** (nuevo componente) mostrado tras login o registro verificado si:
   - `isWebauthnSupported()` retorna true.
   - Usuario no tiene marker `webauthn_device_registered` en localStorage de este dispositivo/navegador.
   - `(now - biometric_prompt_declined_at > 30 días) OR (no existe declined_at en localStorage)`.
2. **Integración post-login**: disparar modal desde el host global (layout autenticado o `DashboardPage`).
3. **Integración post-registro**: disparar modal tras verificación exitosa de email (no en el registro inmediato).
4. **Refactor de `LoginPage.jsx`**:
   - CTA único "Entrar con biometría" (passkey discovery, sin email) encima del formulario email+password.
   - Eliminar el botón "Entrar con biometría" que requiere email previo (LoginPage.jsx:208).
   - CTA sólo visible si `isWebauthnSupported()` true.
5. **Registro desde modal**: al pulsar "Activar ahora", disparar flujo de registro WebAuthn existente (`WebauthnController` endpoints ya entregados en `FEAT-BIOMETRIC-AUTH`). Escribir marker `webauthn_device_registered` en localStorage tras éxito.
6. **Declinar desde modal**: al pulsar "Ahora no", escribir `biometric_prompt_declined_at=<timestamp>` en localStorage. Cerrar modal. No volver a disparar automáticamente hasta 30 días O dispositivo nuevo.
7. **Copy en español** (es-ES) por defecto.
8. **Tests**: cobertura 100% del componente nuevo, del refactor de LoginPage, y del hook/lógica de decisión de mostrar modal.

### Out of Scope

- Cambios en el flujo criptográfico de WebAuthn (reutiliza `WebauthnService` intacto).
- Cambios en endpoints API (`/api/auth/webauthn/*` sin modificar).
- Nuevas columnas en `users` o tablas nuevas (la decisión es localStorage-only).
- Cambios en `ProfilePage` / gestión de credenciales desde ajustes (intacta).
- i18n / soporte multi-idioma del copy del modal.
- Métricas e instrumentación (Amplitude/analytics) — se deja para FEAT separada.
- Opt-out global por cuenta ("no me vuelvas a preguntar nunca") — deferred; la decisión per-dispositivo cubre el caso principal.
- Detección avanzada de dispositivo (fingerprinting, `aaguid` matching).

## Acceptance Criteria

### AC-1: Prompt post-login en dispositivo sin credencial
- **Given**: usuario autenticado con email válido, navegador con WebAuthn soportado, localStorage sin marker `webauthn_device_registered`, sin `biometric_prompt_declined_at` (o con `declined_at` > 30 días).
- **When**: completa login con email+password y llega a `/app`.
- **Then**: se muestra `BiometricOptInModal` con título "¿Activar biometría en este dispositivo?", body explicativo y botones *Activar ahora* / *Ahora no*.

### AC-2: Prompt post-registro tras verificar email
- **Given**: usuario recién registrado, acaba de pulsar el link de verificación de email, navegador soporta WebAuthn, localStorage limpio.
- **When**: aterriza en `/app` tras verificación exitosa.
- **Then**: se muestra `BiometricOptInModal`.

### AC-3: Navegador sin soporte WebAuthn
- **Given**: navegador donde `isWebauthnSupported()` retorna false.
- **When**: usuario completa login.
- **Then**: modal NO se muestra. LoginPage NO muestra CTA biométrico.

### AC-4: Usuario acepta activar biometría
- **Given**: modal visible.
- **When**: usuario pulsa *Activar ahora* y completa el prompt nativo de biometría del SO (Face ID / Touch ID / Windows Hello).
- **Then**: credencial registrada vía flujo WebAuthn existente, marker `webauthn_device_registered=true` + `webauthn_device_registered_at=<timestamp>` escrito en localStorage, modal se cierra, toast de confirmación breve ("Biometría activada").

### AC-5: Usuario declina
- **Given**: modal visible.
- **When**: usuario pulsa *Ahora no*.
- **Then**: `biometric_prompt_declined_at=<timestamp>` escrito en localStorage, modal se cierra, no se muestra de nuevo automáticamente hasta `(now - declined_at > 30 días)` O detección de dispositivo nuevo.

### AC-6: Re-prompt tras 30 días
- **Given**: usuario declinó hace más de 30 días, localStorage aún tiene `biometric_prompt_declined_at` antiguo y no tiene marker de registro.
- **When**: completa login.
- **Then**: modal se muestra de nuevo.

### AC-7: Dispositivo nuevo (localStorage limpio)
- **Given**: usuario con ≥1 credencial registrada en su cuenta (en otros dispositivos), entra desde un navegador nuevo donde localStorage no tiene marker ni declined.
- **When**: completa login.
- **Then**: modal se muestra.

### AC-8: Credencial ya registrada en este dispositivo
- **Given**: localStorage tiene marker `webauthn_device_registered=true`.
- **When**: usuario completa login.
- **Then**: modal NO se muestra. CTA biométrico en LoginPage sigue visible (login via passkey).

### AC-9: Registro biométrico falla
- **Given**: modal visible, usuario pulsa *Activar ahora*.
- **When**: flujo WebAuthn falla (usuario cancela el prompt del SO, timeout, error de red).
- **Then**: se muestra mensaje de error inline en el modal, sin escribir marker ni declined, botones siguen activos para reintentar o cerrar.

### AC-10: CTA biométrico en LoginPage
- **Given**: usuario en `/login`, `isWebauthnSupported()` true.
- **When**: la página carga.
- **Then**: CTA "Entrar con biometría" visible encima del formulario email+password; formulario tradicional sigue disponible debajo.

### AC-11: Sesión no verificada — seguridad
- **Given**: usuario registrado pero NO ha verificado email todavía (sesión autenticada pero `email_verified_at` null).
- **When**: aterriza en `/app`.
- **Then**: modal NO se muestra. Se evita vincular credencial a cuenta no verificada.

### AC-12: Modal respeta feature flag global
- **Given**: `config('webauthn.enabled') === false`.
- **When**: usuario completa login.
- **Then**: modal NO se muestra. LoginPage NO muestra CTA biométrico.

## UX Decision

- **UX Designer Required**: **YES**
- **Razón**: introduce un componente modal nuevo (`BiometricOptInModal`) y modifica la jerarquía visual de `LoginPage` (componente crítico del producto).
- **UX Artifacts**: `docs/features/FEAT-BIOMETRIC-UX/ux-wireframes.html` — **pendiente de generar por `@.cursor/agents/ux-designer.md`**.
- **Referencias de tokens/estilo**: reutilizar el sistema de diseño existente de Superia (colores en `resources/css/`, gradientes ya presentes en `LoginPage.jsx`).

**Notas UX inline (pre-wireframes)**:
- Modal dismissible por botón *Ahora no* y por X/ESC (equivalente a "Ahora no").
- CTA primario *Activar ahora* debe tener contraste alto y posición derecha.
- Body breve con iconografía sugerente (Face ID / Touch ID / huella) — no imágenes de marcas, iconos genéricos.
- Soporte mobile-first: modal ocupa el ancho completo con padding cómodo en viewport pequeño.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Usuario en dispositivo compartido acepta accidentalmente y registra su biometría en una sesión no suya | Security | Mitigado parcialmente: WebAuthn exige prompt del SO (Face ID/huella) — el titular real es quien debe presentar biometría. Documentar en copy: "Sólo funciona con tu propio dispositivo/usuario". |
| LocalStorage borrado → re-prompt recurrente a usuarios que declinaron intencionalmente | UX | Aceptado por diseño (decisión per-dispositivo). Mitigación: cooldown de 30 días mitiga spam accidental. |
| Doble prompt si usuario hace login inmediatamente tras verificar email | UX | Trigger único coordinado por flag en estado (`shouldPromptBiometric`) que se limpia tras mostrar. |
| Refactor de LoginPage rompe login existente | Technical | Tests de regresión de `LoginPage.test.jsx` deben seguir pasando. Cobertura 100% obligatoria. |
| Modal se muestra sobre sesión no verificada (email no confirmado) y registra credencial a cuenta potencialmente falsa | Security | AC-11 lo cubre explícitamente: bloquear modal si `email_verified_at === null`. |
| `isWebauthnSupported()` falso positivo en navegadores legacy | Technical | Manejo de errores en AC-9: si el flujo falla, mensaje inline sin bloquear la sesión. |

## Assumptions

- La infraestructura WebAuthn (`WebauthnService`, endpoints, modelo `WebauthnCredential`) de `FEAT-BIOMETRIC-AUTH` está funcional y testeada.
- `isWebauthnSupported()` y el helper `loginWithPasskey()` en `AuthContext` están disponibles (confirmado en `LoginPage.jsx:5` y :9).
- El flag `webauthn.enabled` (`config/webauthn.php`) se respeta desde backend y frontend.
- `email_verified_at` en `users` se setea al verificar email (flujo estándar de Laravel).
- Los tests de frontend se ejecutan con el framework ya configurado (React Testing Library + Vitest/Jest según `npm test`).

## Open Questions

Ninguna. Todas las decisiones quedaron cerradas en S1 (ver `01-scope.md` — Resolved Decisions).

## Approval

- [ ] PRD aprobado por el usuario el {fecha}
- [ ] `ux-wireframes.html` generado y referenciado

## Transition

- Gate Status: **S2 PASSED**
- Next Step: STEP 3 – Technical Design
- Required Artifacts for Next Step: `02-prd.md`, `ux-wireframes.html`
