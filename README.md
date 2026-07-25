<div align="center">

# 🛒 Superlistia

**Your smart shopping list, powered by AI.**

Create, share and optimize your shopping lists. Superlistia autocompletes products,
generates full lists from a single sentence, estimates prices, detects duplicates,
warns you about restocks and summarizes your weekly spend — all powered by AI.

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![Vite](https://img.shields.io/badge/Vite-7-646CFF?logo=vite&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![Claude](https://img.shields.io/badge/AI-Claude%20Haiku-D97757?logo=anthropic&logoColor=white)
![Tests](https://img.shields.io/badge/tests-~967-success)
![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)

</div>

---

## ✨ What does it do?

Superlistia turns the shopping list into an assisted experience. Instead of typing
product by product, you describe what you need and the AI does the heavy lifting:
it suggests, categorizes, estimates prices and learns from your habits.

### Features

| Area | Feature |
|---|---|
| 🏠 **Landing & Waitlist** | Public landing page + waitlist with lead capture. |
| 🔐 **Authentication** | Email/password login + **JWT**, **biometrics (WebAuthn/passkeys)**, email verification and **GDPR-compliant account deletion**. |
| 📋 **Shopping lists** | Full CRUD, freemium limit (3 active lists), archive and delete. |
| 🥕 **Items and categories** | Products within each list, auto-organized by section (Fruits, Dairy, Bakery…), product history. |
| 👥 **Collaboration** | Share lists via **signed link (HMAC)** with permissions (edit / view-only), sharing over **WhatsApp and Email**. |
| ⚡ **Smart autocomplete** | **3-layer** pipeline (personal history → catalog → AI) that learns from your purchases. |
| 🔁 **Restock and complements** | Restock reminders based on your purchase frequency + complementary product suggestions. |
| 🤖 **AI generation** | Write *"Italian dinner for 2"* and get a full list, with quantities scaled per diner. |
| 💶 **Price estimation** | Estimated price per product with a 3-layer pipeline (history + catalog + AI) and confirmation of real prices to improve future estimates. |
| 🔎 **Duplicate detection** | Avoids duplicates with singular/plural normalization in Spanish (*pan ↔ panes*); already-purchased items don't block new additions. |
| 📊 **History and stats** | Purchase history + stats dashboard (spending and category charts). |
| 📧 **Weekly summary** | Scheduled email (Mondays) with the week's summary, opt-in and unsubscribe via signed link. |
| 🛠️ **Admin panel** | Backoffice with **Backpack** (user, plan and AI limit/usage management) + **Telescope** for observability. |

---

## 🧱 Architecture and stack

- **Backend:** Laravel 12 (PHP 8.4+), **hexagonal** architecture with **DDD**
  principles (bounded contexts, domain services, per-context glossary).
- **Frontend:** React 19 + Vite (SPA served by Laravel), Tailwind CSS with a custom
  design system, internationalization with **i18next**.
- **Database:** MySQL 8 (queues, cache and sessions on the database — no Redis required).
- **AI:** Claude API (**Haiku**) for autocomplete, generation, pricing and summaries,
  with a configurable **monthly budget** and per-operation/plan usage limits.
- **Auth:** JWT + WebAuthn (passkeys/biometrics).
- **Quality:** ~967 tests (PHPUnit + Vitest), **100% coverage** enforced and
  **mutation testing** with Infection (Covered MSI ≈ 87%).

```
app/
├── Domain / Services      # Business logic (hexagonal + DDD)
├── Http/Controllers       # REST API + catch-all SPA
├── Jobs                   # Queued jobs (e.g. AI categorization)
├── Console/Commands       # Scheduled commands (cron)
└── Support/Inflector      # Singular/plural normalizer (ES)
resources/js/              # React 19 SPA (pages, components, libs)
routes/console.php         # Scheduled tasks (scheduler)
docs/                      # Feature and deployment documentation
```

---

## 🚀 Getting started (local development)

### Requirements

- PHP **≥ 8.4** with extensions: `mbstring, pdo_mysql, openssl, tokenizer, xml, ctype, json, bcmath, curl, fileinfo, intl`
- Composer 2.x · Node 20+ · MySQL 8 (or MariaDB 10.6+)
- A **Claude API key** (Anthropic) for the AI features.

### Installation

```bash
git clone https://github.com/Pelrock0/SuperIA.git
cd SuperIA

composer install
cp .env.example .env
php artisan key:generate

# Configure .env: DB_CONNECTION=mysql, DB credentials and CLAUDE_API_KEY
php artisan migrate --seed          # creates tables + catalog, prompts and superadmin

npm install
npm run dev                         # Vite in development mode
php artisan serve                   # http://localhost:8000
```

### Key environment variables

| Variable | Description |
|---|---|
| `APP_KEY` | App key (`php artisan key:generate`). |
| `DB_*` | MySQL connection (`DB_CONNECTION=mysql` in real environments; the `sqlite` default is dev-only). |
| `CLAUDE_API_KEY` | **Required** — without it, autocomplete, generation, pricing and summaries don't work. |
| `CLAUDE_MODEL` / `AI_GENERATION_MODEL` / `AI_WEEKLY_SUMMARY_MODEL` | AI models (defaults to `claude-haiku-4-5`). |
| `AI_BUDGET_CAP_MONTHLY_USD` | Monthly AI spend cap; cuts off calls once exceeded. |
| `MAIL_*` | SMTP for email verification, weekly summary and unsubscribes. |
| `QUEUE_CONNECTION` / `CACHE_STORE` / `SESSION_DRIVER` | `database` by default. |

> ⚠️ Secrets (`CLAUDE_API_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`) are never committed —
> `.env` is in `.gitignore`.

### Tests

```bash
php artisan test        # backend (PHPUnit, 100% coverage)
npm run test            # frontend (Vitest)
composer security       # composer audit + Psalm taint analysis
```

---

## 🔧 Deployment

The project is **already deployed in production**. For full infrastructure details
(Nginx, queue worker, cron scheduler, first boot) see
**[`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)**.

### Update guide (incremental deploy)

```bash
php artisan down                                   # maintenance mode
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart                          # reload code in the workers
php artisan up
```

**Remember** that in production these must keep running:
- **Queue worker:** `php artisan queue:work` (under Supervisor/systemd) — processes
  AI item categorization.
- **Scheduler (cron every minute):** `* * * * * php artisan schedule:run` — runs GDPR
  deletion, collaboration/suggestion cleanup, AI quota resets and the Monday weekly
  summary.

---

## 🤖 How it was built: Sofia4Builders

This project wasn't hand-written. It was built with **Sofia4Builders**, a
deterministic agentic harness I designed to take an ambiguous request all the way to
production software without losing rigor along the way.

What you see in this repository is its output, not a separate effort:

| What's here | Why it's here |
| --- | --- |
| Hexagonal architecture with DDD and a per-context glossary | The system requires a closed glossary before code gets written; if a term is ambiguous, work doesn't move forward |
| ~967 tests with 100% coverage and mutation testing (MSI ≈ 87%) | Coverage is an invariant of the harness, not an optional goal; tests run against a real database |
| Security review (JWT, WebAuthn, HMAC-signed links, GDPR) | Every feature goes through a security reviewer that maps against the OWASP Top 10 for web, API and LLM; any medium-or-higher finding blocks the pipeline |
| AI spend cap and per-operation limits | Cost is treated as a requirement, not a surprise at month's end |
| `CLAUDE.md`, `PROMPTS.md`, `wiki/`, `docs/` | Flat, auditable project memory that carries forward across features |

The design rests on three non-negotiable rules: **one agent per step**, so none
encroaches on another's work; **four independent reviewers** (code, security, tests
and UX) that can send work back and cannot be skipped; and **a human approval at
every step**, because machines propose and prove, but don't decide.

In the professional setting where I use it daily — a regulated pharmaceutical sector
under audit — application delivery time went from 66 days to 10–15, and project teams
of 3–4 developers dropped to 1.

If you're curious about the reasoning behind some of the design decisions:

- [Anticipated Rationalizations](https://medium.com/@pelrock) · why agents drift when
  the workflow is a suggestion instead of an obligation
- [Memory for Agents](https://medium.com/@pelrock) · why project memory is a flat,
  auditable wiki instead of the trendy tooling

---

## 📄 License

Distributed under the **[MIT](LICENSE)** license — © 2026 Alfredo Martín (pelrock@gmail.com).

You may use, modify and distribute the project freely, **keeping the copyright notice
and attribution to the author** in all copies or substantial portions of the software.

<div align="center">
<sub>Made with Laravel · React · Claude</sub>
</div>
