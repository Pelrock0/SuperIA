# Scope Analysis: FEAT-EPIC5C-SUMMARY

## Feature Request

Implement HU-505 (Resumen semanal de reposición inteligente) from `docs/Superia_HU_v3.md` § Épica 5. Original narrative:

> Como usuario habitual, quiero recibir una sugerencia semanal con lo que probablemente necesito comprar esta semana, para planificar mi compra sin esfuerzo.

**Acceptance criteria from the HU**:
1. Una vez a la semana (lunes por la mañana), el sistema genera un resumen personalizado.
2. El resumen se muestra como notificación en la app y opcionalmente por email.
3. Incluye: productos con reposición pendiente + sugerencias basadas en época del año.
4. El usuario puede convertir el resumen en una lista nueva con un toque.
5. El usuario puede desactivar esta funcionalidad desde ajustes.
6. Solo se activa si el usuario tiene al menos 3 semanas de historial.

**Stack note**: the original HU mentions "APScheduler dentro de FastAPI" because the spec was written before the stack was switched to Laravel. Per the canonical project memory, this feature uses **Laravel Scheduler** (already present and active for 4 commands). No FastAPI/APScheduler.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 14–20 hours |
| Confidence | Medium |

## Justification

**HIGH** because multiple risk-bearing axes apply at once:

- **Multi-component**: backend Mailable + console command + scheduler entry + Claude integration + DB migration(s) + 2-3 API endpoints + 2 frontend components (WeeklySummaryPage + dashboard banner) + settings UI toggle.
- **First feature that emails users at scale**: existing mailables (`WaitlistConfirmationMail`, `PasswordResetMail`, `AccountDeletionMail`, `InvitationMail`, `VerificationMail`, `BudgetCapExceededAlert`) are all transactional/triggered by user action. HU-505 fan-outs to **every active user every Monday morning** — operationally a different beast (rate limiting, mail provider quota, bounce handling, unsubscribe).
- **New AI surface**: third Claude integration in Epic 5 (after 5A autocomplete and 5B replenishment/complements). Triggers OWASP LLM Top 10 v2 review again. Prompt includes 4 weeks of consumption history → PII leaving the system to Anthropic. Already established pattern via `HistoryAnonymizer` but the new prompt shape needs review.
- **Cross-cutting concerns**: idempotency for the weekly job (cron retries, partial failures), rate limiting against Claude budget (shared `AiUsageTracker` quota or its own pool — TBD), GDPR considerations (unsubscribe link is mandatory for any non-strictly-transactional outbound email).
- **Freemium intersection**: AC-4 ("convertir el resumen en una lista nueva") collides with the existing freemium limit of 3 active lists. Behavior on overflow must be specified.
- **Stitch dependency**: HU references a Stitch screen `WeeklySummaryPage`. Whether the design exists in the Stitch project today is unknown; if not, design must be generated/fetched before S4 frontend work.

> Per the rule "When in doubt, escalate to the higher complexity" — I'm escalating from MEDIUM to HIGH because of the email-fanout operational risk and the GDPR exposure on outbound email.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Medium | Claude integration patterns are established (3rd time in Epic 5). Laravel Scheduler is in use. Mailable infra exists. The only **new** technical primitive is "fan-out a weekly job over many users with per-user error isolation". |
| Data | Medium | New table(s) likely required: at minimum `weekly_summary_settings` (or a `users.weekly_summary_enabled` column) and possibly `weekly_summaries` (one row per user per week, idempotency dedupe key). Migration is small but non-trivial — needs reversibility and backfill considerations. |
| Security | **High** | (1) PII in outbound email body (last 4 weeks of consumption history) — even with `HistoryAnonymizer`, the email itself shows real product names to the user, and the email provider sees the rendered HTML. (2) Unsubscribe link must be tamper-resistant (signed token, single-use or HMAC) so an attacker cannot unsubscribe arbitrary users. (3) "Convert summary to list" endpoint is authenticated state mutation — needs CSRF/auth + freemium check + idempotency. (4) Prompt injection: 4 weeks of user-controlled product names flow into a Claude prompt — must continue using `PromptSanitizer`. (5) Email enumeration: rate-limit any "summary status" endpoint to prevent attackers polling user existence. |
| Performance | Medium | The weekly job runs over **all active users with ≥3 weeks of history**. At scale this is N HTTP calls to Claude + N email sends. Need bounded concurrency (Laravel queue chunking), per-user error isolation (one user's failure does not block the rest), and Claude budget cap respected (shared `BudgetCap` already exists). Estimated cost per user: ~$0.005/week → 1000 users ≈ $5/week = $260/year. Manageable but needs explicit budget projection in the PRD. |
| Operational | **High** | (1) **First scheduled email fan-out** in the project — bounce handling, suppression list, mail provider rate limits all need to be considered for the first time. Mailtrap is dev-only; **production mail provider is unknown today** (TBD). (2) Cron failure modes: if the Monday job fails partway through (Claude outage, mail provider outage, server restart), how are unprocessed users handled on retry? Idempotency dedupe key required. (3) Observability: need a dashboard or log aggregator entry for "weekly summary run on YYYY-MM-DD: X processed, Y failed, total cost $Z". (4) Unsubscribe handling: link must work even if the user is logged out. (5) Hard kill switch: if the feature misbehaves, ops needs a config flag to disable it without a redeploy. |

## Affected Areas

### Backend
- `app/Console/Commands/` — new command, e.g. `ai:weekly-summary` or `WeeklySummaryDispatch`
- `routes/console.php` — new `Schedule::command(...)->weeklyOn(1, '08:00')->timezone('Europe/Madrid')` entry
- `app/Mail/` — new `WeeklySummaryMail` mailable
- `app/Services/` — new `WeeklySummaryService` (orchestrates per-user generation)
- `app/Support/Ai/ClaudeClientInterface.php` + `ClaudeClient.php` + `FakeClaudeClient.php` — new method `generateWeeklySummary(array $context): array`
- `app/Support/Ai/AiUsageTracker.php` — new `AiOperation::WeeklySummary` enum case (or shared with existing)
- `app/Models/` — new `WeeklySummary` model (and possibly `WeeklySummarySetting` if not flat-column on `users`)
- `database/migrations/` — at least 1 migration: weekly_summaries table + users.weekly_summary_enabled column (or settings table row)
- `app/Http/Controllers/` — new endpoints: `GET /api/weekly-summary/latest`, `POST /api/weekly-summary/convert-to-list`, `POST /api/settings/weekly-summary` (toggle), `GET /unsubscribe/weekly-summary?token=...` (public, signed)
- `app/Http/Requests/` — FormRequests for the new endpoints
- `routes/api.php` + `routes/web.php` — new routes
- `config/ai.php` — new section for weekly_summary (model, max_tokens, prompt template, budget per call)
- `resources/views/emails/` — new Blade template for the email body

### Frontend
- `resources/js/pages/WeeklySummaryPage.{tsx,jsx}` — main page (route `/app/resumen`)
- `resources/js/components/WeeklySummaryBanner.tsx` — dashboard notification component
- `resources/js/pages/SettingsPage.tsx` — toggle for weekly summary opt-out (existing or new file)
- `resources/js/api/weeklySummary.ts` — API client wrapper
- Stitch design dependency: project "Superia" → screen "Resumen semanal" — **status unknown**; verify via MCP before frontend S4

### Tests
- Unit tests for `WeeklySummaryService` (per-user generation, ≥3-weeks-history gate, AiUsageTracker quota check)
- Feature tests for the console command (uses `Mail::fake()` + `Bus::fake()`)
- Feature tests for each of the 4 new endpoints (happy + auth bypass + freemium edge for convert-to-list)
- Idempotency test (run command twice in same week → exactly one email sent per user)
- Unsubscribe token test (valid, invalid, replay)
- Frontend component tests (vitest)

## Resolved Decisions (S1, 2026-04-12)

All 15 open questions resolved by the user during S1 review. Decisions below are binding inputs to S2 (PRD) and S3 (Tech Design).

| # | Decision | Source |
|---|----------|--------|
| 1 | **Mail provider**: Laravel built-in SMTP driver. Configure via env (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`). No SES/Resend/Postmark for V1. **Bounce handling out of scope V1.** | User |
| 2 | **In-app notification**: option (a) — persistent banner on dashboard until dismissed. Component: `WeeklySummaryBanner`. No new infra. | User |
| 3 | **Email opt-in default**: option (b) — opted-in for in-app by default; email requires **explicit opt-in** by user. Weekly summary is treated as marketing-style per RGPD, not transactional. | User |
| 4 | **Active user filter**: exclude soft-deleted, exclude unverified (`email_verified_at IS NULL`), exclude users whose last `producto_historial.fecha_compra` is older than 60 days (activity-based proxy, **not** login-based — the `users.last_login_at` column does not exist in the schema and adding it would require touching `AuthController` of Epic 1). Filter hook for future freemium-vs-paid kept, no filter today. **Revised 2026-04-12 during S4** after discovering the missing column; user confirmed option D. | User |
| 5 | **Freemium limit on convert-to-list**: option (a) — return existing `FREEMIUM_LIMIT` 403, identical to `ShoppingListController::store`. No special cases. | User |
| 6 | **AiUsageTracker quota**: **shared** budget. `weekly_summary` uses the existing pool via a new `AiOperation` enum case. Heavy autocomplete users may miss the summary — acceptable for V1. Isolation deferred to a future iteration. | User |
| 7 | **Idempotency**: unique constraint on `(user_id, week_start_date)` in a new `weekly_summaries` table. The DB constraint **is the source of truth** for dedup; no application-level pre-check needed. | User |
| 8 | **Localization**: Spanish-only for V1. No i18n infra. All new mailables/templates in Spanish. | User |
| 9 | **Estacionalidad**: option (b) — let Claude infer from the month in the prompt. No hardcoded 12-bucket list. Prompt drift risk is accepted for V1 (nice-to-have feature). | User |
| 10 | **Unsubscribe token**: `URL::signedRoute` with **TTL 30 days**. Stateless (no DB row). Not single-use — regenerated per email send. Intercepted token expires within 30 days max. | User |
| 11 | **Failure isolation**: option (a) — continue on per-user failure, log it, retry only the failed cohort in the next scheduled run. **Never abort on first failure.** | User |
| 12 | **Stitch screen**: `resumen_semanal` exists in the Stitch project. S4 frontend must fetch it via MCP before generating `WeeklySummaryPage`. | User |
| 13 | **Hard kill switch**: `config('ai.weekly_summary.enabled')` env-backed flag, default `true`. Must be checked before dispatching any jobs. | User |
| 14 | **Cost cap per run**: rely on existing per-user `BudgetCap`. No separate per-run cap for V1. Global `AiUsageTracker` budget block is sufficient. | User |
| 15 | **Test data**: factories and seeders for 3-week `producto_historial` simulation are a **required deliverable** of this feature, not optional. | User |

> All open questions are RESOLVED. No TBDs remain. S1 is unblocked for S2 (PRD).

## Open Questions (historical — superseded by Resolved Decisions above)

> Per `core.md` § 9 — TBDs must be resolved before advancing past S2 (PRD).

1. **Production mail provider**: Mailtrap is configured for dev. What is the production target? (SMTP via SES? Resend? Postmark? Mailgun?) This determines bounce-handling integration, suppression list mechanism, and rate limits. Without an answer, deliverability and compliance can't be designed. answer is SMTP

2. **"Notificación en la app" mechanism**: AC-2 says "notificación en la app y opcionalmente por email". What does "in-app notification" mean concretely?
   - (a) A persistent banner on the dashboard until dismissed?
   - (b) A toast on next login?
   - (c) A new in-app inbox/feed (new infra)?
   - (d) Browser push notifications (new infra, requires service worker)?
   - The simplest is (a), which is what `WeeklySummaryBanner` would imply. Confirm.^
   answer is a

3. **Email opt-in default**: per AC-5 the user can disable. Default state should be:
   - (a) Opted in by default (notification + email)?
   - (b) Opted in for in-app, opted out for email (user must enable email)?
   - (c) Opted in for in-app only, email is a separate opt-in?
   - GDPR allows transactional email by default but expects explicit consent for marketing-style outbound. Weekly summary sits in a gray zone.

4. **"Active user" definition**: AC-1 says "el sistema genera un resumen personalizado" without scoping which users. Combined with AC-6 ("≥3 semanas de historial"), the implicit filter is users with ≥3 weeks of `producto_historial` rows. But should we additionally exclude:
   - Soft-deleted users? (yes, obviously)
   - Users with `last_login_at` older than N days? (what N?)
   - Unverified users?
   - Users in the freemium plan vs paid? (currently all users are freemium — N/A today, may matter later)

5. **Convert-to-list interaction with freemium 3-list limit**: AC-4 says "el usuario puede convertir el resumen en una lista nueva". If the user already has 3 active lists, what happens?
   - (a) Error 403 with the existing `FREEMIUM_LIMIT` code (consistent with `ShoppingListController::store`).
   - (b) Auto-archive the oldest list to make room (surprising).
   - (c) Allow exceeding the limit just for summary conversions (special case).
   - Recommend (a) for consistency, but confirm.

6. **AiUsageTracker quota ownership**: does `weekly_summary` count against the existing per-user shared quota (`AiOperation` enum, currently shared budget across all ops per Epic 5B refactor), or does it have its own pool?
   - Shared keeps the budget simple but means heavy autocomplete users may not get a weekly summary.
   - Separate isolates the pool but requires budget projection per op.

7. **Idempotency dedupe key**: if the cron retries Monday's job (provider outage, server restart), what guarantees one email per user per week?
   - Recommend: unique constraint on `(user_id, week_start_date)` in a `weekly_summaries` table. Confirm before designing.

8. **Localization**: is the email body Spanish-only for V1, or do we need i18n from the start? Existing mailables (`WaitlistConfirmationMail` etc.) — what language are they?

9. **Estacionalidad (AC-3)**: HU mentions "sugerencias basadas en época del año". How is "month → seasonal products" sourced?
   - (a) Hardcoded list per month (12 buckets, manually curated).
   - (b) Claude infers it from the prompt ("month is October, in Spain typical seasonal products are...").
   - (b) is simpler but increases prompt drift risk. Confirm.

10. **Unsubscribe token design**: signed JWT? Laravel `URL::signedRoute`? Single-use stored in DB? Per the security risk notes, this matters — if it's a stateless signed token, an attacker who intercepts one can replay it forever unless TTL'd.

11. **Failure isolation policy**: if 5% of users fail (Claude rate limit, mail bounce, prompt error), should the cron:
    - (a) Continue and log per-user failures, retry only the failed cohort tomorrow?
    - (b) Abort the entire run on first failure (fragile)?
    - (c) Continue and silently drop failures (worst, do not pick)?

12. **Stitch screen status**: is the "Resumen semanal" screen designed in the Stitch project today? If yes, fetch via MCP before S4 frontend. If no, do we generate it during this feature or block on a separate design task?

13. **Hard kill switch**: should there be a `config('ai.weekly_summary.enabled')` flag (or env var) to disable the entire feature without a deploy? Recommend yes.

14. **Cost cap per run**: should the cron stop dispatching new users once a per-run budget cap is reached (e.g. $X/week)? Or rely on the existing per-user `BudgetCap`?

15. **Test data for ≥3-weeks-history check**: factories/seeders to simulate 3 weeks of `producto_historial` will be needed for tests. This is implementation-detail but worth flagging.

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

**Required next step**: STEP 2 — PRD. The 15 open questions above must be answered (or explicitly deferred with rationale) before the PRD is considered complete. Several of them (mail provider, opt-in default, "in-app notification" mechanism) are decisions only the user/product owner can make.

## Transition

- Gate: S1 PENDING (awaiting user approval)
- Next Step: STEP 2 — PRD Writing
- Required Artifacts for Next Step: `01-scope.md`
