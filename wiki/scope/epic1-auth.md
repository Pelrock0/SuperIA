# FEAT-EPIC1-AUTH — Authentication & User Management

**Complexity:** HIGH (40-50h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-101 | Register via invitation token, email verification, JWT (15min/30d) | Implemented |
| HU-102 | Login: email+password, lockout (5 attempts/15min), "remember me" (30d) | Implemented |
| HU-103 | Password recovery: 1-hour token, single-use, invalidates all sessions | Implemented |
| HU-104 | Profile: edit name, change password (requires current password) | Implemented |
| HU-105 | Account deletion: soft delete + hard delete batch (30d), audit log (no PII) | Implemented |

## Complexity Classification

- Auth logic: HIGH — JWT version invalidation, lockout, invitation flow
- Account deletion: MEDIUM — soft delete, scheduled hard delete, audit log
- Security: HIGH — timing attacks, enumeration prevention, session invalidation

## Key Dependencies

- `tymon/jwt-auth` v2.3.0
- `WaitlistEntry` invitation token (from Epic 0)
- Mailtrap/Resend for email delivery

## Design Decisions

- `jwt_version` column on users — increment on password change/reset → instant invalidation of all tokens
- Account lockout per-email (not per-IP) → prevents user enumeration via IP targeting
- Hard-delete scheduled batch (console command, runs daily)
- Audit log stores hashed `user_id` (bcrypt), no PII
- httpOnly cookie JWT strategy deferred → tokens in JSON body for this epic

## Deviations

- Tokens in JSON body (not httpOnly cookies) — migration to cookies deferred to frontend integration

## Review Findings

- "Remember me" TTL was calculated but not applied to JWT → fixed via `JWTAuth::factory()->setTTL()`
- Rate limiting added to refresh, password change, and account deletion endpoints
- 121 backend + 55 frontend tests all passing
