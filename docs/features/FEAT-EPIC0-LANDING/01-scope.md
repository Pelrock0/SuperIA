# Scope Analysis: FEAT-EPIC0-LANDING

## Feature Request
Épica 0 — Landing y Lista de Espera. Primera presencia pública de Superia. Incluye:
- **HU-001**: Landing page con propuesta de valor, responsive, sin analytics/cookies
- **HU-002**: Formulario de lista de espera (nombre, email, pregunta opcional), email confirmación con posición
- **HU-003**: Política de privacidad y compromiso de datos (RGPD)
- **HU-004**: Panel admin para gestionar waitlist (tabla, exportar CSV, invitar usuarios, enlace con expiración 7d)

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | MEDIUM |
| Estimated Effort | 20-28 hours |
| Confidence | High |

## Justification
- HU-001: Landing estática con React → LOW por sí sola
- HU-002: Requiere backend (endpoint, rate limiting, envío email, lógica de posición) + migración BD → MEDIUM
- HU-003: Página estática con contenido legal → LOW
- HU-004: Panel admin con CRUD, exportación CSV, sistema de invitaciones con token expirable, envío email → MEDIUM
- **Combinado**: múltiples migraciones BD, envío de emails transaccionales, rate limiting, sistema de tokens de invitación, rol superadmin → MEDIUM

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | Stack estándar Laravel+React. Rate limiting con middleware Laravel. Emails con Laravel Mail. |
| Data | Medium | Nuevas tablas: `waitlist_entries`, `invitations`. Migraciones necesarias. Datos personales (email, nombre) bajo RGPD. |
| Security | Medium | Rate limiting en waitlist (3 intentos/IP/hora). Tokens de invitación con HMAC-SHA256 y expiración. No revelar si email existe. Ruta admin solo superadmin. |
| Performance | Low | Volumen bajo esperado en fase waitlist. Landing debe cargar <2s. |
| Operational | Low | Deploy estándar. Requiere configurar servicio de email (SMTP). |

## Affected Areas
- **Backend**: Nuevos modelos (`WaitlistEntry`, `Invitation`), controladores, servicios, migraciones, middleware rate limiting
- **Frontend React**: Componentes `LandingPage`, `WaitlistForm`, páginas legales
- **Admin**: Ruta protegida `/admin` con gestión waitlist
- **Email**: Templates de confirmación waitlist + invitación de acceso
- **Base de datos**: 2 nuevas tablas mínimo

## Recommendation
- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

## Open Questions
- Ninguna. Requisitos claros en HU v3.

## Transition
- Gate: S1 PASSED
- Next Step: STEP 2 (PRD Writing)
