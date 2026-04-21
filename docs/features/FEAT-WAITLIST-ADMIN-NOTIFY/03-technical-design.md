# Technical Design: FEAT-WAITLIST-ADMIN-NOTIFY

## Overview
Se añade una notificación por email a todos los admins tras cada nuevo registro en la lista de espera. El disparo ocurre en `WaitlistService::register()`, fuera de la transacción DB, para que el fallo de envío no revierta el registro. Se introduce una nueva clase `AdminWaitlistNotificationMail` (queued) y una Blade template que extiende `emails.layout`.

No se requieren migraciones. No hay cambios en la API. El frontend no se modifica.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Services | Orquestar registro + dispatch notificación admin | `WaitlistService` |
| Infrastructure (Mail) | Encapsular datos del mailable + template | `AdminWaitlistNotificationMail` |
| Infrastructure (View) | Renderizar email con estilo de la app | `emails.admin-waitlist-notification` |
| Controllers/API | Sin cambios — delega a service | `WaitlistController` |
| Frontend | Sin cambios | N/A |

### Data Flow

```
POST /api/waitlist
  → WaitlistController::store()
  → WaitlistService::register()
      ├── [DB Transaction]
      │     └── WaitlistEntry::create()
      ├── Mail::to($entry->email)->queue(WaitlistConfirmationMail)   ← existente
      └── [foreach admin]
            Mail::to($admin)->queue(AdminWaitlistNotificationMail)   ← nuevo
  ← return ['message', 'position']
```

### Transaction Boundaries

- **Transacción DB**: `WaitlistEntry::create()` — sin cambios, permanece dentro del `DB::transaction()`
- **Dispatch de notificación admin**: FUERA de la transacción — es un side effect; un fallo de cola no debe revertir el registro
- **Idempotencia**: No aplicable — cada registro es único. El guard de duplicado existente (`if ($existing)`) ya previene doble envío

## Data Model

### New Tables/Collections
Ninguna.

### Migrations
Ninguna.

### API Changes
Ninguna.

## New Files

### `app/Mail/AdminWaitlistNotificationMail.php`
```php
class AdminWaitlistNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $applicantName,
        public readonly string $applicantEmail,
        public readonly int $position,
    ) {}

    public function envelope(): Envelope { ... }
    public function content(): Content { ... }
}
```
- Recibe primitivos (no el modelo) para evitar serialización de Eloquent en la cola
- Subject: `"Superlistia — Nuevo registro en lista de espera"`

### `resources/views/emails/admin-waitlist-notification.blade.php`
- Extiende `emails.layout`
- Muestra: nombre, email y posición del solicitante
- Mismo estilo visual que `waitlist-confirmation.blade.php`

### Modificación: `app/Services/WaitlistService.php`

Añadir tras el envío de `WaitlistConfirmationMail`:

```php
$admins = User::role(['admin', 'superadmin'])->get();

if ($admins->isEmpty()) {
    Log::warning('AdminWaitlistNotify: no admin users found to notify');
} else {
    foreach ($admins as $admin) {
        Mail::to($admin->email)->queue(
            new AdminWaitlistNotificationMail(
                $entry->name,
                $entry->email,
                $queuePosition,
            )
        );
    }
}
```

**Por qué loop individual y no `Mail::to($admins)`**: `Mail::to(collection)` pone todos los destinatarios en el campo `To:`, exponiendo los emails de los admins entre sí. El loop envía emails independientes.

## Performance

### Query Optimization
- `User::role(['admin', 'superadmin'])->get()` — query única con Spatie Permission; sin N+1
- El número de admins es bajo (< 10 típicamente) — no se requiere `chunkById`

### Caching Strategy
- No aplicable. La lista de admins no se cachea — cambios de rol deben reflejarse inmediatamente.

### Async Processing
- `AdminWaitlistNotificationMail implements ShouldQueue` — el dispatch no bloquea el request HTTP

## Security

- Solo usuarios con rol `admin`/`superadmin` reciben la notificación
- El email expone nombre y email del solicitante — es información PII, pero los destinatarios son admins autorizados
- Los emails se envían individualmente (no en copia) — no se exponen emails de admins entre sí

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Loop individual por admin | Privacidad (no expone emails entre admins), fallo aislado por destinatario | Más jobs en cola si hay muchos admins | **Selected** |
| `Mail::to($admins)->queue()` | Un solo job de cola | Expone todos los emails en el campo `To:` | Rejected: fuga de PII |
| Laravel Notification (`notifiable`) | Más extensible, soporta multi-canal | Overhead innecesario para este caso puntual | Rejected: YAGNI |
| Env var `ADMIN_NOTIFY_EMAIL` (como `BudgetCapExceededAlert`) | Simple | No escala a múltiples admins, hardcodea destinatario | Rejected: no cumple el requisito de notificar a todos los admins |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Admin sin email configurado (`email` null) | Medium | Low | `User::role()` retorna usuarios con email en `users.email`; el campo es `NOT NULL` en el schema |
| Cola no procesada (worker caído) | Low | Low | Side effect — el registro ya se completó. Emails se procesarán cuando el worker reanude |
| Nuevo rol admin añadido en el futuro que no sea `admin`/`superadmin` | Low | Low | Scope fuera de este feature. Documentado como deuda técnica explícita |

## Open Questions
Ninguna.

## Implementation Notes
- Usar `php artisan make:mail AdminWaitlistNotificationMail --markdown=` NO — usar `Content` con `view:` siguiendo el patrón de `BudgetCapExceededAlert`
- Tests en `WaitlistServiceTest` — añadir casos: admin notificado en registro nuevo, no notificado en duplicado, sin admins (warning sin excepción)
- No tocar la transacción DB existente

## Transition
- Gate Status: S3 PASSED
- Next Step: STEP 4 – Implementation
- Required Artifacts: 01-scope.md, 02-prd.md, 03-technical-design.md
