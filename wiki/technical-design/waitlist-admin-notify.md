# Technical Design — FEAT-WAITLIST-ADMIN-NOTIFY

Notificación por email a todos los admins tras cada nuevo registro de waitlist. Dispatch **fuera** de la transacción DB (fallo de cola no revierte registro). Nueva clase `AdminWaitlistNotificationMail` queued + Blade template. Sin migraciones, sin cambios de API.

## Arquitectura

| Capa | Responsabilidad | Módulo |
|------|----------------|--------|
| Service | Orquestar registro + dispatch admin notify | `WaitlistService::register()` |
| Mail | Encapsular datos + template (recibe primitivos, no Eloquent) | `App\Mail\AdminWaitlistNotificationMail` (nuevo) |
| View | Renderizar email | `emails.admin-waitlist-notification.blade.php` (nuevo, extiende `emails.layout`) |
| Controller | Sin cambios | `WaitlistController` |
| Frontend | Sin cambios | — |

## Flujo

```
POST /api/waitlist
  → WaitlistController::store()
  → WaitlistService::register()
      ├── [DB Transaction]
      │     └── WaitlistEntry::create()
      ├── Mail::to($entry->email)->queue(WaitlistConfirmationMail)   ← existente
      └── [foreach admin]
            Mail::to($admin->email)->queue(AdminWaitlistNotificationMail)   ← nuevo
  ← return ['message', 'position']
```

## Decisiones de diseño

| Opción | Decisión | Razón |
|--------|----------|-------|
| Loop individual por admin | **Seleccionada** | Privacidad — `Mail::to($admins)` pondría todos en `To:` exponiendo emails entre admins |
| Spatie `User::role(['admin', 'superadmin'])->get()` | **Seleccionada** | Query única, sin N+1 |
| Mail primitivos (string, string, int) | **Seleccionada** | Evita serialización Eloquent en queue payload |
| `implements ShouldQueue` | **Seleccionada** | No bloquea HTTP request |
| Dispatch fuera de transacción DB | **Seleccionada** | Fallo de cola no debe revertir registro (side effect) |
| Laravel Notification (multi-canal) | Rechazada | YAGNI |
| Env var `ADMIN_NOTIFY_EMAIL` único | Rechazada | No escala a múltiples admins |

## Seguridad

- Solo `admin`/`superadmin` reciben notificación
- Emails enviados **individualmente** — no se exponen emails de admins entre sí
- PII expuesta: nombre + email del solicitante (destinatarios autorizados)

## Gotchas

- **Roles futuros**: si se añade rol admin que no sea `admin`/`superadmin`, no recibe notificación. Documentado como deuda.
- **Sin admins en DB**: `Log::warning('AdminWaitlistNotify: no admin users found')` — no excepción.
- **Worker caído**: registro ya completó; emails se procesan al reanudar.

Origen: `docs/features/FEAT-WAITLIST-ADMIN-NOTIFY/03-technical-design.md`.
