# Technical Design — FEAT-EPIC1-AUTH

## Architecture

JWT-based stateless auth via `tymon/jwt-auth`. Three services: `AuthService`, `RegistrationService`, `AccountDeletionService`.

## Data Flow

```
Register:
  POST /api/auth/register { token, name, email, password }
  → RegistrationService::register()
    → Consume WaitlistEntry (atomic, prevents double-use)
    → Create User (email_verified_at = null, jwt_version = 0)
    → Queue VerificationMail
  → return 201

Email verification:
  GET /api/auth/verify-email/{id}/{hash}  (signed URL, 60min)
  → Set email_verified_at, issue JWT

Login:
  POST /api/auth/login { email, password, remember }
  → AuthService::login()
    → Check lockout (LoginAttempt count in last 15min ≥ 5)
    → Hash::check()
    → Check email_verified_at, is_active
    → Record success (clear attempts) or failure
    → JWTAuth::attempt() + TTL = 15min (or 30d if remember)
  → return { token, user }

JWT validation (all protected routes):
  JwtVersionCheck middleware:
    → JWTAuth::parseToken()->authenticate()
    → Compare token.jwt_version claim == user.jwt_version in DB
    → 401 TOKEN_INVALIDATED if mismatch

Password reset:
  POST /api/auth/forgot-password
  → Hash + store in password_reset_tokens (1hr TTL)
  → Queue ResetMail

  POST /api/auth/reset-password { token, email, password }
  → Verify token, single-use (delete after verify)
  → Update password + increment jwt_version (invalidates all sessions)

Account deletion:
  POST /api/auth/delete-account { password }
  → Verify password
  → Set deleted_at (soft delete) + scheduled_hard_delete_at (now + 30d)
  → Increment jwt_version
  → Create AccountDeletionLog { hashed_user_id, reason }
  → Queue DeletionMail
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| `jwt_version` column | Instant invalidation without token blacklist DB overhead |
| Lockout per-email (not per-IP) | Prevents enumeration via IP-based targeting |
| Hard-delete batch (console command) | GDPR compliance; runs daily via scheduler |
| Audit log uses bcrypt hash of user_id | No PII in audit table; still uniquely correlatable |

## Gotchas

- "Remember me" TTL was calculated but NOT applied → fixed via `JWTAuth::factory()->setTTL(remember ? 43200 : 15)`
- `CheckIfAdmin` middleware was a boilerplate stub (always true) — pre-existing issue; not in this epic's scope
- Tokens in JSON response body (not httpOnly cookies) — deferred decision; frontend handles storage
