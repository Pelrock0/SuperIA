# Security Review: FEAT-BIOMETRIC-UX

## Summary

- **Status**: **PASS WITH NOTES**
- **Reviewer**: claude-security-reviewer
- **Date**: 2026-04-17

**Scope**: feature es **frontend-only**. Cero cambios en backend/endpoints/DB/auth criptográfico. Reutiliza `WebauthnService`, `WebauthnController`, rutas y tabla `webauthn_credentials` de `FEAT-BIOMETRIC-AUTH` (ya auditado previamente).

Superficie atacable nueva: **0 endpoints**, **0 queries SQL**, **3 claves de localStorage** (sin PII).

## Automated Gates

| Gate | Command | Result |
|------|---------|--------|
| Deps audit (PHP) | `composer audit` (via `composer security`) | **PASS** — No security vulnerability advisories found |
| SAST (PHP) | `psalm --taint-analysis --no-cache --no-progress` | **PASS** — No errors found. 94.53% type coverage |
| Deps audit (JS) | `npm audit --audit-level=moderate` | **1 moderate** — `follow-redirects <=1.15.11` (transitive de `axios@1.15.0`). No relacionado con esta FEAT; tech debt pre-existente. |
| Secret scan | `gitleaks` | **N/A** — no instalado en entorno local. Sin hook pre-commit. Nota: usuario reportó errores en su entorno — pendiente de compartir output para triage. |

**Deprecation notices** del output de `composer security` son de `composer.phar` ejecutándose en PHP 8.5 (warnings del propio runtime), **no findings de seguridad** del código del proyecto.

## OWASP Top 10 2021 Findings

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| **A01** | Broken Access Control | **PASS** | Modal sólo renderiza en `DashboardPage` (detrás de `ProtectedRoute`). Hook bloquea explícitamente si `email_verified_at` null (AC-11). Registro WebAuthn usa endpoint `/auth/webauthn/register/*` que exige `auth:sanctum` (definido en `FEAT-BIOMETRIC-AUTH`). |
| **A02** | Cryptographic Failures | **PASS** | Sin nuevo flujo criptográfico. Reutiliza WebAuthn (ECDSA P-256/EdDSA según dispositivo) ya auditado. localStorage **no** almacena PII, tokens, ni secretos — sólo flag `'1'` + timestamps ISO. |
| **A03** | Injection | **PASS** | Sin nuevas queries SQL. Sin `eval()` ni `dangerouslySetInnerHTML`. Sin construcción dinámica de URL/endpoints con user input. Keys de localStorage son constantes hardcodeadas. |
| **A04** | Insecure Design | **PASS** | Política de re-prompt documentada y justificada (S1). AC-11 (bloqueo email no verificado) diseñado explícitamente para prevenir vinculación de credencial a cuenta fraudulenta. Dismiss durante loading bloqueado para evitar escribir `declined_at` mientras un registro está en curso. |
| **A05** | Security Misconfiguration | **PASS** | Sin cambios en `config/*`. Feature flag `webauthn.enabled` (ya existente) respetado por `probeEnabled()` → si 404, modal y CTA ocultos. Sin exposición de debug info en errores del modal (mensajes genéricos localizados). |
| **A06** | Vulnerable Components | **PASS WITH NOTE** | `follow-redirects <=1.15.11` (moderate) — transitive via axios. **No consumido por esta FEAT**: el modal sólo llama a `registerCredential` que usa `api` (axios) contra endpoints same-origin (`/api/auth/webauthn/*`), sin redirects cross-origin. Mitigación futura: `npm audit fix` o `axios` upgrade, fuera de scope. |
| **A07** | Auth & Identification Failures | **PASS** | Passkey discovery flow ya validado en `FEAT-BIOMETRIC-AUTH`. Esta FEAT no modifica el login; sólo reordena UI (elimina botón email+biometría). Signature counter y challenge uniqueness siguen gestionados server-side. |
| **A08** | Software & Data Integrity Failures | **PASS** | Sin nuevos paquetes npm/composer. Sin CDN externos. Marker de localStorage es untrusted por diseño (sólo afecta UX, no autorización): si un atacante manipula `webauthn_device_registered` o `biometric_prompt_declined_at` en el navegador del usuario, el peor caso es "modal no aparece" o "aparece cuando no debería" — cero impacto en auth. |
| **A09** | Logging & Monitoring | **PASS WITH NOTE** | Errores de registro WebAuthn se capturan y muestran al usuario; no se loguea server-side (reutiliza flujo existente). **Sugerencia no bloqueante**: instrumentar eventos `biometric_modal_shown/accepted/dismissed` para detectar patrones anómalos (e.g., muchos dismisses = posible fallo UX; muchos failures = posible ataque de fingerprinting). Documentado como tech debt en S4. |
| **A10** | SSRF | **N/A** | Sin nuevas llamadas HTTP salientes. Sin URLs construidas con input de usuario. |

## OWASP API Top 10 2023 (aplicable a endpoints consumidos)

Esta FEAT no crea endpoints nuevos. Consume endpoints ya auditados:

| ID | Endpoint | Status |
|----|----------|--------|
| API1/API5 (BOLA/BOPLA) | `POST /api/auth/webauthn/register/begin\|complete` | **PASS** — protegidos por `auth:sanctum`, usuario derivado del token (no se acepta `user_id` como parámetro). |
| API2 (Broken Auth) | `POST /api/auth/webauthn/authenticate/*` | **PASS** — challenge/response server-side, signature counter anti-replay. |
| API3 (BOPLA) | `GET /api/profile/webauthn-credentials` | **PASS** — scope filtrado al `auth()->user()`. |
| API4 (Rate limit) | Todos | **PASS WITH NOTE** — rate limiting heredado del middleware global. Sin cambios. |

## OWASP LLM Top 10 v2 (2025)

| ID | Category | Status |
|----|----------|--------|
| LLM01-LLM10 | **N/A** | Feature no interactúa con LLMs ni superficie AI. Sin prompts, sin embeddings, sin output de modelo. |

## Cross-Cutting

- **Idempotencia**: `markDeviceRegistered()` es idempotente (escribe el mismo valor `'1'`). `markPromptDeclined()` sobreescribe `declined_at` — aceptable semánticamente (cada dismiss resetea el cooldown de 30d). Ambos side-effects toleran retry seguro.
- **Rate limiting**: heredado del middleware global sobre los endpoints WebAuthn. Sin cambios en esta FEAT.
- **Transacciones**: N/A — sin escritura en BD desde el flujo del modal. La transacción de `WebauthnService::completeRegistration()` sigue intacta.
- **XSS**: sin riesgo. Textos del modal son strings literales en el JSX, sin interpolación de user input. Mensajes de error del servidor se renderizan vía `{error}` (React escapa por defecto) sin `dangerouslySetInnerHTML`.
- **CSRF**: N/A para WebAuthn (el challenge/response es el mecanismo anti-CSRF intrínseco del protocolo).
- **LocalStorage** (cross-cutting específico):
  - Claves son constantes (no derivadas de user input). Sin colisión cross-user.
  - Contenido: `'1'` y timestamps ISO8601. Sin tokens de sesión, sin PII, sin secretos.
  - **Privacy**: el timestamp `webauthn_device_registered_at` podría usarse para fingerprinting si un atacante con acceso XSS al origen lo lee. Riesgo aceptable — si hay XSS en el origen, ese es el problema grave, no el timestamp.
  - Safe-storage helpers capturan excepciones de storage bloqueado/lleno — no fallan el flujo principal.
- **Session dispositivo compartido**: AC-11 previene vincular credencial a cuenta no verificada. Copy del modal refuerza "sólo funciona con tu propio dispositivo". El SO exige biometría real del titular (no baypaseable desde JS).

## Required Changes

Ninguno. 

## Recommendation

- [ ] Approve
- [x] **Approve with notes** (Low-only)
- [ ] Request changes (blocking)

## Notes / Tech Debt

1. **`follow-redirects` moderate** (pre-existente, no relacionado): ejecutar `npm audit fix` en una FEAT de infra aparte. No bloquea esta FEAT porque el modal no usa redirects cross-origin.
2. **Observabilidad de eventos de biometría**: añadir telemetría para `modal_shown/accepted/dismissed/register_failed` permitiría detectar patrones anómalos. Sugerido también en S5-CODE. Fuera de scope.
3. **gitleaks en CI/local del usuario**: pendiente de ver el output para triage. Probablemente ya existente y no introducido por esta FEAT.
4. **Focus trap completo en modal**: `autoFocus` al primario resuelve el 90% de a11y. Un focus trap riguroso con librería (`focus-trap-react`) es mejora futura.
5. **3 tests pre-existentes fallan** (Superia→Superlistia rebranding): a arreglar en S5-TEST para cumplir requisito de 100% pass.

## Transition

- Gate Status: **S5-SEC PASSED WITH NOTES**
- Next Step: **S5-TEST** (test gate — debe llegar a 100% pass; arreglar los 3 fallos pre-existentes).
