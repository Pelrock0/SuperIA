# Test Gate: FEAT-BIOMETRIC-UX

## Verdict

**PASS**

## Test Execution

### Frontend (vitest)

| Metric | Value |
|--------|-------|
| Command | `npm test` (= `vitest run`) |
| Total Tests | **345** |
| Passing | **345** |
| Failing | **0** |
| Files | 46 passed |
| Duration | 22.9s |

### Backend (PHPUnit via artisan)

| Metric | Value |
|--------|-------|
| Command | `php artisan test` |
| Total Tests | **664** |
| Passing | **664** |
| Failing | **0** |
| Assertions | 1291 |
| Duration | 333.5s |

### Combined

| Metric | Value |
|--------|-------|
| **Total Tests** | **1009** |
| **Passing** | **1009** |
| **Failing** | **0** |
| **Pass rate** | **100%** |

Nota: la primera ejecución de backend falló con 629 PDOException porque MySQL local no estaba arrancado. Tras iniciar el servicio y relanzar, 664/664 pasan.

## Pre-existing failures fixed in this gate

3 tests fallaban pre-existentemente por rebranding "Superia" → "Superlistia" incompleto (componentes actualizados, test strings no). Fixes aplicados:

| File | Línea | Fix |
|------|-------|-----|
| `resources/js/components/lists/EmptyState.test.jsx` | :9 | `/bienvenido a superia/i` → `/bienvenido a superlistia/i` |
| `resources/js/components/collab/ConsentBanner.test.jsx` | :14 | `/lista compartida por un usuario de superia/i` → `/...superlistia/i` |
| `resources/js/components/collab/RevokedLinkView.test.jsx` | :34 | `/ir a superia/i` → `/ir a superlistia/i` |

Estos 3 archivos tenían modificaciones pre-FEAT en el working tree (los componentes ya decían "Superlistia"); los tests no habían sido actualizados. Arreglados aquí para cumplir el requisito de 100%.

## Acceptance Criteria Coverage

| AC ID | Description | Test | Status |
|-------|-------------|------|--------|
| AC-1 | Prompt post-login en dispositivo sin credencial | `useBiometricPromptDecision.test.js` — "returns allow=true when no marker and never declined" + `DashboardPage.test.jsx` — "renders modal when hook returns true" | **Covered** |
| AC-2 | Prompt post-registro tras verificar email | Cubierto por la misma lógica de AC-1 (aplicada al mismo mount de DashboardPage tras verify-redirect) | **Covered** |
| AC-3 | Navegador sin soporte WebAuthn | `useBiometricPromptDecision.test.js` — "returns allow=false when WebAuthn not supported" + `LoginPage.test.jsx` — "does NOT render CTA when WebAuthn not supported" | **Covered** |
| AC-4 | Usuario acepta activar biometría | `BiometricOptInModal.test.jsx` — "calls registerCredential and closes on success" | **Covered** |
| AC-5 | Usuario declina | `BiometricOptInModal.test.jsx` — "dismiss button marks declined and calls onClose" | **Covered** |
| AC-6 | Re-prompt tras 30 días | `useBiometricPromptDecision.test.js` — "returns allow=true when declined older than cooldown" | **Covered** |
| AC-7 | Dispositivo nuevo (localStorage limpio) | `useBiometricPromptDecision.test.js` — "returns allow=true when no marker and never declined" | **Covered** |
| AC-8 | Credencial ya registrada en este dispositivo | `useBiometricPromptDecision.test.js` — "returns allow=false when device marker present" | **Covered** |
| AC-9 | Registro biométrico falla | `BiometricOptInModal.test.jsx` — "shows error message on registration failure and does not mark device" + "uses fallback error message when err.message is empty" | **Covered** |
| AC-10 | CTA biométrico en LoginPage | `LoginPage.test.jsx` — "renders primary CTA above form when supported and enabled" + 8 tests relacionados | **Covered** |
| AC-11 | Sesión no verificada — bloqueo | `useBiometricPromptDecision.test.js` — "returns allow=false when email not verified" (security path) | **Covered** |
| AC-12 | Modal respeta feature flag global | `useBiometricPromptDecision.test.js` — "returns false when probe reports disabled" + `LoginPage.test.jsx` — "does NOT render CTA when probeEnabled returns false" | **Covered** |

**AC coverage: 12/12 (100%)**

## Path Coverage Matrix

| Path Type | Required | Found | Status | Notes |
|-----------|----------|-------|--------|-------|
| Happy Path | YES | 8+ | OK | Activación, login biométrico, refactor LoginPage, mostrar modal cuando corresponde |
| Failure Path | YES | 10+ | OK | registerCredential falla, probeEnabled rechaza/devuelve false, err sin message, server error con response.data.error.message |
| Edge Cases | YES | 12+ | OK | user null, email null, declinado reciente vs antiguo, fecha inválida en localStorage, storage bloqueado, unmount durante async probe, keys no-Escape |
| Security Path | YES | 2 | OK | AC-11 (email no verificado), AC-12 (flag global off) — ambos con tests dedicados |

## Database Test Configuration

| Check | Status | Notes |
|-------|--------|-------|
| Transaction wrapping | N/A | Feature frontend-only, sin escrituras de BD desde código nuevo |
| Real database (not SQLite) | YES | phpunit.xml usa `DB_CONNECTION=mysql`, `DB_DATABASE=superia` |
| Test isolation | YES | Backend heredado; frontend usa `vi.clearAllMocks()` + `window.localStorage.clear()` en `beforeEach` |

## Security Tests

| Category | Tests Found | Status |
|----------|-------------|--------|
| Authentication | 2 (AC-11 + AC-12 gates) | OK |
| Authorization | N/A (sin endpoints nuevos) | N/A |
| Input validation | N/A (sin input de usuario en el modal) | N/A |

## Missing Tests

Ninguno.

## Configuration Issues

Ninguno tras el fix del servicio MySQL (pre-existente ambiental, no del código).

## Feature Test Breakdown

| Test File | Tests | Status |
|-----------|-------|--------|
| `resources/js/lib/webauthnApi.test.js` | 25 | PASS |
| `resources/js/hooks/useBiometricPromptDecision.test.js` | 12 | PASS |
| `resources/js/components/auth/BiometricOptInModal.test.jsx` | 13 | PASS |
| `resources/js/pages/DashboardPage.test.jsx` | 10 | PASS |
| `resources/js/pages/LoginPage.test.jsx` | 17 | PASS |
| `resources/js/components/profile/WebauthnCredentialsList.test.jsx` | 10 | PASS |
| **FEAT total** | **87** | **PASS** |

## Transition

- Gate Status: **S5-TEST PASSED**
- Next Step: **S5-UX** (UX review — último gate de S5 antes de S6 completion).
