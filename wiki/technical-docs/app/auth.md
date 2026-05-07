# Technical Docs — Auth & JWT

**Keywords:** login, register, JWT, password, WebAuthn, biometric, lockout, invitation

## Overview

Superlistia uses JWT-based stateless authentication. Two auth paths: email+password and WebAuthn passkeys. All protected routes require `auth:api` + `JwtVersionCheck` middleware.

## JWT Lifecycle

```
Issue:   JWTAuth::fromUser(user) → access token (15min) + refresh token (30d if remember)
Refresh: POST /api/auth/refresh → new token pair
Invalidate:
  - Single logout: JWTAuth::invalidate()
  - All sessions: UPDATE users SET jwt_version = jwt_version + 1
    → JwtVersionCheck detects mismatch → 401 TOKEN_INVALIDATED
```

## Registration Flow

1. Admin generates invitation token for `WaitlistEntry`
2. User opens invitation URL → `GET /api/auth/invitation/{token}` validates
3. `POST /api/auth/register` → consumes token + creates unverified user
4. Email with signed verification link sent
5. `GET /api/auth/verify-email/{id}/{hash}` → sets `email_verified_at` + issues JWT

## Login Flow

```
POST /api/auth/login { email, password, remember? }
1. Check LoginAttempt count (last 15min) → 429 if ≥5
2. Hash::check(password, user.password)
3. Check email_verified_at (must not be null)
4. Check is_active (must be true)
5. Clear LoginAttempts on success; record attempt on failure
6. JWTAuth::attempt() with TTL: 15min (normal) or 30d (remember)
7. Return { token, user }
```

## Password Reset Flow

```
POST /api/auth/forgot-password { email }
→ Generate token, store in password_reset_tokens (1hr TTL)
→ Queue ResetMail

POST /api/auth/reset-password { token, email, password }
→ Verify token (single-use: delete after verify)
→ UPDATE password + increment jwt_version
→ Revoke all WebAuthn credentials (trust rotation)
```

## Account Deletion

```
POST /api/auth/delete-account { password }
→ Verify password
→ SET deleted_at = now(), scheduled_hard_delete_at = now() + 30d
→ increment jwt_version (all tokens invalidated)
→ INSERT account_deletion_logs { hashed_user_id: bcrypt(user_id), reason }
→ Queue DeletionMail
→ Console command accounts:delete-expired runs daily → permanent DELETE
```

## WebAuthn Authentication

```
Phase 1: POST /api/auth/webauthn/authenticate/begin { email? }
→ Generate challenge (UUID keyed, 5min cache)
→ Return PublicKeyCredentialRequestOptions
→ If email provided: allowCredentials = user's credential IDs
→ If no email: allowCredentials = [] (discoverable / passkey flow)

Phase 2: POST /api/auth/webauthn/authenticate/complete { credential }
→ Verify signature with stored public_key
→ Verify sign_count > stored (cloning detection)
→ UPDATE sign_count, last_used_at
→ Issue JWT (same as password login)
```

## Security Notes

- Lockout is per-email (not per-IP) → prevents enumeration via IP variation
- Duplicate email registration returns same message (no enumeration)
- `is_active` check after password validation (prevents timing oracle for deactivated accounts)
- RP ID must match exactly: `superlistia.com` (prod), `superia.com.local` (dev)
