# Design — Views Map

## Frontend Structure (`dashboard/src/`)

| Directory/Module | Purpose | Domain |
|-----------------|---------|--------|
| `app/page.tsx` | App root / list dashboard | Shopping Lists |
| `app/layout.tsx` | Root layout (auth context, providers) | Global |
| `app/globals.css` | Global Tailwind + custom styles | Global |
| `app/features/[id]/page.tsx` | Feature workflow viewer (Sofia4Builders admin) | Dev tooling |
| `app/api/features/[id]/actions/` | Feature workflow API routes | Dev tooling |
| `app/api/features/[id]/artifacts/` | Feature artifacts API | Dev tooling |
| `app/api/features/[id]/prompt/` | Feature prompt API | Dev tooling |
| `app/api/metrics/route.ts` | Admin metrics API route | Admin |
| `components/header.tsx` | App header (nav, user menu) | Global |
| `components/claude-terminal.tsx` | Claude terminal emulator | Dev tooling |
| `components/terminal-provider.tsx` | Terminal state provider | Dev tooling |
| `lib/actions.ts` | Server actions / API helpers | Global |

## Key React Pages (inferred from feature docs)

| Page | Route | Domain |
|------|-------|--------|
| Landing page | `/` (public) | Marketing |
| Login page | `/login` | Auth |
| Register page | `/register` | Auth |
| Dashboard (lists) | `/app` | Shopping Lists |
| List detail | `/app/lists/{id}` | List Items |
| Shared list | `/app/shared/{token}` | Collaboration |
| History | `/app/historial` | History |
| Profile | `/app/profile` | Auth |
| Admin panel | `/admin/*` | Admin (Backpack/Blade) |

## Component Inventory (key components)

| Component | Purpose |
|-----------|---------|
| `AddItemModal` | Item creation form with autocomplete combobox |
| `HeroSection` | Landing page hero with mobile-optimized layout |
| `PriceBar` | Summary bar showing estimated total price range |
| `BiometricOptInModal` | Post-login prompt for passkey registration |
| `SelectListModal` | Reusable modal for picking from user's lists |
| `ShareListModal` | Share token management (create, copy, revoke) |
| `CollaboratorPanel` | Owner view of who has access to a list |

## Admin Views (Backpack/Blade)

| View | Purpose |
|------|---------|
| `admin/dashboard` | Metrics cards (users, lists, AI usage, waitlist) |
| `admin/user` | User CRUD with is_active toggle, plan selector |
| `admin/ai-usage-log` | Read-only AI usage with filters and cost summary |
| `admin/waitlist-entry` | Waitlist management with invitation actions |
| `admin/ai-prompt` | Editable AI system prompts |

## Internationalization

- i18next integrated (Epic: branding + i18n commit `7befae7`)
- Language detection: browser language API
- Default language: Spanish (es)
- Translations: Spanish strings corrected across all components
