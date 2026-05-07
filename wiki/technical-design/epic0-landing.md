# Technical Design — FEAT-EPIC0-LANDING

## Architecture

Laravel API + React SPA + Backpack CRUD admin.

- `WaitlistService` handles registration, rate limit check, and invitation generation
- `WaitlistFormRequest` validates name + email + honeypot
- Queued mail: `VerificationMail` dispatched after DB commit
- Backpack CRUD: standard resource for `WaitlistEntry`

## Data Flow

```
POST /api/waitlist
  → WaitlistFormRequest (validate + rate limit 3/IP/hr)
  → WaitlistService::register()
    → DB::transaction {
        INSERT waitlist_entries (position = pending count + 1)
        dispatch(VerificationMail)
      }
  → return { position: approx ± 5, message }

Admin invite:
POST /admin/waitlist/{entry}/invite
  → Generate HMAC token (Str::random(32) + expiry 7d)
  → Persist token + sent_at
  → Queue InvitationMail
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| React SPA (not Blade) | Future-proofing for full app |
| Backpack admin (not custom) | Pre-installed, zero extra cost |
| HMAC token (not DB lookup) | Stateless — token validity from signature alone |
| Position obfuscation (±5) | Prevents list scraping without losing UX value |
| Rate limit by IP | Simplest effective protection for public endpoint |

## Gotchas

- Token generation initially used predictable timestamp — **fixed** to `Str::random(32)` after review
- Email dispatched outside transaction: if queue fails, entry exists but no email → acceptable (admin can re-invite)
- Admin routes must be POST + CSRF — originally GET, **fixed** after review
