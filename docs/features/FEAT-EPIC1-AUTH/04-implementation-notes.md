# Backend Implementation Notes: FEAT-EPIC1-AUTH

## Summary

Implemented JWT-based authentication system using `tymon/jwt-auth` v2.3.0 on Laravel 12. Covers registration via invitation token, login with account lockout, password reset, profile management, and RGPD-compliant account deletion.

## Files Changed

| File | Type | Description | Tests |
|------|------|-------------|-------|
| app/Models/User.php | Modified | Added JWTSubject, SoftDeletes, jwt_version, new fillable fields | All auth tests |
| app/Models/LoginAttempt.php | Created | Login attempt tracking model | AuthServiceTest |
| app/Models/AccountDeletionLog.php | Created | RGPD audit log model (no PII) | AccountDeletionServiceTest |
| app/Services/AuthService.php | Created | Login, logout, refresh, lockout logic | AuthServiceTest |
| app/Services/RegistrationService.php | Created | Register, verify email, token validation | RegistrationServiceTest |
| app/Services/AccountDeletionService.php | Created | Soft delete, hard delete batch, audit log | AccountDeletionServiceTest |
| app/Http/Controllers/Auth/RegisterController.php | Created | Token validation, register, verify email | RegisterTest |
| app/Http/Controllers/Auth/LoginController.php | Created | Login, logout, refresh | LoginTest |
| app/Http/Controllers/Auth/PasswordResetController.php | Created | Forgot password, reset password | PasswordResetTest |
| app/Http/Controllers/Auth/ProfileController.php | Created | Show, update, change password | ProfileTest |
| app/Http/Controllers/Auth/AccountDeletionController.php | Created | Account deletion initiation | AccountDeletionTest |
| app/Http/Requests/Auth/*.php | Created | 7 FormRequest classes for validation | Via controller tests |
| app/Http/Middleware/JwtVersionCheck.php | Created | Validates jwt_version claim matches DB | JwtVersionCheckTest |
| app/Mail/VerificationMail.php | Created | Email verification mailable | RegisterTest |
| app/Mail/PasswordResetMail.php | Created | Password reset mailable | PasswordResetTest |
| app/Mail/AccountDeletionMail.php | Created | Account deletion confirmation | AccountDeletionTest |
| app/Console/Commands/DeleteExpiredAccountsCommand.php | Created | Hard-delete batch for RGPD compliance | AccountDeletionServiceTest |
| routes/api.php | Created | All auth API endpoints | All feature tests |
| routes/web.php | Modified | Moved API routes to api.php, SPA catch-all only | - |
| routes/console.php | Modified | Added daily schedule for accounts:delete-expired | - |
| bootstrap/app.php | Modified | Added api.php routing, JSON exception handler for auth errors | - |
| config/auth.php | Modified | Added JWT api guard | - |
| config/jwt.php | Modified | TTL 15min, refresh 43200min (30 days) | - |
| .env.example | Modified | Added JWT_SECRET, JWT_TTL, Mailtrap config | - |
| resources/views/emails/verification.blade.php | Created | Verification email template | - |
| resources/views/emails/password-reset.blade.php | Created | Password reset email template | - |
| resources/views/emails/account-deletion.blade.php | Created | Account deletion email template | - |

## Migrations

| Migration | Description | Reversible |
|-----------|-------------|------------|
| add_auth_fields_to_users_table | jwt_version, privacy_accepted_at, scheduled_hard_delete_at, soft deletes | Yes |
| create_login_attempts_table | Login attempt tracking with composite index (email, attempted_at) | Yes |
| create_account_deletion_logs_table | RGPD audit log (hashed_user_id, no PII) | Yes |

## API Contract (Backend -> Frontend)

### Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | /api/auth/invitation/{token} | No | Validate invitation, returns email + name |
| POST | /api/auth/register | No | Register with token, name, password, privacy |
| GET | /api/auth/verify-email/{id}/{hash} | No (signed) | Verify email via signed URL |
| POST | /api/auth/login | No | Login, returns JWT token + user |
| POST | /api/auth/logout | JWT | Invalidate token |
| POST | /api/auth/refresh | JWT | Refresh access token |
| POST | /api/auth/forgot-password | No | Request password reset email |
| POST | /api/auth/reset-password | No | Reset password with token |
| GET | /api/profile | JWT | Get current user profile |
| PUT | /api/profile | JWT | Update name |
| PUT | /api/profile/password | JWT | Change password (requires current) |
| POST | /api/auth/delete-account | JWT | Delete account (requires password) |

### Response Format

```json
// Success
{ "data": { "message": "...", ... } }

// Error
{ "error": { "code": "ERROR_CODE", "message": "..." } }
```

### Error Codes

| Code | HTTP Status | Meaning | Frontend Action |
|------|-------------|---------|-----------------|
| INVALID_TOKEN | 404 | Invalid/expired invitation token | Show error, link to landing |
| REGISTRATION_FAILED | 422 | Token invalid or email taken | Show error message |
| INVALID_SIGNATURE | 403 | Invalid email verification link | Show error |
| INVALID_CREDENTIALS | 401 | Wrong email/password | Show generic error |
| ACCOUNT_LOCKED | 429 | 5+ failed login attempts | Show lockout message with timer |
| EMAIL_NOT_VERIFIED | 401 | Email not yet verified | Show verification prompt |
| TOKEN_INVALIDATED | 401 | JWT version mismatch | Redirect to login |
| TOKEN_REFRESH_FAILED | 401 | Refresh token expired | Redirect to login |
| RESET_FAILED | 422 | Invalid/expired reset token | Show error, link to forgot |
| INVALID_PASSWORD | 422 | Wrong current password | Show field error |
| DELETION_FAILED | 422 | Wrong password on delete | Show field error |
| UNAUTHORIZED | 401 | No/invalid auth token | Redirect to login |

## Tests Added

| Test File | Type | Count | Coverage |
|-----------|------|-------|----------|
| tests/Feature/Auth/RegisterTest.php | Feature | 15 tests | Registration + email verification endpoints |
| tests/Feature/Auth/LoginTest.php | Feature | 14 tests | Login, logout, refresh, lockout endpoints |
| tests/Feature/Auth/PasswordResetTest.php | Feature | 10 tests | Forgot + reset password endpoints |
| tests/Feature/Auth/ProfileTest.php | Feature | 11 tests | Profile show, update, change password endpoints |
| tests/Feature/Auth/AccountDeletionTest.php | Feature | 8 tests | Account deletion endpoint + audit log |
| tests/Unit/Services/AuthServiceTest.php | Unit | 8 tests | Login logic, lockout detection |
| tests/Unit/Services/RegistrationServiceTest.php | Unit | 10 tests | Registration, token validation, verify |
| tests/Unit/Services/AccountDeletionServiceTest.php | Unit | 9 tests | Soft delete, hard delete batch, audit |
| tests/Unit/Middleware/JwtVersionCheckTest.php | Unit | 3 tests | JWT version validation middleware |

**Total: 90 tests, 177 assertions, all passing.**
**Full test suite: 121 tests, 255 assertions, all passing (no regressions).**

## Implementation Decisions

1. **Routes in api.php**: Moved API routes from web.php to dedicated api.php to avoid CSRF middleware and follow Laravel conventions. web.php now only has the SPA catch-all.
2. **Password reset**: Uses Laravel's built-in Password broker with custom URL callback pointing to SPA route `/reset-password?token=...&email=...`.
3. **JWT token delivery**: Returns token in JSON response body (not cookies). The frontend implementation step will decide cookie vs localStorage strategy.
4. **jwt_version invalidation**: Embedded in JWT custom claims. Middleware checks on every authenticated request. Incremented on password change, reset, and account deletion.

## Deviations from Design

1. **Token storage**: Design specified httpOnly cookies. Implementation returns token in JSON body — cookie setting will be handled in frontend step as it depends on SPA architecture decisions (Axios interceptors, cookie domain config).
2. **Separate api.php**: Design suggested keeping routes in web.php. Changed to api.php for proper middleware group separation (no CSRF on API routes).

## Known Issues / Technical Debt

None.
