# Technical Design: FEAT-BIOMETRIC-UX

## Overview

El diseño es **frontend-only**: aprovecha toda la infraestructura WebAuthn ya entregada por `FEAT-BIOMETRIC-AUTH` (endpoints, `WebauthnService`, modelo `WebauthnCredential`, helpers en `resources/js/lib/webauthnApi.js`). No requiere migraciones, ni nuevos endpoints, ni cambios en `AuthService`.

Se introducen tres piezas:
1. **Hook** `useBiometricPromptDecision` — encapsula la lógica de decisión "mostrar/no mostrar modal", leyendo/escribiendo localStorage y consultando credenciales de la cuenta.
2. **Componente** `BiometricOptInModal` — UI del prompt con estados idle/loading/error/success.
3. **Integración** en `DashboardPage` (host del modal post-login/post-registro) y refactor de `LoginPage` para jerarquía visual nueva.

La decisión de persistir el estado "declinado" en `localStorage` (no en backend) simplifica radicalmente el diseño: cero migraciones, cero endpoints nuevos, cero cambios en contratos de API. El coste aceptado: la decisión es per-dispositivo y se pierde al limpiar cookies.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | — (sin cambios) | N/A |
| Services | — (sin cambios) | N/A (`WebauthnService` se reutiliza) |
| Infrastructure | — (sin cambios) | N/A (tabla `webauthn_credentials` intacta) |
| Controllers/API | — (sin cambios) | N/A (`WebauthnController` reutilizado) |
| Frontend — estado global | Exponer `shouldPromptBiometric` como señal efímera post-login/post-verify | `AuthContext.jsx` |
| Frontend — lógica | Decisión de mostrar modal, lectura/escritura de localStorage, chequeo de credenciales de cuenta | `hooks/useBiometricPromptDecision.js` (nuevo) |
| Frontend — presentación | UI del modal, gestión de estados, llamadas al flujo WebAuthn existente | `components/auth/BiometricOptInModal.jsx` (nuevo) |
| Frontend — integración | Hosting del modal (desktop y bottom-sheet mobile), disparador desde layout | `pages/DashboardPage.jsx` (modificado) |
| Frontend — entry point | CTA biométrico primario, refactor jerarquía, eliminación del botón email+biometría | `pages/LoginPage.jsx` (modificado) |

### Data Flow

**Flujo 1 — Login con email+password → modal post-login → registro**

```
LoginPage.jsx
   ├─ user submits email+password
   ├─ AuthContext.login() → POST /api/auth/login
   └─ setUser(...), navigate("/app")

DashboardPage.jsx (mount)
   ├─ useBiometricPromptDecision()
   │      ├─ if !isWebauthnSupported() → {show: false}
   │      ├─ if !config.webauthn.enabled (propagated via user profile or env) → {show: false}
   │      ├─ if !user.email_verified_at → {show: false}
   │      ├─ read localStorage: webauthn_device_registered
   │      │      └─ if true → {show: false}
   │      ├─ read localStorage: biometric_prompt_declined_at
   │      │      └─ if exists AND (now - declined_at < 30d) → {show: false}
   │      └─ return {show: true}
   ├─ if show → render <BiometricOptInModal onActivate onDismiss />
   │
   ├─ onActivate:
   │      ├─ setState: loading
   │      ├─ webauthnApi.registerCredential(deviceNameDefault)
   │      │      ├─ POST /api/auth/webauthn/register/begin
   │      │      ├─ navigator.credentials.create(...)
   │      │      └─ POST /api/auth/webauthn/register/complete
   │      ├─ success:
   │      │      ├─ localStorage.setItem('webauthn_device_registered', '1')
   │      │      ├─ localStorage.setItem('webauthn_device_registered_at', now)
   │      │      ├─ setState: success → close modal, toast "Biometría activada"
   │      │      └─ AuthContext.refreshUser() (opcional, para UI consistency)
   │      └─ failure: setState: error (render inline; no escribir marker)
   │
   └─ onDismiss (botón, X, ESC, click-outside):
          ├─ localStorage.setItem('biometric_prompt_declined_at', now)
          └─ close modal
```

**Flujo 2 — Post-registro (email verificado)**

```
Email verification link → /verify-email?token=XXX
   └─ backend marca email_verified_at, redirige a /app?verified=1

DashboardPage.jsx (mount con ?verified=1)
   └─ mismo useBiometricPromptDecision() se evalúa
       (email_verified_at ahora es NOT null → gate pasa)
```

**Flujo 3 — Login vía biometría (ya registrado)**

```
LoginPage.jsx
   ├─ CTA "Entrar con biometría" (sin email)
   ├─ AuthContext.loginWithPasskey(null)
   │      └─ webauthnApi.authenticate(null) → POST /api/auth/webauthn/authenticate/{begin,complete}
   └─ navigate("/app")

DashboardPage.jsx (mount)
   └─ useBiometricPromptDecision()
       └─ localStorage.webauthn_device_registered === '1' → {show: false}
```

### Transaction Boundaries

Sin transacciones de base de datos nuevas. El flujo de registro WebAuthn ya existente (`FEAT-BIOMETRIC-AUTH`) tiene sus propias transacciones en `WebauthnService::completeRegistration()`, que se reutilizan sin cambios.

**Idempotencia**: el modal escribe el marker `webauthn_device_registered` sólo tras respuesta exitosa del backend. Si el usuario recarga antes de que el backend confirme, el modal volverá a aparecer (comportamiento aceptable — simplemente re-intenta).

## Data Model

### New Tables/Collections

**Ninguna.**

### Migrations

**Ninguna.**

### LocalStorage Schema (cliente)

| Clave | Tipo | Cuándo se escribe | Cuándo se lee | Valor |
|-------|------|-------------------|---------------|-------|
| `webauthn_device_registered` | string `'1'` | tras registro WebAuthn exitoso (desde modal O desde ProfilePage) | al decidir si mostrar modal | `'1'` si registrado |
| `webauthn_device_registered_at` | ISO8601 string | junto al anterior | diagnóstico / futuras métricas | timestamp |
| `biometric_prompt_declined_at` | ISO8601 string | al dismissar el modal | al decidir si re-promptear (>30d) | timestamp |

**Nota**: `WebauthnCredentialsList.jsx` actual (ProfilePage) también debe escribir `webauthn_device_registered` tras registrar manualmente, para mantener consistencia — cambio mínimo dentro de scope.

### API Changes

**Ninguno.** Se reutilizan:
- `POST /api/auth/webauthn/register/begin`
- `POST /api/auth/webauthn/register/complete`
- `GET /api/profile/webauthn-credentials` (para detectar "usuario tiene credenciales en cuenta")
- `GET /api/profile` (ya expone `email_verified_at` — `ProfileController.php:25`)

## Performance

### Query Optimization

- El hook `useBiometricPromptDecision` hace **1 sola** llamada ligera al montarse: `GET /profile/webauthn-credentials` (sólo si las comprobaciones locales no deciden antes). Para el usuario típico con `webauthn_device_registered=1`, ni siquiera esa llamada — el hook cortocircuita.
- Sin N+1: `listCredentials()` devuelve array plano de credenciales del usuario autenticado (ya implementado en `FEAT-BIOMETRIC-AUTH`).

### Caching Strategy

- `probeEnabled()` en `webauthnApi.js` ya cachea el flag global (`_enabledCache`).
- Decisión del modal se calcula una vez por montaje de `DashboardPage`. No se re-evalúa en cada render (se usa `useMemo`/`useState` interno del hook).

### Async Processing

N/A. Todo sincrónico cliente-side; la única llamada asíncrona opcional (`listCredentials`) es una request REST estándar.

## Security

### Authentication / Authorization

- **Modal sólo renderiza en contexto autenticado** (DashboardPage está tras `ProtectedRoute`).
- **AC-11 bloqueo**: el hook chequea `user.email_verified_at` — si null, devuelve `{show: false}`. Evita vincular credencial a cuenta no verificada.
- **AC-12 flag global**: si `config('webauthn.enabled') === false`, backend devuelve 404 en endpoints WebAuthn. Frontend detecta vía `probeEnabled()` (ya cacheado) y oculta modal + CTA de LoginPage.

### Input Validation

- Sin input de usuario en el modal (sólo botones). No hay riesgo de inyección.
- localStorage keys tienen nombres estables, no se construyen dinámicamente con datos de usuario.

### Data Protection

- localStorage no almacena PII (sólo flags booleanos y timestamps).
- No se exponen datos sensibles nuevos en la UI (el nombre del dispositivo default se genera a partir de `navigator.userAgent` parseado en cliente — mismo enfoque que `FEAT-BIOMETRIC-AUTH`).

### CSRF / Replay

- Endpoints WebAuthn ya tienen challenge/response con anti-replay implementado en `WebauthnService`.
- No hay endpoints nuevos → superficie de ataque sin cambios.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| localStorage-only para estado "declinado" | Cero migraciones, cero endpoints, simple | No coherente multi-dispositivo, se pierde al limpiar cookies | **Seleccionado** — decisión inherentemente per-dispositivo |
| Columna `users.webauthn_prompt_declined_at` | Persistente, coherente entre navegadores | Migración + endpoint + complejidad; pero semánticamente incorrecto (decisión es per-dispositivo, no per-cuenta) | Rechazado |
| Híbrido (localStorage + columna opt-out global) | Cubre usuario que rechaza en todos lados | Overkill para este scope | Rechazado (deferred a FEAT futura si se detecta necesidad) |
| Host del modal en `AppLayout` global | Dispara desde cualquier ruta autenticada | Más intrusivo; requiere nuevo componente de layout si no existe | Rechazado |
| Host del modal en `DashboardPage` | DashboardPage es el landing post-login y post-verify | No cubre usuarios que navegan directo a `/app/listas/X` tras login | **Seleccionado** — 99% del tráfico pasa por Dashboard; simplicidad gana |
| Nuevo hook `useBiometricPromptDecision` | Lógica aislada, testeable unitariamente | Un archivo nuevo | **Seleccionado** — separación de concerns clara |
| Lógica inline en `DashboardPage.jsx` | Menos archivos | Mezcla decisión con render, difícil de testear aisladamente | Rechazado |
| Marker en localStorage se escribe también tras registro desde ProfilePage | Consistencia: si el usuario registró manualmente, el modal no vuelve | Cambio mínimo fuera del componente "core" de la FEAT | **Seleccionado** — dentro de scope, cubre AC-8 |
| Detección "credencial existe" por `aaguid` fingerprint | Más preciso | Frágil (navegadores no siempre exponen aaguid); complejidad alta | Rechazado |
| Detección por "tiene ≥1 credencial + localStorage limpio" | Simple, 95% efectivo | Puede mostrar prompt en dispositivo que ya tiene credencial si se borró localStorage | **Seleccionado** — coste UX bajo (el SO dirá "credencial ya existe" si el usuario activa); aceptado |
| Unificar los dos CTAs biométricos actuales en uno | UI más clara, menos confusión | Pérdida del flujo "email + biometría" (usuario raro) | **Seleccionado** — passkey discovery cubre el caso principal |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Usuario limpia localStorage → re-prompt repetido | Medium (UX) | Medium | Aceptado por diseño. Cooldown 30d mitiga en casos normales. |
| Modal se dispara dos veces si usuario verifica email e inmediatamente hace login | Low (UX) | Low | Hook usa `useMemo`+estado local para decidir una vez por montaje. Si el usuario realmente hace dos montajes, se muestra dos veces — comportamiento poco relevante. |
| Backend responde que ya existe credencial para ese `rpId`+usuario al intentar registrar | Medium (UX) | Low | Capturar error del endpoint `/register/complete` y mostrar mensaje inline específico: "Este dispositivo ya está registrado". Escribir `webauthn_device_registered=1` igualmente (auto-corrección del localStorage inconsistente). |
| Refactor de LoginPage rompe tests existentes | Medium (dev) | Medium | `LoginPage.test.jsx` debe actualizarse; cobertura 100% obligatoria en S4. Mantener `data-testid` existentes donde sea posible. |
| Usuario en navegador antiguo ve CTA biométrico por falso positivo de `isSupported()` | Low | Low | `isSupported()` ya verifica `PublicKeyCredential` y `navigator.credentials.{create,get}`; robusto. Error handling en AC-9 cubre fallos. |
| `probeEnabled()` cacheado con valor stale tras toggle de flag en prod | Low (ops) | Low | Caché se resetea en cada hard refresh. Aceptado — toggle de flag es operación infrecuente. |
| Bottom-sheet mobile no encaja con sistema de diseño actual | Low (UX) | Medium | Reutilizar Tailwind/CSS existente. S5-UX review validará antes de merge. |

## Open Questions

Ninguna. Todas las decisiones quedaron cerradas en S1 (`01-scope.md` — Resolved Decisions) y elaboradas en S2 (`02-prd.md` — Acceptance Criteria).

## Implementation Notes

### Orden sugerido de implementación (S4)

1. **Hook primero** (`resources/js/hooks/useBiometricPromptDecision.js`) + tests unitarios de la función pura de decisión (mockeando localStorage y `listCredentials`).
2. **Helpers de localStorage** (`resources/js/lib/webauthnApi.js` — añadir `markDeviceRegistered()`, `markPromptDeclined()`, `hasDeviceMarker()`, `getDeclinedAt()`).
3. **Componente modal** (`resources/js/components/auth/BiometricOptInModal.jsx`) con estados idle/loading/error/success. Tests de componente aislado (props-driven).
4. **Integración en DashboardPage** (`DashboardPage.jsx`) — añadir montaje condicional del modal; actualizar `DashboardPage.test.jsx` para cubrir AC-1, AC-2, AC-3, AC-8, AC-11, AC-12.
5. **Actualizar `WebauthnCredentialsList`** (`resources/js/components/profile/WebauthnCredentialsList.jsx`) para escribir marker tras registro manual — cambio de 2 líneas + test.
6. **Refactor de LoginPage** (`LoginPage.jsx`) — eliminar botón "Entrar con biometría" que requiere email (LoginPage.jsx:208), dejar sólo el passkey-discovery, reposicionar como CTA primario sobre el form. Actualizar `LoginPage.test.jsx` para cubrir AC-10.
7. **Responsive**: media query para bottom-sheet en viewport < 640px (Tailwind `sm:` breakpoint).

### Test coverage targets

| Archivo | Cobertura |
|---------|-----------|
| `useBiometricPromptDecision.js` | 100% (happy + todos los gates: no support, not verified, flag off, registered, declined-recent, declined-old, new device) |
| `BiometricOptInModal.jsx` | 100% (render + click Activar + click Cancelar + estados error/loading/success) |
| `DashboardPage.jsx` (nuevo código) | 100% de las ramas añadidas |
| `LoginPage.jsx` (código refactorizado) | 100% (CTA visible/oculto, click passkey, error auth) |
| `WebauthnCredentialsList.jsx` (2 líneas nuevas) | 100% |
| `webauthnApi.js` (nuevos helpers localStorage) | 100% |

### Stack constraints (de core.md)

- No SQLite en tests — N/A (sin cambios de DB)
- Transacciones con rollback en tests — N/A (sin cambios de DB)
- 100% coverage — obligatorio
- Tests deben cubrir: happy, failure, edge, security (AC-11 es el security path)

## Transition

- Gate Status: **S3 PASSED**
- Next Step: STEP 4 – Implementation
- Required Artifacts for Next Step: `02-prd.md`, `03-technical-design.md`
- Nota para S5: revisión UX obligatoria del modal y el LoginPage refactorizado.
