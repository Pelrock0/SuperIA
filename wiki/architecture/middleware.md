# Architecture — Middleware

## Custom Middleware (`app/Http/Middleware/`)

| Middleware | Routes Protected | Purpose |
|-----------|-----------------|---------|
| `CheckIfAdmin` | `/admin/*`, `/telescope` | Validates user has `admin` or `superadmin` Spatie role; JSON 401 for API, redirect for web |
| `EnsureWebauthnEnabled` | `/api/auth/webauthn/*` | Returns 404 if `config('webauthn.enabled')` is false; feature flag gate |
| `JwtVersionCheck` | All `auth:api` routes | Parses JWT claim `jwt_version`, compares to `users.jwt_version` in DB; 401 TOKEN_INVALIDATED on mismatch |
| `ValidateShareToken` | `/api/shared/*` | Resolves share token from URL param, verifies HMAC signature (constant-time), checks revoked_at; 410 on any failure; attaches `ShareTokenContext` to request |
| `ValidateShareToken:write` | `/api/shared/*/items` (mutations) | Same as above + enforces mode='write'; 403 if read-only token |

## Framework Middleware Applied

| Middleware | Applied To | Purpose |
|-----------|------------|---------|
| `auth:api` (JWT) | All authenticated API routes | Validates JWT and sets auth user |
| `throttle:N,M` | Various API routes | Rate limiting (N requests per M minutes) |
| `signed` | `/unsubscribe/weekly-summary/{user}` | Validates Laravel signed URL (30-day TTL) |

## Rate Limit Configuration

| Route Group | Limit | Window |
|-------------|-------|--------|
| `POST /waitlist` | 3 | 60 min (per IP) |
| `POST /auth/register` | 5 | 60 min |
| `POST /auth/login` | 10 | 60 min |
| `POST /auth/forgot-password` | 3 | 60 min |
| `POST /auth/reset-password` | 5 | 60 min |
| `POST /auth/delete-account` | 3 | 60 min |
| `POST /auth/refresh` | 30 | 1 min |
| `PUT /profile/password` | 5 | 60 min |
| `POST /lists/{list}/share` | 10 | 60 min |
| WebAuthn endpoints | 20 | 1 min |
| `GET /suggestions`, `GET /complements` | 60 | 1 min |
| `/api/shared/*` (anonymous) | 60 | 1 min |
| Most authenticated reads | 60 | 1 min |
