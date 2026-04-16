# Scope Analysis: FEAT-BIOMETRIC-AUTH

## Feature Request

Permitir que el usuario acceda a la web app de Superia usando biometría (Touch ID, Face ID, Windows Hello, huella Android) en lugar de email + password.

**Confirmaciones del usuario:**
- Plataforma: web app responsive (mobile-first + desktop). NO app nativa.
- Email + password se mantiene como **fallback principal** (no se elimina).
- **Multi-dispositivo**: un usuario puede registrar credenciales biométricas en varios dispositivos (móvil + laptop + tablet).

**Tecnología propuesta**: WebAuthn / FIDO2 / Passkeys (W3C standard). Es el único camino estándar en web para usar biometría del dispositivo — no se accede directamente al sensor; el navegador delega en el sistema operativo (Touch ID, Face ID, Windows Hello, etc.).

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 24-40 horas |
| Confidence | Medium |

## Justification

Cumple **múltiples** criterios HIGH:

1. **Security-critical**: nueva vía de autenticación al sistema. Un fallo permite acceso no autorizado a cuentas.
2. **Architectural decisions required**: configuración de Relying Party (rpId, origin), política de attestation (none / indirect / direct), discoverable credentials vs server-side credentials, user verification level (preferred / required).
3. **Cross-system workflow**: toca login flow (`AuthService`), gestión de usuarios (`User` model), JWT issuance, frontend AuthContext, profile/settings UI, password reset flow (¿qué pasa si pierdes el dispositivo y olvidas password?).
4. **External standard library**: el ecosistema PHP usa `web-auth/webauthn-lib` (mantenida) o `pragmarx/google2fa` no aplica aquí. Hay que evaluar/elegir librería.
5. **High blast radius**: bug en verificación de assertion = bypass de auth. Bug en challenge generation = replay attacks.
6. **Compliance implications**: WebAuthn requiere HTTPS en producción (ya cumplido), pero requiere configuración cuidadosa de RP ID que afecta a multi-dominio/subdominio si lo hay.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | **High** | WebAuthn es un protocolo no trivial: challenge/response flow asíncrono, registro vs autenticación con flujos distintos, attestation parsing (CBOR), validación de signature counter para detectar credenciales clonadas. Curva de aprendizaje real. Hay que elegir librería (recomendado `web-auth/webauthn-lib` para Laravel). |
| Data | **Medium** | Nueva tabla `webauthn_credentials` (credentialId, publicKey, signCount, transports, aaguid, user_id, created_at, last_used_at, name). Sin migraciones destructivas. PII sensible: la public key NO es PII pero el credentialId + nombre del dispositivo sí pueden ser identificadores. |
| Security | **High** | Es el corazón del riesgo: (a) replay attacks si no se valida challenge único por sesión, (b) credential cloning si no se valida signCount, (c) phishing si rpId mal configurado, (d) lockout si el usuario pierde TODOS sus dispositivos y no tiene email+password fuerte (mitigado: mantener fallback obligatorio), (e) account enumeration via lista de credenciales por email. Requiere S5-SEC review exhaustivo. |
| Performance | **Low** | Cero impacto. Verificación de assertion es operación criptográfica O(1). Una query extra a `webauthn_credentials` por login. |
| Operational | **Medium** | Requiere HTTPS en todos los entornos donde se quiera testear (incluido staging). En `localhost` funciona sin HTTPS por excepción del estándar. Rollback fácil (feature flag o eliminar endpoints). Monitoring: log de fallos de assertion (potenciales ataques). |

## Affected Areas

### Backend
- `app/Models/User.php` — relación `webauthnCredentials()`
- Nuevo modelo `app/Models/WebauthnCredential.php`
- Nueva migración `database/migrations/*_create_webauthn_credentials_table.php`
- `app/Services/AuthService.php` — métodos `beginRegistration()`, `completeRegistration()`, `beginAuthentication()`, `completeAuthentication()`
- `app/Http/Controllers/Auth/WebauthnController.php` — endpoints
- `routes/api.php` — 4 endpoints nuevos
- `composer.json` — añadir `web-auth/webauthn-lib`
- `config/webauthn.php` — RP ID, origin, timeout, attestation policy

### Frontend
- `resources/js/lib/webauthnApi.js` — wrapper sobre `navigator.credentials.create/get`
- `resources/js/pages/LoginPage.jsx` — botón "Entrar con biometría"
- `resources/js/pages/ProfilePage.jsx` — sección "Dispositivos biométricos" (lista + registrar + revocar)
- `resources/js/contexts/AuthContext.jsx` — método `loginWithPasskey()`

### Tests
- Feature tests para los 4 endpoints
- Unit tests para AuthService methods
- Mocks de WebAuthn (no se puede ejercitar el sensor en tests)

## Resolved Decisions (input para S2)

| # | Decision | Value | Notes |
|---|----------|-------|-------|
| 1 | `rpId` (Relying Party ID) | **Prod**: `superlistia.com` · **Dev**: `superia.com.local` (+ `localhost` por excepción del estándar) | Configurable por env var `WEBAUTHN_RP_ID` |
| 2 | User Verification policy | `preferred` | Pide biometría/PIN si hay, no bloquea si no |
| 3 | Attestation policy | `none` | Adecuado para consumer app, sin necesidad de tracking de hardware |
| 4 | Usuario puede nombrar sus credenciales | **Sí** | Campo `name` (ej. "iPhone 14", "Laptop trabajo"). Default = `User-Agent` parseado |
| 5 | Recuperación si pierde dispositivo + password | **Reset por email** (flujo existente) | No se introduce nuevo flujo. Document en PRD que el reset por email también revoca o no las credenciales WebAuthn (decisión a tomar en S2) |
| 6 | Discoverable credentials (login sin email) | **Soportar ambos modos** | (a) con email previo: `allowCredentials` filtrado por user · (b) sin email: `residentKey: required` para passkeys verdaderas |
| 7 | Feature flag para rollout | **Sí** | Config flag `webauthn.enabled` (env var). Permite shipping detrás del flag y enable progresivo |

## Open Questions

Ninguna. Todas las decisiones de scope están resueltas.

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] **Require PRD (MEDIUM/HIGH → STEP 2)**
- [ ] Escalate to architect

**Justificación**: HIGH complexity + security-critical + decisiones de arquitectura abiertas. Necesita PRD formal en S2 (acceptance criteria precisos, decisiones de UX explícitas) y Tech Design en S3 (elección de librería, configuración de RP, esquema de DB, flujo exacto de challenge/response).

## Transition

- Gate: S1 PASSED
- Next Step: STEP 2 (PRD Writing)
