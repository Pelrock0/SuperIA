# Backend Implementation Notes: FEAT-WAITLIST-ADMIN-NOTIFY

## Summary
Notificación por email a todos los admins (`admin`/`superadmin`) cuando un nuevo usuario se registra en la lista de espera. Dispatch asíncrono (queued) fuera de la transacción DB. Sin migraciones.

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `app/Mail/AdminWaitlistNotificationMail.php` | Created | Mailable queued con primitivos (name, email, position) |
| `resources/views/emails/admin-waitlist-notification.blade.php` | Created | Template Blade extendiendo `emails.layout` |
| `app/Services/WaitlistService.php` | Modified | Añadido `notifyAdmins()` y dispatch tras registro |
| `tests/Unit/Services/WaitlistServiceTest.php` | Modified | 5 nuevos tests para la notificación admin |

## Migrations
Ninguna.

## API Contract
Sin cambios en la API pública.

## Tests Added

| Test | Type | What it tests |
|------|------|---------------|
| `test_register_notifies_admin_on_new_entry` | Unit | Admin recibe email con datos correctos del solicitante |
| `test_register_notifies_all_admins_individually` | Unit | Múltiples admins reciben emails individuales |
| `test_register_does_not_notify_admins_on_duplicate_email` | Unit | No se notifica en registro duplicado |
| `test_register_logs_warning_when_no_admins_exist` | Unit | Warning en log cuando no hay admins |
| `test_register_completes_successfully_when_no_admins_exist` | Unit | Registro completa sin excepción si no hay admins |

## Test Coverage Report

| Component | Coverage |
|-----------|----------|
| `WaitlistService` | 100% |
| `AdminWaitlistNotificationMail` | 100% |
| **Total** | **100%** |

## Notes for Reviewers
- `notifyAdmins()` es privado — cubierto indirectamente via `register()`
- Dispatch fuera de `DB::transaction()` — intencional; side effect no debe revertir registro
- Primitivos en constructor del Mailable — evita serialización de Eloquent en cola
- Loop individual por admin — cada destinatario recibe email independiente (no se exponen emails entre admins)

## Implementation Decisions
- Elegido loop individual sobre `Mail::to($admins)` por privacidad (ver trade-offs en diseño técnico)
- `notifyAdmins()` extrae la lógica para mantener `register()` legible

## Known Issues / Technical Debt
- Si en el futuro se añaden nuevos roles admin (ej: `moderator`), hay que actualizar el array en `notifyAdmins()`. Documentado en diseño técnico como deuda explícita.
