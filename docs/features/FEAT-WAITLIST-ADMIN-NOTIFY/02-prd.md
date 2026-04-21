# PRD: FEAT-WAITLIST-ADMIN-NOTIFY - Admin Waitlist Notification

## Business Objective
Los administradores no tienen visibilidad inmediata cuando alguien se apunta a la lista de espera. Actualmente deben entrar al panel de Backpack para ver nuevas entradas. Este feature entrega notificación proactiva por email a todos los admins en el momento del registro, permitiendo respuesta rápida sin depender del panel.

## Problem Statement
Los usuarios con rol `admin` o `superadmin` no reciben ninguna alerta cuando llega un nuevo registro en la lista de espera. Esto genera latencia operativa y dependencia de revisión manual del panel.

## Scope

### In Scope
- Enviar email de notificación a todos los usuarios con rol `admin` o `superadmin` cuando se complete un registro en la lista de espera
- El email incluye: nombre, email y posición en la lista del nuevo solicitante
- El email usa el estilo visual (Blade template) existente en la aplicación
- El envío es asíncrono (queued)
- Si no existen admins registrados, el flujo continúa sin error y se registra un warning en el log

### Out of Scope
- Configuración de notificaciones por admin (activar/desactivar)
- Notificaciones por otros canales (Slack, SMS, push)
- Notificación cuando un admin invita o registra a un usuario desde la lista
- Modificaciones al panel de administración
- Nuevas rutas o endpoints de API

## Acceptance Criteria

### AC-1: Email enviado a todos los admins en nuevo registro
- **Given**: Existen uno o más usuarios con rol `admin` o `superadmin`
- **When**: Un nuevo usuario completa el registro en la lista de espera
- **Then**: Cada admin recibe un email con nombre, email y posición del nuevo solicitante

### AC-2: Email con formato de la aplicación
- **Given**: Se dispara la notificación de nuevo registro
- **When**: El sistema genera el email
- **Then**: El email usa la Blade template con el estilo visual de la aplicación (logo, colores, footer)

### AC-3: Envío asíncrono (no bloquea el registro)
- **Given**: Un usuario se registra en la lista de espera
- **When**: El sistema procesa el registro
- **Then**: El email de notificación a admins se encola (queued) y el registro se completa sin esperar al envío

### AC-4: Sin admins registrados — flujo no falla
- **Given**: No existen usuarios con rol `admin` o `superadmin` en la base de datos
- **When**: Un nuevo usuario se registra en la lista de espera
- **Then**: El registro se completa correctamente, no se lanza excepción, y se registra un warning en el log

### AC-5: Email no enviado en registros fallidos
- **Given**: El registro en la lista de espera falla por validación u otro error
- **When**: `WaitlistService::register()` lanza una excepción
- **Then**: No se envía ningún email de notificación a admins

### AC-6: Email contiene datos correctos
- **Given**: Se envía la notificación de un nuevo registro
- **When**: El admin abre el email
- **Then**: El email muestra el nombre completo, email y número de posición del solicitante

## UX Decision
- **UX Designer Required**: No
- **UX Artifacts**: N/A
- **Basic UX Notes**: Email transaccional solo. Usa estilo Blade existente del proyecto. Sin cambios en UI web.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| N+1 query al cargar admins con sus roles | Performance | Usar `User::role(['admin', 'superadmin'])->get()` — query única con Spatie |
| Admin con email inválido bloquea el envío a los demás | Technical | Iterar admins individualmente con try/catch por destinatario o usar `Mail::to($admin)->queue()` en loop |
| Exposición de datos del solicitante fuera de admins autorizados | Security | Solo se envía a roles `admin`/`superadmin` — sin exposición externa |

## Assumptions
- Los roles `admin` y `superadmin` son los únicos con privilegios de administración (confirmado por `CheckIfAdmin` middleware)
- El sistema de colas está configurado y operativo en producción
- El mailer (`MAIL_MAILER`) está configurado en `.env`

## Open Questions
Ninguna.

## Approval
- [ ] PRD aprobado

## Transition
- Gate Status: S2 PASSED
- Next Step: STEP 3 – Technical Design
- Required Artifacts: 01-scope.md, 02-prd.md
