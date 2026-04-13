# Implementation Notes - FEAT-EPIC0-LANDING

## Summary
Landing page pública con React SPA, formulario de waitlist con rate limiting, emails transaccionales, política de privacidad RGPD, y panel admin Backpack CRUD para gestión de waitlist.

## Files Changed

| File | Type | Description | Tests |
|------|------|-------------|-------|
| `database/migrations/2026_04_10_190624_create_waitlist_entries_table.php` | Migration | Tabla waitlist_entries | - |
| `app/Models/WaitlistEntry.php` | Model | Modelo Eloquent con estados y validación de invitación | WaitlistEntryTest |
| `app/Services/WaitlistService.php` | Service | Lógica de registro, invitación, posición aproximada | WaitlistServiceTest |
| `app/Http/Requests/WaitlistFormRequest.php` | FormRequest | Validación: name, email, shopping_companion | WaitlistControllerTest |
| `app/Http/Controllers/Api/WaitlistController.php` | Controller | POST /api/waitlist (thin, delega a service) | WaitlistControllerTest |
| `app/Http/Controllers/Admin/WaitlistEntryCrudController.php` | Admin CRUD | Backpack CRUD + invite + export CSV | - |
| `app/Mail/WaitlistConfirmationMail.php` | Mailable | Email confirmación con posición | WaitlistServiceTest |
| `app/Mail/InvitationMail.php` | Mailable | Email invitación con token 7d | WaitlistServiceTest |
| `resources/views/emails/waitlist-confirmation.blade.php` | View | Template email confirmación | - |
| `resources/views/emails/invitation.blade.php` | View | Template email invitación | - |
| `resources/views/landing.blade.php` | View | Blade wrapper para React SPA | WaitlistControllerTest |
| `resources/views/vendor/backpack/crud/buttons/invite.blade.php` | View | Botón invitar en admin | - |
| `resources/views/vendor/backpack/crud/buttons/export_csv.blade.php` | View | Botón exportar CSV en admin | - |
| `resources/js/app.jsx` | React Entry | Entry point React con routing | - |
| `resources/js/pages/LandingPage.jsx` | React Page | Landing completa: hero+features+form+commitment | LandingPage.test |
| `resources/js/pages/PrivacyPage.jsx` | React Page | Política privacidad RGPD completa | PrivacyPage.test |
| `resources/js/components/HeroSection.jsx` | React Component | Hero con nombre y tagline | HeroSection.test |
| `resources/js/components/FeaturesSection.jsx` | React Component | 3 features: IA, compartidas, historial | FeaturesSection.test |
| `resources/js/components/DataCommitment.jsx` | React Component | Compromiso datos + enlace privacidad | DataCommitment.test |
| `resources/js/components/WaitlistForm.jsx` | React Component | Formulario waitlist con estados | WaitlistForm.test |
| `resources/js/lib/api.js` | Utility | Axios wrapper con CSRF | - |
| `routes/web.php` | Routes | / y /privacy (landing), POST /api/waitlist | WaitlistControllerTest |
| `routes/backpack/custom.php` | Routes | CRUD waitlist-entry + invite + export-csv | - |
| `database/factories/WaitlistEntryFactory.php` | Factory | Factory con estados invited/expired | - |
| `vite.config.js` | Config | Añadido plugin React | - |
| `package.json` | Config | React, react-router-dom, vitest, testing-library | - |
| `vitest.config.js` | Config | Vitest con jsdom + setup | - |

## Migrations

| Migration | Description | Reversible |
|-----------|-------------|------------|
| `create_waitlist_entries_table` | name, email(unique), shopping_companion(enum), position, status(enum), invitation_token, timestamps | Yes (dropIfExists) |

## API Endpoints

| Method | Path | Auth | Rate Limit | Description |
|--------|------|------|------------|-------------|
| GET | `/` | No | No | Landing page (React SPA) |
| GET | `/privacy` | No | No | Privacy page (React SPA) |
| POST | `/api/waitlist` | No | 3/IP/hora | Registro en waitlist |
| GET | `/admin/waitlist-entry` | Superadmin | No | CRUD admin waitlist |
| GET | `/admin/waitlist-entry/{id}/invite` | Superadmin | No | Enviar invitación |
| GET | `/admin/waitlist-entry/export-csv` | Superadmin | No | Exportar CSV |

### Request/Response: POST /api/waitlist

```json
// Request
{
  "name": "Juan García",
  "email": "juan@example.com",
  "shopping_companion": "familia"
}

// Response 201
{
  "message": "Te has registrado en la lista de espera",
  "position": 47
}

// Response 422
{
  "message": "Los datos no son válidos",
  "errors": { "email": ["El email no es válido"] }
}

// Response 429
{ "message": "Too Many Attempts." }
```

## Tests Added

| Test File | Type | Count | What it tests |
|-----------|------|-------|---------------|
| tests/Unit/Models/WaitlistEntryTest.php | Unit | 7 | Model states, casts, fillable |
| tests/Unit/Services/WaitlistServiceTest.php | Unit | 8 | Register, invite, duplicate, position, token |
| tests/Feature/Api/WaitlistControllerTest.php | Feature | 13 | API endpoint, validation, rate limit, pages |
| resources/js/components/HeroSection.test.jsx | Frontend | 2 | Renders name + tagline |
| resources/js/components/FeaturesSection.test.jsx | Frontend | 1 | Renders 3 features |
| resources/js/components/DataCommitment.test.jsx | Frontend | 3 | Commitment text, items, link |
| resources/js/components/WaitlistForm.test.jsx | Frontend | 8 | Submit, errors, rate limit, loading |
| resources/js/pages/LandingPage.test.jsx | Frontend | 2 | Full page render, footer |
| resources/js/pages/PrivacyPage.test.jsx | Frontend | 4 | Title, RGPD rights, back link |

**Backend: 30 tests, 77 assertions — ALL PASSING**
**Frontend: 20 tests — ALL PASSING**

## Known Issues / Technical Debt
- phpunit.xml uses SQLite in-memory. Rules say "no SQLite for tests, use real DB". Needs MySQL test config when DB is available.
- Admin CRUD tests not written (Backpack CRUD integration tests require full Backpack setup).

## Deviations from Design
- None.

## Transition
- Gate Status: S4 PASSED
- Next Step: STEP 5 - Reviews
