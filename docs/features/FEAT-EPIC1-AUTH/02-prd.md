# PRD: FEAT-EPIC1-AUTH - Autenticacion y Gestion de Usuarios

## Business Objective

Superia needs a complete authentication and user management system to transition from a public landing page (Epic 0) to a functional product. Users who receive invitations from the waitlist must be able to register, log in, manage their profile, and exercise their RGPD rights. Without this epic, no authenticated feature (lists, IA, collaboration) can be built.

## Problem Statement

Currently, Superia has a public landing page and a waitlist system (Epic 0). Invited users receive an email with a registration token but have no way to create an account, log in, or access the product. There is no authentication layer, no user session management, and no RGPD-compliant data deletion mechanism.

## Scope

### In Scope

- **HU-101**: Registration via invitation token (consume existing HMAC tokens from HU-004)
- **HU-102**: Login with email/password, account lockout, "remember me"
- **HU-103**: Password recovery via email with single-use token
- **HU-104**: View and edit user profile (name, password change)
- **HU-105**: Account deletion with RGPD compliance (soft delete, hard delete batch, ownership transfer)
- JWT authentication with `tymon/jwt-auth` (access token 15min, refresh token 30 days)
- Email notifications via Mailtrap: verification, password reset, account deletion confirmation
- API endpoints under `/api/auth/*` and `/api/profile/*`
- React pages: RegisterPage, LoginPage, ProfilePage
- Database migrations for login_attempts, account_deletion_logs, User model updates

### Out of Scope

- Invitation creation/management (already implemented in HU-004/FEAT-EPIC0-LANDING)
- Social login (Google, Apple, Facebook)
- Two-factor authentication (2FA)
- Admin user management panel (Epic 10 — HU-1002)
- Role/permission management beyond basic user vs superadmin
- Password strength meter UI component
- Session management across multiple devices (single device per token is sufficient)
- Email template design/branding (functional plain templates are sufficient)

## Acceptance Criteria

### AC-1: Register with valid invitation token (HU-101)
- **Given**: A user has received an invitation email with a valid, non-expired token
- **When**: They navigate to `/register?token={invitation_token}`
- **Then**: The registration form displays with email pre-filled (read-only), fields for name, password (min 8 chars, 1 uppercase, 1 number), password confirmation, and mandatory privacy policy checkbox

### AC-2: Register — token validation
- **Given**: A user navigates to `/register?token={token}`
- **When**: The token is expired (>7 days) or invalid
- **Then**: The system shows a clear message explaining the token is invalid/expired and offers a link back to the landing page waitlist

### AC-3: Register — email verification
- **Given**: A user completes the registration form with valid data
- **When**: They submit the form
- **Then**: The account is created in inactive state, a verification email is sent, and the user sees a message instructing them to check their email

### AC-4: Register — email verification completion
- **Given**: A user clicks the verification link in their email
- **When**: The link is valid and not expired
- **Then**: The account is activated, the user is redirected to the dashboard (`/app`) with a welcome message, and a valid JWT token pair is issued

### AC-5: Login — success (HU-102)
- **Given**: A registered user with a verified, active account
- **When**: They submit correct email and password at `/login`
- **Then**: The system returns a JWT access token (15min) and refresh token (30 days), and redirects to `/app`

### AC-6: Login — invalid credentials
- **Given**: A user at the login form
- **When**: They submit incorrect email or password
- **Then**: The system shows a generic message "Credenciales incorrectas" without specifying which field failed

### AC-7: Login — account lockout
- **Given**: A user has failed login 5 consecutive times
- **When**: They attempt a 6th login
- **Then**: The account is locked for 15 minutes with message "Cuenta bloqueada temporalmente. Intentalo de nuevo en 15 minutos." Failed attempts reset after successful login.

### AC-8: Login — remember me
- **Given**: A user checks "Recuerdame" during login
- **When**: They submit valid credentials
- **Then**: The refresh token is set to 30 days. Without "Recuerdame", the refresh token expires at browser session end.

### AC-9: Password recovery — request (HU-103)
- **Given**: A user at the password recovery form
- **When**: They submit any email address
- **Then**: The system always shows "Si el email esta registrado, recibiras un enlace de recuperacion" regardless of whether the email exists

### AC-10: Password recovery — reset
- **Given**: A user clicks a valid password reset link (valid 1 hour, single-use)
- **When**: They submit a new password (same rules as registration)
- **Then**: The password is updated, all existing sessions/tokens are invalidated, and the user is redirected to login

### AC-11: Password recovery — expired/used token
- **Given**: A user clicks a password reset link that is expired or already used
- **When**: The page loads
- **Then**: The system shows a clear message and a link to request a new reset

### AC-12: View profile (HU-104)
- **Given**: An authenticated user
- **When**: They navigate to their profile page
- **Then**: They see their name (editable), email (read-only), and option to change password

### AC-13: Edit profile — name
- **Given**: An authenticated user on the profile page
- **When**: They change their name and save
- **Then**: The name is updated and a success message is shown

### AC-14: Edit profile — change password
- **Given**: An authenticated user on the profile page
- **When**: They submit current password, new password, and confirmation
- **Then**: If current password is correct, the password is updated. If incorrect, an error is shown.

### AC-15: Delete account — initiation (HU-105)
- **Given**: An authenticated user on the profile page
- **When**: They click "Eliminar cuenta"
- **Then**: A confirmation dialog shows: "Esta accion eliminara todas tus listas, items e historial de forma permanente e irreversible." and requires current password to proceed.

### AC-16: Delete account — execution (sole owner)
- **Given**: A user confirms account deletion and owns lists that are NOT shared
- **When**: The deletion process executes
- **Then**: The account is soft-deleted, all owned non-shared lists are deleted, the user is logged out, and a confirmation email is sent. Hard delete batch runs after 30 days.

### AC-17: Delete account — execution (shared lists)
- **Given**: A user confirms account deletion and owns lists that ARE shared
- **When**: The deletion process executes
- **Then**: Shared list ownership transfers to the oldest collaborator. The rest of the process follows AC-16.

### AC-18: Delete account — audit log
- **Given**: An account deletion is processed
- **When**: The soft delete completes
- **Then**: An audit log entry is created with: deletion timestamp, anonymized user ID (hash), and reason. No PII is stored in the log.

### AC-19: JWT token refresh
- **Given**: An authenticated user with a valid refresh token
- **When**: Their access token expires
- **Then**: The frontend automatically calls `/api/auth/refresh` and receives a new access token without interrupting the user experience

### AC-20: JWT token — invalid/expired refresh
- **Given**: A user with an expired or invalid refresh token
- **When**: They attempt any authenticated action
- **Then**: The system returns 401, and the frontend redirects to `/login`

## UX Decision

- **UX Designer Required**: YES
- **UX Artifacts**: Stitch MCP screens exist for Register, Login, Profile. Will be consumed during S4 (implementation) and reviewed at S5-UX.
- **Screens involved**:
  - `RegisterPage` — Stitch screen "Registro" → `/register`
  - `LoginPage` — Stitch screen "Login" → `/login`
  - `ProfilePage` — Stitch screen "Perfil" → `/app/profile`
  - Password recovery form (no Stitch screen specified — minimal functional UI)
  - Account deletion confirmation dialog (inline in ProfilePage)

**Note**: UX wireframes will be needed at S5-UX. The frontend implementation at S4 will pull designs from Stitch MCP.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| `tymon/jwt-auth` compatibility with Laravel 12 | Technical | Verify package supports Laravel 12 before implementation. Fallback: fork or use `php-open-source-saver/jwt-auth` (maintained fork). |
| Email delivery failures block registration flow | Operational | Use Laravel queue for emails. Implement retry (3 attempts). Provide manual re-send verification option in UI. |
| Account lockout as DoS vector | Security | Lockout is per user account, not per IP. Attacker cannot lock out arbitrary accounts without knowing the email. Consider CAPTCHA for future improvement (out of scope). |
| Hard delete batch job failure | Operational | Log all batch operations. Alert on failure. Idempotent job design — can re-run safely. |
| Refresh token theft | Security | Refresh tokens stored httpOnly. Rotate on use. Invalidate all tokens on password change/account deletion. |
| RGPD audit log containing PII accidentally | Security | Log schema enforced at model level — only hashed user ID and timestamp. Code review gate (S5-SEC) must verify. |

## Assumptions

- Mailtrap is configured and credentials are in `.env`
- `tymon/jwt-auth` (or compatible fork) supports Laravel 12
- The existing `WaitlistEntry` model and invitation tokens from FEAT-EPIC0-LANDING are stable and will not change
- Lists and shared lists (Epic 2, 3, 4) do not exist yet — HU-105 ownership transfer logic will be implemented as a service that future epics will integrate with
- The React frontend uses Vite (confirmed from `vite.config.js`)
- No rate limiting middleware exists yet — will be created as part of this epic for auth endpoints

## Open Questions

None. All questions resolved in S1.

## Approval

- [ ] PRD approved by stakeholder on [date]

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
- Required Artifacts for Next Step: 02-prd.md
