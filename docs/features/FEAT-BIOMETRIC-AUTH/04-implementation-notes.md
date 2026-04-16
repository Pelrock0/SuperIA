# Implementation Notes: FEAT-BIOMETRIC-AUTH

_Backend + Frontend implementation complete._

---

# Backend Implementation Notes: FEAT-BIOMETRIC-AUTH

## Summary

Backend implementation of WebAuthn / Passkeys as alternative authentication path alongside email+password. Added `web-auth/webauthn-lib` ^5.0, new `webauthn_credentials` table, `WebauthnService` orchestrating challenge lifecycle and crypto verification, `WebauthnController` with 7 endpoints, feature flag middleware, and integration with password reset (AC-9). Frontend pending in subsequent S4 iteration.

## Files Changed

| File | Type | Description | Tests |
|------|------|-------------|-------|
| `composer.json` / `composer.lock` | Modified | Added `web-auth/webauthn-lib:^5.0` dependency | N/A |
| `config/webauthn.php` | Created | Feature flag, RP config, origins, TTLs, algorithms | Exercised by all WebauthnTest |
| `.env.example` | Modified | Added WEBAUTHN_* env vars | N/A |
| `database/migrations/2026_04_16_142310_create_webauthn_credentials_table.php` | Created | `webauthn_credentials` table per tech design | Exercised by all WebauthnTest |
| `app/Models/WebauthnCredential.php` | Created | Model with casts, user relation, adapters to/from `PublicKeyCredentialSource`, base64url helpers | WebauthnTest |
| `app/Models/User.php` | Modified | Added `webauthnCredentials()` HasMany relation | WebauthnTest |
| `app/Services/WebauthnService.php` | Created | Orchestrates challenge lifecycle, registration/assertion verification, CRUD, cascade revocation | WebauthnTest |
| `app/Http/Controllers/Auth/WebauthnController.php` | Created | 7 endpoints: 4 WebAuthn + 3 CRUD. Authz on credentials by owner | WebauthnTest |
| `app/Http/Middleware/EnsureWebauthnEnabled.php` | Created | 404 when feature flag disabled (anti-enumeration per AC-11) | WebauthnTest |
| `app/Http/Requests/Auth/Webauthn/BeginAuthenticationRequest.php` | Created | Email nullable+email+max:255 | WebauthnTest |
| `app/Http/Requests/Auth/Webauthn/CompleteAuthenticationRequest.php` | Created | handle (uuid) + credential (array) | WebauthnTest |
| `app/Http/Requests/Auth/Webauthn/CompleteRegistrationRequest.php` | Created | handle (uuid) + name (1-50) + credential (array) | WebauthnTest |
| `app/Http/Requests/Auth/Webauthn/UpdateCredentialRequest.php` | Created | name (1-50) | WebauthnTest |
| `app/Http/Controllers/Auth/PasswordResetController.php` | Modified | Injected WebauthnService; on successful reset calls `revokeAllForUser` (AC-9) | WebauthnTest |
| `routes/api.php` | Modified | Registered 7 WebAuthn routes under feature-flag middleware. Public auth endpoints with throttle:20,1 | WebauthnTest |

## Migrations

| Migration | Description | Reversible |
|-----------|-------------|------------|
| `2026_04_16_142310_create_webauthn_credentials_table` | Creates table with columns per design. `credential_id` stored as base64url VARCHAR(512) (not raw binary, to allow UNIQUE index in MySQL). UNIQUE(credential_id), INDEX(user_id). FK to users with CASCADE DELETE | Yes |

## API Contract (Backend → Frontend)

### Endpoints Created

| Method | Path | Auth | Feature Flag | Description |
|--------|------|------|--------------|-------------|
| POST | `/api/auth/webauthn/authenticate/begin` | Public | Required | Generate options for login. Body: `{email?: string}` |
| POST | `/api/auth/webauthn/authenticate/complete` | Public | Required | Verify assertion → JWT. Body: `{handle: uuid, credential: object}` |
| POST | `/api/auth/webauthn/register/begin` | JWT | Required | Generate options for new credential. Empty body |
| POST | `/api/auth/webauthn/register/complete` | JWT | Required | Verify attestation → persist credential. Body: `{handle: uuid, name: string, credential: object}` |
| GET | `/api/profile/webauthn-credentials` | JWT | Required | List user's credentials |
| PATCH | `/api/profile/webauthn-credentials/{id}` | JWT | Required | Rename credential (AC-7). Body: `{name: string}` |
| DELETE | `/api/profile/webauthn-credentials/{id}` | JWT | Required | Revoke credential (AC-8) |

All endpoints respond with **404** when `webauthn.enabled=false` (AC-11).

### Request/Response Examples

```json
// POST /api/auth/webauthn/register/begin (JWT required)
// Request: {}
// Response 200:
{
  "data": {
    "handle": "uuid-string",
    "options": {
      "rp": { "id": "superia.com.local", "name": "Superia" },
      "user": { "id": "base64url", "name": "user@email.com", "displayName": "Pedro" },
      "challenge": "base64url",
      "pubKeyCredParams": [{"type":"public-key","alg":-7}, {"type":"public-key","alg":-257}],
      "timeout": 60000,
      "attestation": "none",
      "authenticatorSelection": {"userVerification":"preferred","residentKey":"preferred"},
      "excludeCredentials": []
    }
  }
}

// POST /api/auth/webauthn/register/complete
// Request: { "handle": "uuid", "name": "iPhone 14", "credential": { "id": "...", "rawId": "...", "type": "public-key", "response": { "clientDataJSON": "...", "attestationObject": "..." } } }
// Response 201:
{ "data": { "id": 1, "name": "iPhone 14", "transports": ["internal"], "created_at": "2026-04-16T..." } }
// Response 422 on verification failure:
{ "error": { "code": "WEBAUTHN_REGISTRATION_FAILED", "message": "..." } }

// POST /api/auth/webauthn/authenticate/begin
// Request: { "email": "user@test.com" }  (or {} for discoverable mode)
// Response 200:
{
  "data": {
    "handle": "uuid",
    "options": {
      "challenge": "base64url",
      "rpId": "superia.com.local",
      "allowCredentials": [{"type":"public-key","id":"base64url","transports":["internal"]}],
      "userVerification": "preferred",
      "timeout": 60000
    }
  }
}

// POST /api/auth/webauthn/authenticate/complete
// Request: { "handle": "uuid", "credential": { ... } }
// Response 200:
{ "data": { "token": "eyJhbGc...", "user": { "id": 1, "name": "Pedro", "email": "pedro@test.com" } } }
// Response 401 on any failure (expired handle, invalid signature, counter regression, unknown credential):
{ "error": { "code": "WEBAUTHN_AUTH_FAILED", "message": "Autenticacion biometrica fallida." } }

// GET /api/profile/webauthn-credentials
// Response 200:
{ "data": [
    { "id": 1, "name": "iPhone 14", "transports": ["internal"], "last_used_at": "...", "created_at": "..." }
] }

// PATCH /api/profile/webauthn-credentials/{id}
// Request: { "name": "Mi iPhone" }
// Response 200:
{ "data": { "id": 1, "name": "Mi iPhone" } }

// DELETE /api/profile/webauthn-credentials/{id}
// Response 200:
{ "data": { "message": "Dispositivo revocado." } }
```

### Error Codes

| Code | Meaning | Frontend Action |
|------|---------|-----------------|
| 401 | Assertion failed or unauth | Show error, keep password fallback visible |
| 403 | User not owner of credential | Show access denied |
| 404 | Feature flag disabled | Hide WebAuthn UI entirely |
| 422 | Validation error (name too long, invalid handle) or registration failed | Show field errors or generic retry |

## Implementation Decisions

1. **Library**: `web-auth/webauthn-lib:^5.0` (canonical). No Laravel wrapper — direct integration with our `AuthService` pattern.

2. **Challenge storage**: Laravel cache (`Cache::put`) with 5-min TTL, keyed by `webauthn:{type}:{uuid_handle}`. Handle is a UUID returned to the client and passed back on complete. Single-use — forgotten after verify.

3. **`credential_id` storage**: Base64url VARCHAR(512) rather than raw binary (MySQL BLOB cannot be UNIQUE without prefix).

4. **Feature flag behavior**: All 7 endpoints return **404** when disabled (anti-enumeration). Implemented via `EnsureWebauthnEnabled` middleware.

5. **Password reset integration**: Revocation added inside `Password::reset()` callback in `PasswordResetController`. No separate `PasswordResetService` exists in this codebase; extraction was out of scope.

6. **Anti-enumeration**: `createAuthenticationOptions(email)` always queries DB for user and returns a consistent options shape with empty `allowCredentials` for unknown emails.

7. **JWT reuse**: Successful assertion emits a token via `JWTAuth::fromUser($user)` — same flow as password login.

## Tests Added

| Test File | Type | Tests | What they cover |
|-----------|------|-------|-----------------|
| `tests/Feature/WebauthnTest.php` | Feature | 28 | Feature flag (AC-11), auth required, authz cross-user, list/rename/delete (AC-7, AC-8), multi-device listing (AC-6 partial), begin registration options shape, begin auth with/without/unknown email (anti-enumeration), validation errors, expired challenge rejection, password reset revokes (AC-9), password change does not revoke (AC-10), service-level revoke scope |

### AC Coverage Matrix

| AC | Covered by | Notes |
|----|-----------|-------|
| AC-1 (register first credential) | `test_begin_registration_returns_options` + crypto delegated to lib | Full flow validated in S5-UX browser |
| AC-2 (registration errors) | `test_complete_registration_requires_handle_and_credential`, `test_complete_registration_rejects_invalid_name`, `test_complete_registration_with_expired_handle_fails` | |
| AC-3 (login with email) | `test_begin_authentication_with_email_returns_allow_credentials` + crypto delegated | Full flow in S5-UX |
| AC-4 (login without email) | `test_begin_authentication_without_email_returns_empty_allow_credentials` + crypto delegated | Full flow in S5-UX |
| AC-5 (fallback to password) | Backend emits 401 (`test_complete_authentication_with_expired_handle_fails`). UI fallback in frontend phase | |
| AC-6 (multi-device) | `test_list_credentials_returns_user_credentials_only` (2 credentials), `test_begin_registration_excludes_existing_credentials` | |
| AC-7 (rename) | `test_user_can_rename_own_credential`, validation tests | |
| AC-8 (revoke) | `test_user_can_delete_own_credential`, authz tests | |
| AC-9 (password reset revokes) | `test_password_reset_revokes_all_webauthn_credentials`, `test_password_reset_does_not_affect_other_users_credentials` | |
| AC-10 (password change does not revoke) | `test_password_change_from_profile_does_not_revoke_credentials` | |
| AC-11 (feature flag 404) | `test_feature_flag_disabled_returns_404_for_all_endpoints` | |
| AC-12 (replay attack) | Indirect: challenge is single-use (forgotten after verify). Full replay test requires a real assertion | Verify in S5-SEC |
| AC-13 (credential cloning) | SignCount validation logic in `WebauthnService::verifyAssertion` (lines 154-164). Full test requires real assertions | Verify in S5-SEC |
| AC-14 (rpId mismatch) | Delegated to `web-auth/webauthn-lib` (`CheckRelyingPartyIdIdHash`). Library has its own tests | Verify in S5-SEC |
| AC-15 (browser no WebAuthn support) | Frontend capability detection (frontend phase) | |

## Test Coverage Report

- Services (WebauthnService): non-crypto paths 100%; crypto verification delegated to library
- Controllers (WebauthnController): 100% — every endpoint tested
- Middleware (EnsureWebauthnEnabled): 100% (enabled + disabled branches)
- Models (WebauthnCredential): helpers + adapters exercised
- **Feature tests: 28 passing, 60 assertions**
- **Full suite: 664/664 passing** (up from 636, zero regressions)

## Known Issues / Technical Debt

1. **Crypto flow test limitation**: Cannot generate valid WebAuthn assertions synthetically without hardware. Browser-based S5-UX testing covers this end-to-end with real authenticator hardware or virtual test authenticators.

2. **No composer.lock commit verification**: The S5-SEC security gate (`composer audit` in `tests/Feature/SecurityGatesIntegrationTest.php`) will catch vulnerabilities in `web-auth/webauthn-lib` transitive deps.

## Deviations from Design

1. **Minor**: Tech design proposed extracting `AuthService::issueTokenForUser()`. Implemented inline — single `JWTAuth::fromUser($user)` call.
2. **Minor**: Challenge keyed by UUID `handle` instead of session_id (JWT API is stateless, no Laravel session).
3. **Minor**: `credential_id` as base64url VARCHAR(512) (MySQL BLOB UNIQUE index limitation).

## Transition (backend)

- Gate Status: S4 (backend) PASSED — frontend phase started and completed below

---

# Frontend Implementation Notes: FEAT-BIOMETRIC-AUTH

## Summary

Frontend implementation of WebAuthn UI following the tech design and PRD. Added `webauthnApi.js` wrapper over `navigator.credentials.*`, extended `AuthContext` with `loginWithPasskey`, added biometric/passkey buttons to `LoginPage`, and created `WebauthnCredentialsList` component integrated into `ProfilePage`. UI gracefully hides when backend feature flag is off (404 on probe) or the browser doesn't support WebAuthn.

## Components Created/Modified

### Created

| Component | Path | Tests | Coverage |
|-----------|------|-------|----------|
| `webauthnApi.js` | `resources/js/lib/webauthnApi.js` | `webauthnApi.test.js` (14 tests) | 100% of exported functions |
| `WebauthnCredentialsList.jsx` | `resources/js/components/profile/WebauthnCredentialsList.jsx` | `WebauthnCredentialsList.test.jsx` (12 tests) | 100% of UI states |

### Modified

| Component | Changes |
|-----------|---------|
| `resources/js/context/AuthContext.jsx` | Added `loginWithPasskey(email?)` method that calls `webauthnAuthenticate`, updates `user` state |
| `resources/js/pages/LoginPage.jsx` | Added capability check + probe on mount, 2 buttons: "Entrar con biometría" (requires email) + "Entrar con passkey" (discoverable). Hidden when `isSupported()` false or backend returns 404 |
| `resources/js/pages/ProfilePage.jsx` | Imported + integrated `<WebauthnCredentialsList />` inside the Seguridad section |

## State Management

- **Local state** in `LoginPage` for `webauthnAvailable`, `webauthnStatus`, tied to the existing `error` state
- **Local state** in `WebauthnCredentialsList` for credentials list, loading, error, registering, inline rename edit state
- **Probe cache** in `webauthnApi.js` (`_enabledCache`) to avoid repeated 404 probes per page mount
- **Global state** via `AuthContext` for `loginWithPasskey` — consistent with existing `login` pattern

## API Integration (Frontend → Backend)

| Endpoint | Function | Error Handling |
|----------|----------|----------------|
| `POST /api/auth/webauthn/register/begin` | `registerCredential(name)` | Throws clear msg |
| `POST /api/auth/webauthn/register/complete` | `registerCredential(name)` | Maps `NotAllowedError` → "Registro cancelado", generic → retry msg |
| `POST /api/auth/webauthn/authenticate/begin` | `authenticate(email?)` + `probeEnabled()` | Probe distinguishes 404 (feature off) from other errors |
| `POST /api/auth/webauthn/authenticate/complete` | `authenticate(email?)` | Maps `NotAllowedError` → "Autenticacion cancelada" |
| `GET /api/profile/webauthn-credentials` | `listCredentials()` | 404 → hides section |
| `PATCH /api/profile/webauthn-credentials/{id}` | `renameCredential(id, name)` | Field error shown inline |
| `DELETE /api/profile/webauthn-credentials/{id}` | `deleteCredential(id)` | Error shown inline |

## Tests Added

| Test File | Type | Tests | What it tests |
|-----------|------|-------|---------------|
| `resources/js/lib/webauthnApi.test.js` | Unit | 14 | `isSupported` capability detection, base64url helpers round-trip, `probeEnabled` + 404 handling + caching, list/rename/delete thin wrappers, `registerCredential` / `authenticate` error mapping (NotAllowedError, not-supported) |
| `resources/js/components/profile/WebauthnCredentialsList.test.jsx` | Component | 12 | Renders nothing when not supported / 404, empty state + add, list rendering, add credential flow, register failure shows error, revoke with confirm, revoke cancelled, inline rename save, rename validation |
| `resources/js/pages/LoginPage.test.jsx` | Component | — | Updated mock to include `loginWithPasskey` and webauthnApi so existing tests still pass; section hidden when `isSupported=false` |

## Test Coverage Report

| Component Type | Tests | Coverage |
|----------------|-------|----------|
| Utilities/Helpers (webauthnApi) | 14 | 100% of exported functions |
| Components (WebauthnCredentialsList) | 12 | 100% UI branches covered |
| LoginPage (existing tests updated) | — | Regression-safe with mocks |
| **Total frontend suite** | **298 passing** | Zero regressions |

## Visual Validation

Deferred to **S5-UX** with MCP chrome-devtools. Frontend-developer agent cannot run browser in Claude Code context today; backend HTTPS/`rpId` setup and real authenticator hardware are also needed for meaningful end-to-end visual testing.

**Expected visual states to verify in S5-UX:**
- LoginPage: biometric buttons hidden when feature disabled / unsupported browser
- LoginPage: biometric buttons visible + disabled "Entrar con biometría" until email is typed
- ProfilePage: empty state → "Añadir mi primer dispositivo"
- ProfilePage: list with 1+ credentials + rename inline edit + revoke confirm dialog
- Error rendering on cancel / failures

## Accessibility

- All buttons use `<button>` element
- `aria-label` on icon-only actions (Renombrar / Revocar) with credential name context
- Semantic headings (`h3` for section title)
- Keyboard-reachable inline rename input with Guardar / Cancelar buttons
- Error messages with `role="alert"`

## Notes for Reviewers

1. **Probe strategy**: LoginPage and WebauthnCredentialsList both probe the backend to decide whether to render UI. This covers the feature-flag-off scenario without requiring a separate config endpoint and avoids backend changes.
2. **Default device name**: Simple User-Agent parse (`iPhone — Safari`, `Windows — Chrome`). Intentionally minimal — not UA fingerprinting.
3. **Build size**: `npm run build` emits a ~854KB bundle (gzip 248KB). Build warning about chunk size is pre-existing, not introduced by this feature.
4. **Crypto flow end-to-end testing**: Cannot be unit-tested in the frontend because `navigator.credentials.create/get` require real hardware. The S5-UX phase with MCP chrome-devtools is where this gets validated manually in the browser.

## Deviations from Design

1. **Minor**: Default device name computed client-side from `navigator.userAgent` (simple parse) rather than server-side. Simpler, sends less PII to the server, and the user can edit immediately after registration.
2. **Minor**: Probe approach chosen over adding a `/api/config` endpoint — keeps the backend scope small and aligns with the "no backend code in frontend phase" workflow constraint.

## Known Issues / Technical Debt

- The build bundle warning for >500KB chunks is project-wide (not from this feature). Could be split later with `manualChunks`.

## Transition

- Gate Status: S4 PASSED (backend + frontend both complete)
- Next Step: S5 reviews (S5-CODE → S5-SEC → S5-TEST → S5-UX)
- Required Artifacts: 02-prd.md, 03-technical-design.md, 04-implementation-notes.md
