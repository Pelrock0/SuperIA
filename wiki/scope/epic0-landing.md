# FEAT-EPIC0-LANDING — Landing & Waitlist

**Complexity:** MEDIUM (20-28h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-001 | Responsive landing page, value proposition, no analytics | Implemented |
| HU-002 | Waitlist form: name+email, rate limit 3/IP/hr, confirmation email | Implemented |
| HU-003 | Privacy policy: RGPD-compliant, visible data commitment | Implemented |
| HU-004 | Admin panel: Backpack CRUD, CSV export, token-based invitations (7d expiry) | Implemented |

## Complexity Classification

- Backend: MEDIUM — WaitlistService, HMAC tokens, rate limiting, Backpack CRUD
- Frontend: LOW — React landing page, static form
- Security: MEDIUM — rate limiting, token security, PII handling

## Key Dependencies

- Email service (Mailtrap/Resend)
- Backpack CRUD (pre-installed)
- React SPA

## Design Decisions

- Positional approximation (+/−5) prevents list scraping
- Rate limiting by IP only (3/hr)
- Duplicate email returns same success message (no enumeration)
- Token = HMAC-SHA256 over `APP_KEY + entry_id + email` — never stored, recomputed on verify
- Admin export: CSV with PII, admin-only access (accepted risk)

## Deviations

None — implementation matched scope.

## Review Findings

- Token generation initially used predictable timestamp → fixed to `Str::random(32)`
- Admin invite routes changed from GET to POST + CSRF
- 51 tests passing (31 backend + 20 frontend)
