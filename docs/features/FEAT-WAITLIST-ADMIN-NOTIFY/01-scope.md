# Scope Analysis: FEAT-WAITLIST-ADMIN-NOTIFY

## Feature Request
Cuando un usuario se apunta a la lista de espera, enviar un email a todos los usuarios con rol `admin` o `superadmin` notificándoles del nuevo registro, con el formato visual de la aplicación.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | MEDIUM |
| Estimated Effort | 3 hours |
| Confidence | High |

## Justification
- Modifica lógica de negocio existente (`WaitlistService::register()`)
- Introduce nueva clase Mail + Blade template
- Requiere query de usuarios por rol (Spatie Permission)
- Patrón ya existente en el proyecto (`BudgetCapExceededAlert`) — riesgo bajo

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | Patrón de admin notification ya existe (`BudgetCapExceededAlert`). Usar `User::role(['admin','superadmin'])->get()` con Spatie Permission. |
| Data | Low | No hay cambios de schema. Lectura de datos existentes (roles, waitlist entry). |
| Security | Low | El email expone nombre y email del solicitante a admins autorizados. Solo se envía a roles `admin`/`superadmin` — no hay exposición externa. |
| Performance | Low | Query de admins es puntual y de bajo volumen. Usar `chunkById` si la base de admins crece. Email va a cola (queued). |
| Operational | Low | Si no hay admins registrados, el flujo continúa sin error. Añadir log de warning si `$admins->isEmpty()`. |

## Affected Areas
- `app/Services/WaitlistService.php` — añadir dispatch de notificación post-registro
- `app/Mail/AdminWaitlistNotificationMail.php` — nueva clase Mail (queued)
- `resources/views/emails/admin-waitlist-notification.blade.php` — nueva Blade template con estilo de la app
- `tests/Feature/Services/WaitlistServiceTest.php` — ampliar con casos de notificación admin

## Recommendation
- [x] Require PRD (MEDIUM → STEP 2)

## Open Questions
Ninguna. Todos los requisitos están claros:
- Roles objetivo: `admin` y `superadmin`
- Trigger: `WaitlistService::register()` tras crear la entrada
- Contenido: nombre + email del solicitante, posición en lista
- Estilo: templates Blade existentes del proyecto

## Transition
- Gate: S1
- Next Step: STEP 2 (PRD)
