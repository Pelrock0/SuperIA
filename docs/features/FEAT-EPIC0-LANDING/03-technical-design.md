# Technical Design: FEAT-EPIC0-LANDING

## Overview
La Épica 0 establece la presencia pública de Superia con una landing page React servida por Laravel via Vite, un sistema de waitlist con rate limiting y emails transaccionales, y gestión admin via Backpack CRUD (ya instalado en el proyecto).

El frontend público usa React (a instalar) con Tailwind CSS 4 (ya configurado) compilado por Vite. El admin usa Backpack CRUD existente para gestión de waitlist, extendido con acciones custom para invitar y exportar CSV.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | Reglas de negocio waitlist: validar registro, calcular posición, generar token invitación | `App\Domain\Waitlist\WaitlistService` |
| Services | Orquestación: crear registro + enviar email, invitar + generar enlace | `App\Services\WaitlistService` |
| Infrastructure | Envío email, persistencia BD | Laravel Mail, Eloquent |
| Controllers/API | Endpoint público waitlist, servir SPA | `App\Http\Controllers\Api\WaitlistController`, `App\Http\Controllers\LandingController` |
| Admin (Backpack) | CRUD waitlist, botón invitar, exportar CSV | `App\Http\Controllers\Admin\WaitlistEntryCrudController` |
| Frontend (React) | Landing page, formulario waitlist, privacidad | `LandingPage`, `WaitlistForm`, `PrivacyPage` |

### Data Flow

```
Visitante → GET / → Laravel sirve blade wrapper → React SPA monta LandingPage
Visitante → Rellena form → POST /api/waitlist → WaitlistController
  → WaitlistFormRequest (validación)
  → WaitlistService::register()
    → Verificar duplicado email
    → Crear WaitlistEntry
    → Calcular posición aproximada (+/- 5)
    → Dispatch WaitlistConfirmationMail (queued)
  → Response 201 { message, position }

Admin → /admin/waitlist-entry → Backpack CRUD → Lista, filtros, exportar CSV
Admin → Botón "Invitar" → WaitlistService::invite(entry)
  → Generar token HMAC-SHA256 con expiración 7d
  → Guardar invitation_token + invitation_sent_at
  → Dispatch InvitationMail (queued)
```

### Transaction Boundaries
- **Registro waitlist**: transacción en `WaitlistService::register()` envuelve creación de registro. El email se despacha fuera de la transacción (queued).
- **Invitación**: transacción en `WaitlistService::invite()` actualiza el estado. Email fuera de transacción.
- Rollback: si la creación del registro falla, no se envía email (dispatch post-commit).

## Data Model

### New Tables

| Name | Purpose | Key Fields |
|------|---------|------------|
| `waitlist_entries` | Registros de lista de espera | `id`, `name`, `email` (unique), `shopping_companion` (nullable enum), `position`, `status` (enum: pending/invited/registered), `invitation_token` (nullable), `invitation_sent_at` (nullable), `invitation_expires_at` (nullable), `created_at`, `updated_at` |

### Migrations
1. `create_waitlist_entries_table`: Tabla principal con índice único en `email`, índice en `status`

### API Changes

| Endpoint | Method | Purpose | Auth | Rate Limit |
|----------|--------|---------|------|------------|
| `/` | GET | Servir landing (blade + React) | No | No |
| `/privacy` | GET | Servir página privacidad | No | No |
| `/api/waitlist` | POST | Registrar en waitlist | No | 3/IP/hora |
| `/admin/waitlist-entry` | GET | CRUD admin waitlist | Superadmin | No |
| `/admin/waitlist-entry/export` | GET | Exportar CSV | Superadmin | No |
| `/admin/waitlist-entry/{id}/invite` | POST | Enviar invitación | Superadmin | No |

### Request/Response Schemas

**POST /api/waitlist**
```json
// Request
{
  "name": "string, required, max:100",
  "email": "string, required, email, max:255",
  "shopping_companion": "string, nullable, in:solo,pareja,familia,compañeros"
}

// Response 201
{
  "message": "Te has registrado en la lista de espera",
  "position": 47
}

// Response 422 (validation)
{
  "message": "Los datos no son válidos",
  "errors": { "email": ["El email no es válido"] }
}

// Response 429 (rate limited)
{
  "message": "Has alcanzado el límite de intentos. Inténtalo más tarde."
}
```

## Frontend Architecture

### Setup
- Instalar: `react`, `react-dom`, `@vitejs/plugin-react`
- Configurar Vite para React + Laravel
- Routing: `react-router-dom` para `/` y `/privacy`
- Laravel sirve un blade wrapper que monta el React SPA para rutas públicas

### Components

```
resources/js/
├── app.jsx                  # Entry point React
├── pages/
│   ├── LandingPage.jsx      # Hero + features + compromiso datos + WaitlistForm
│   └── PrivacyPage.jsx      # Política privacidad RGPD
├── components/
│   ├── WaitlistForm.jsx     # Formulario nombre + email + companion
│   ├── HeroSection.jsx      # Hero con tagline
│   ├── FeaturesSection.jsx  # 3 características
│   └── DataCommitment.jsx   # Sección compromiso de datos
└── lib/
    └── api.js               # Axios wrapper para /api/*
```

### State Management
No se necesita Zustand para Épica 0. Estado local con `useState` es suficiente para el formulario.

## Rate Limiting

Usar middleware Laravel `throttle` configurado:
- Crear middleware custom `ThrottleWaitlist` que aplica `throttle:3,60` (3 requests por 60 minutos por IP)
- Respuesta 429 con mensaje amigable en JSON

## Email

### Templates
1. **WaitlistConfirmationMail**: Confirma registro con posición aproximada
2. **InvitationMail**: Enlace de registro con token (expira 7 días)

### Posición aproximada
`position = count(waitlist_entries where status != 'registered') + random(-5, +5)`
Mínimo 1, nunca negativo.

## Admin (Backpack CRUD)

- `WaitlistEntryCrudController` extiende Backpack CrudController
- Columnas: nombre, email, fecha registro, companion, status
- Filtros: por status, por fecha
- Botón custom "Invitar" en cada fila (solo si status=pending)
- Botón "Exportar CSV" en toolbar
- Acceso: middleware `role:superadmin` (Backpack PermissionManager ya instalado)

## Security

- Rate limiting por IP en endpoint público
- Tokens de invitación: `hash_hmac('sha256', $email . $timestamp, config('app.key'))` con expiración 7d
- Email duplicado: respuesta genérica sin revelar existencia
- Admin protegido por middleware de rol superadmin
- No cookies de tracking, no scripts de terceros en landing
- CSRF protection en formulario (via Laravel)

## Performance

### Query Optimization
- Índice único en `waitlist_entries.email` para búsqueda rápida de duplicados
- Índice en `status` para filtros admin

### Landing Performance
- React SPA con code splitting (lazy load PrivacyPage)
- Assets compilados por Vite con hashing
- Tailwind CSS purging automático
- Meta: carga <2s en 3G

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| React SPA para landing | Reutilizable para toda la app, consistente con stack futuro | Overhead para página simple | **Selected**: inversión a futuro, todo el frontend será React |
| Blade puro para landing | Más rápido de implementar, mejor SSR | No reutilizable, cambio de stack luego | Rejected: duplicaría trabajo |
| Backpack CRUD para admin waitlist | Ya instalado, rápido, con permisos | Acoplado a Backpack | **Selected**: ya está en el proyecto, ideal para admin |
| Admin custom React | Consistente con frontend | Mucho más trabajo, reinventar la rueda | Rejected: Backpack cubre las necesidades |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| SMTP no configurado | High | Medium | Fallback a log driver en dev. Documentar config en .env.example |
| React + Vite config issues | Medium | Low | Configuración estándar bien documentada |
| Rate limit bypass por proxy | Low | Low | Suficiente para fase waitlist |

## Open Questions
Ninguna. Requisitos completos.

## Implementation Notes
1. Primero: instalar dependencias React y configurar Vite
2. Segundo: migración BD + modelo WaitlistEntry
3. Tercero: backend (controller, service, form request, emails, rate limiting)
4. Cuarto: Backpack CRUD admin
5. Quinto: frontend React (landing + form + privacy)
6. Sexto: tests

## Transition
- Gate Status: S3 PASSED
- Next Step: STEP 4 - Implementation
- Required Artifacts: 02-prd.md, 03-technical-design.md
