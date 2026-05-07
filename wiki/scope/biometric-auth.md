# FEAT-BIOMETRIC-AUTH — WebAuthn / Passkeys

**Complexity:** HIGH | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-B1 | Register biometric credential from profile settings | Implemented |
| HU-B2 | Login with email + biometrics (non-discoverable flow) | Implemented |
| HU-B3 | Login with passkey alone (discoverable credentials) | Implemented |
| HU-B4 | Revoke individual credentials | Implemented |
| HU-B5 | Rename credentials | Implemented |
| HU-B6 | Password reset revokes all passkeys (trust rotation) | Implemented |

## Key Dependencies

- `web-auth/webauthn-lib` ^5.2 (Symfony-maintained)
- Laravel challenge cache
- `webauthn_credentials` table (Eloquent model)
- Feature flag: `config('webauthn.enabled')`

## Design Decisions

- RP ID: `superlistia.com` (prod) / `superia.com.local` (dev)
- User verification: preferred (not required)
- Attestation: none
- Discoverable credentials supported (passkey-style login)
- Challenge: UUID handle (stateless, no session dependency)
- `credential_id`: base64url VARCHAR(512) (MySQL BLOB limitation workaround)

## Deviations

- JWT inline in WebAuthnController (not extracted to separate service — tech debt)
- UUID handle for challenge instead of session ID

## Review Findings

- Replay attacks mitigated: single-use challenges
- Credential cloning detected via `signCount` validation
- Anti-enumeration on `begin-auth` endpoint
- 3 throttle additions during security review
- 664 backend + 298 frontend tests all passing
