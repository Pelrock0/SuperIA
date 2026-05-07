# Technical Design — FEAT-BIOMETRIC-AUTH

## Architecture

WebAuthn via `web-auth/webauthn-lib`. Two independent flows: registration (from profile) and authentication (from login page). `WebauthnService` wraps library complexities. Challenges are stateless (cached by UUID handle).

## Data Flow

```
Registration:
  POST /api/auth/webauthn/register/begin  (authenticated)
  → WebauthnService::createRegistrationChallenge(user)
    → Generate PublicKeyCredentialCreationOptions
    → Cache challenge keyed by UUID handle (5min TTL)
    → Return JSON challenge to frontend (browser passes to navigator.credentials.create())

  POST /api/auth/webauthn/register/complete { credential }
  → WebauthnService::validateAndStoreCredential(user, credential)
    → Verify attestation (none mode)
    → Verify challenge from cache
    → INSERT webauthn_credentials {
        user_id, credential_id (base64url), public_key, sign_count,
        transports, aaguid, attestation_type, name (device name), last_used_at
      }

Authentication (non-discoverable — email first):
  POST /api/auth/webauthn/authenticate/begin { email }
  → WebauthnService::createAuthenticationChallenge(user)
    → allowCredentials: user's credential_id list
    → Cache challenge by UUID
    → Return challenge

  POST /api/auth/webauthn/authenticate/complete { credential }
  → WebauthnService::validateAuthentication(credential)
    → Load credential from DB by credential_id
    → Verify signature with stored public_key
    → Verify sign_count > stored (prevents cloning)
    → UPDATE sign_count, last_used_at
    → Issue JWT (same as password login)

Authentication (discoverable — passkey):
  POST /api/auth/webauthn/authenticate/begin (no email)
  → allowCredentials: [] (browser discovers from device)
  → Same completion flow

Credential management:
  GET    /api/profile/webauthn-credentials → list
  PATCH  /api/profile/webauthn-credentials/{id} { name } → rename
  DELETE /api/profile/webauthn-credentials/{id} → revoke
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Attestation: none | Maximum device compatibility; attestation analysis is complex and out of scope |
| UUID handle for challenge (not session ID) | Stateless API; no PHP session required |
| VARCHAR(512) for credential_id | MySQL BLOB type causes issues with Eloquent; base64url fits in VARCHAR |
| Sign count validation | Detects credential cloning (FIDO2 requirement) |
| Password reset revokes all passkeys | Trust rotation: if password compromised, so might be registered devices |

## Gotchas

- RP ID must match exactly: `superlistia.com` in prod, `superia.com.local` in dev — mismatch causes all credential verifications to fail
- The `EnsureWebauthnEnabled` middleware returns 404 (not 403) when feature is disabled — intentional (feature doesn't exist when disabled)
- `sign_count` can be 0 for software authenticators (some password managers) — allowed but logged
