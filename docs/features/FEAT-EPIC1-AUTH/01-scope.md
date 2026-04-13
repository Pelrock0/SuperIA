# Scope Analysis: FEAT-EPIC1-AUTH

## Feature Request

Epic 1 — Autenticacion y Gestion de Usuarios. Covers 5 user stories:

- **HU-101**: Registrarse con invitacion (enlace unico con expiracion 7 dias, email verificacion, JWT auth, bcrypt cost 12)
- **HU-102**: Iniciar sesion (email+password, bloqueo tras 5 intentos/15min, "Recuerdame" 30 dias)
- **HU-103**: Recuperar contrasena (enlace 1 hora, un solo uso, invalidar sesiones previas)
- **HU-104**: Ver y editar perfil (nombre, cambio contrasena con actual requerida)
- **HU-105**: Eliminar cuenta y datos RGPD (soft delete + hard delete batch 30 dias, transferencia listas compartidas, log auditoria sin PII)

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 40-50 hours |
| Confidence | High |

## Justification

HIGH because:
1. **Security-critical**: JWT authentication (access 15min + refresh 30 days), bcrypt, account lockout, password reset tokens
2. **RGPD compliance**: Right to erasure with audit trail, data deletion pipeline, soft/hard delete lifecycle
3. **External integrations**: Email sending (verification, invitation, password reset, deletion confirmation)
4. **Database migrations**: Multiple new tables/columns — invitation tokens, password reset tokens, login attempts, account deletion logs
5. **Cross-system impact**: Auth system touches every future feature (middleware, guards, policies)
6. **Architectural decisions**: JWT package selection (tymon/jwt-auth vs laravel-sanctum+SPA), refresh token strategy, email queue system

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | High | No JWT package installed. Must choose and integrate JWT auth (tymon/jwt-auth or Sanctum). Refresh token rotation strategy needed. Current User model uses standard Laravel auth, needs JWT traits/interfaces. |
| Data | High | New migrations: invitation_tokens, login_attempts, account_deletion_logs. Soft delete + hard delete batch job. Data integrity on cascade deletion (listas compartidas ownership transfer). |
| Security | High | Password hashing (bcrypt cost 12), account lockout mechanism, token expiration enforcement, HMAC invitation links, password reset single-use tokens, generic error messages to prevent user enumeration. |
| Performance | Medium | Login attempt tracking per IP/user. Password reset rate limiting. Batch deletion job impact on DB. Index strategy for token lookups. |
| Operational | Medium | Email delivery reliability (verification, invitation, reset, deletion confirmation). Batch deletion cron job. Monitoring for failed login spikes (brute force detection). |

## Affected Areas

- **app/Models/User.php** — JWT interface, soft deletes, new relations
- **app/Models/** — New models: InvitationToken, LoginAttempt, AccountDeletionLog
- **app/Http/Controllers/Auth/** — New: RegisterController, LoginController, PasswordResetController, ProfileController, AccountDeletionController
- **app/Http/Requests/** — New FormRequests for each endpoint
- **app/Http/Middleware/** — JWT auth middleware, account lockout middleware
- **app/Services/** — AuthService, InvitationService, AccountDeletionService
- **app/Mail/** — VerificationEmail, InvitationEmail, PasswordResetEmail, AccountDeletionEmail
- **database/migrations/** — Multiple new migrations
- **routes/api.php** — All auth API endpoints (needs creation)
- **config/jwt.php** — JWT configuration
- **resources/js/** — React pages: RegisterPage, LoginPage, ProfilePage + routing
- **tests/** — Full coverage for all auth flows

## Resolved Questions

1. **JWT package**: `tymon/jwt-auth` — confirmed by stakeholder.
2. **Email provider**: Mailtrap. Credentials in `.env`.
3. **Invitation flow**: HU-004 already fully implemented in FEAT-EPIC0-LANDING (Backpack CRUD, HMAC tokens, queued invitation emails, CSV export). This epic only handles the registration side (consuming the invitation token).
4. **Frontend routing**: React Router v7.14.0 already configured. Existing routes: `/` (Landing), `/privacy` (Privacy). New auth routes will extend existing setup.
5. **Shared list ownership transfer** (HU-105): If sole owner, delete list. If shared, transfer ownership to oldest collaborator.

## Open Questions

None. All TBDs resolved.

## Recommendation

- [ ] Proceed directly (LOW -> STEP 1b)
- [x] Require PRD (MEDIUM/HIGH -> STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)
