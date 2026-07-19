# Technical Design — FEAT-BIOMETRIC-UX

Frontend-only sobre infraestructura WebAuthn de [[biometric-auth]]. Cero migraciones, cero endpoints nuevos. Tres piezas: hook de decisión, modal opt-in, integración en DashboardPage + refactor LoginPage.

## Arquitectura

| Capa | Responsabilidad | Módulo |
|------|----------------|--------|
| Estado global | Señal efímera `shouldPromptBiometric` post-login/post-verify | `AuthContext.jsx` |
| Lógica | Decisión show/no show + lectura localStorage + check credenciales | `hooks/useBiometricPromptDecision.js` (nuevo) |
| Presentación | UI modal: idle/loading/error/success | `components/auth/BiometricOptInModal.jsx` (nuevo) |
| Integración | Hosting del modal (desktop + bottom-sheet mobile) | `pages/DashboardPage.jsx` |
| Entry point | CTA biométrico primario, jerarquía visual | `pages/LoginPage.jsx` |

## Flujo de decisión del hook

```
useBiometricPromptDecision():
  if !isWebauthnSupported()                          → {show: false}
  if !config.webauthn.enabled                        → {show: false}
  if !user.email_verified_at                         → {show: false}
  if localStorage.webauthn_device_registered === '1' → {show: false}
  if localStorage.biometric_prompt_declined_at AND
     (now - declined_at < 30d)                       → {show: false}
  → {show: true}
```

## LocalStorage schema

| Clave | Valor | Cuándo se escribe |
|-------|-------|-------------------|
| `webauthn_device_registered` | `'1'` | Tras registro exitoso (modal O ProfilePage) |
| `webauthn_device_registered_at` | ISO8601 | Junto al anterior |
| `biometric_prompt_declined_at` | ISO8601 | Al dismissar modal |

## Decisiones de diseño

- **localStorage-only para "declinado"** — decisión inherentemente per-dispositivo. Alt rechazada: columna `users.webauthn_prompt_declined_at` (semánticamente incorrecto + migración innecesaria).
- **Host en DashboardPage** (no AppLayout global) — 99% del tráfico post-login pasa por Dashboard; simplicidad gana.
- **Hook separado** — testeable unitariamente con mocks de localStorage y `listCredentials`.
- **Unificar CTAs biométricos en uno** — passkey discovery cubre el caso principal; se elimina botón "email + biometría".
- **Marker se escribe también desde `WebauthnCredentialsList`** (ProfilePage) para consistencia tras registro manual.

## API (reusa, sin cambios)

- `POST /api/auth/webauthn/register/begin` / `/complete`
- `GET /api/profile/webauthn-credentials` (detección "tiene credenciales")
- `GET /api/profile` (campo `email_verified_at`)

## Seguridad

- AC-11: modal solo si `email_verified_at` NOT null
- AC-12: si `config('webauthn.enabled') === false` backend devuelve 404 → frontend oculta via `probeEnabled()` (cacheado)
- localStorage solo flags + timestamps, sin PII
- Endpoints WebAuthn ya tienen challenge/response anti-replay

## Gotchas

- Usuario que limpia localStorage → re-prompt repetido. Mitigado con cooldown 30d.
- Si backend dice "credencial ya existe" en `/register/complete` → capturar error inline + escribir marker `=1` para auto-corregir localStorage inconsistente.
- Bottom-sheet mobile (Tailwind `sm:` breakpoint < 640px); reuso de CSS existente.

Origen: `docs/features/FEAT-BIOMETRIC-UX/03-technical-design.md`.
