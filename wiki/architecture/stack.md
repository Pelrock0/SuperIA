# Architecture — Stack & Dependencies

## Runtime

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | Laravel | ^12.0 |
| Language | PHP | ^8.4 (runtime 8.5.5) |
| Frontend | Next.js | ^15.3.1 |
| Frontend runtime | React | 19.2.4 |
| Styling | TailwindCSS | ^4 |
| Language | TypeScript | ^5 |
| Database | MySQL | (production) |
| Cache / Queue | Laravel default (Redis or file) | — |

## Key Backend Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `laravel/framework` | ^12.0 | Core framework |
| `tymon/jwt-auth` | ^2.3 | JWT token auth |
| `web-auth/webauthn-lib` | ^5.2 | WebAuthn / FIDO2 passkeys |
| `resend/resend-laravel` | 1.3 | Transactional email delivery |
| `backpack/crud` | ^7.0 | Admin panel CRUD |
| `backpack/permissionmanager` | ^7.3 | Role/permission UI (Spatie wrapper) |
| `backpack/pro` | ^3.0 | Advanced admin features |
| `backpack/settings` | ^3.2 | App settings management |
| `backpack/theme-tabler` | ^2.0 | Tabler CSS theme for admin |
| `laravel/telescope` | ^5.17 | Dev debugging + monitoring |
| `laravel/tinker` | ^2.11 | REPL for local dev |
| `laravel-lang/common` | ^6.7 | i18n language files |
| `vimeo/psalm` | ^6.0 | Static analysis (CI) |
| `psalm/plugin-laravel` | ^3.0 | Laravel-aware Psalm rules |

## Key Frontend Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `next` | ^15.3.1 | React framework + SSR |
| `react` | 19.2.4 | UI library |
| `tailwindcss` | ^4 | Utility CSS |
| `recharts` | — | Charts (statistics page) |
| `i18next` | — | Internationalization |
| `xterm` | ^6.0.0 | Terminal emulator (dashboard) |
| `node-pty` | ^1.1.0 | PTY for terminal (dashboard) |
| `ws` | ^8.20.0 | WebSocket (dashboard terminal) |

## PHP Extensions Required

- `ext-bcmath` (BigDecimal math)
- `ext-curl`, `ext-mbstring`, `ext-openssl`, `ext-pdo`, `ext-xml` (standard Laravel)

## CI/CD

- GitHub Actions (`security.yml`): Psalm + Composer audit + gitleaks
- PHP 8.5.5 (upgraded from 8.4.0 for Psalm 6 compatibility)
- Tests: PHPUnit, Vitest (frontend)
- Database: MySQL (real DB in tests, no SQLite)
