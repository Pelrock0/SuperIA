## Security Review: FEAT-REC-SAVE-PARTIAL

### Summary
- **Status**: PASS WITH NOTES
- **Reviewer**: claude-security-reviewer
- **Date**: 2026-05-04

The implementation closes the IDOR gap flagged in `02-prd.md §Risks` and `03-technical-design.md §Security` end-to-end (service-side combined `id + user_id + status=Active` query inside the transaction with `lockForUpdate`, plus negative tests at unit and HTTP level for AC-11, AC-12, AC-13, AC-14). Automated gates (composer audit, npm audit, manual SAST/secret scan) found no Critical/High findings. The single Low finding is the absence of an explicit `throttle:` middleware on the new `POST /weekly-summary/{summary}/save` endpoint — consistent with sibling endpoints in the same group (`/weekly-summary/dismiss`, `/weekly-summary/latest`) but still worth addressing as application-wide debt. None of the LLM Top 10 v2 controls are triggered by this feature: it makes **no new AI calls**; it only consumes a previously generated and sanitized `payload_json`.

---

### Automated Gates

| Gate | Command | Result |
|------|---------|--------|
| Deps audit (PHP) | `composer audit --no-dev --format=plain` | PASS — `No security vulnerability advisories found.` |
| Deps audit (Node, prod) | `npm audit --omit=dev` | 1 Moderate (`postcss <8.5.10`, GHSA-qx2v-qp2m-jg93). Below the High/Critical block threshold. **Not introduced by this feature.** |
| Deps audit (Node, full) | `npm audit` | 2 Moderate (`postcss`, `follow-redirects`). Below the High/Critical block threshold. **Not introduced by this feature.** |
| Secret scan | Manual regex (`gitleaks` not installed): `(?i)(api[_-]?key\|secret\|password\|token\|bearer\|sk-[a-z0-9]{20,})\s*[:=]\s*['"][A-Za-z0-9+/=_-]{12,}['"]` against the 5 backend files + 3 frontend files of the feature | PASS — 0 matches |
| SAST (PHP) | `vendor/bin/psalm --no-cache app/Http/Controllers/WeeklySummaryController.php app/Http/Requests/SaveWeeklySummarySelectionRequest.php app/Services/WeeklySummaryService.php app/Services/ListItemService.php` | 2 `InvalidArrayOffset` errors at `WeeklySummaryService.php:169-170` — confirmed pre-existing (`generateForUser`, commit `83ef439`, AI token tracking). **Not in the feature's diff.** No findings on the `saveSelection` / `createOrIncrement` / `save()` controller code. |
| Lockfile present | `git ls-files composer.lock package-lock.json` | PASS — both committed |
| `.env` not tracked | `git ls-files \| grep -E "^\.env$"` | PASS — empty result |

> Note: `gitleaks` and `trufflehog` are not installed on the dev machine. The secret scan above is a manual regex pass over the feature's diff; CI is expected to run a proper tool. Logged as tooling debt (out of scope for this feature).

---

### OWASP Top 10 2021 Findings

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| A01 | Broken Access Control | PASS | Summary ownership check (`WeeklySummaryService.php:240-242` → `abort(404)`) before opening the transaction; second ownership re-check after `lockForUpdate` (line 254-261). Target list validated by combined query (`id` + `user_id = auth()->id()` + `status = Active` + `lockForUpdate`) at lines 276-285 → `abort(404)` on miss. AC-11 covered by `tests/Feature/WeeklySummaryEndpointsTest.php::test_save_returns_404_for_other_users_summary` and `::test_save_returns_404_for_other_users_target_list`. AC-14 by `::test_save_returns_404_for_archived_target_list`. Route binding (`WeeklySummary $summary`) does NOT auto-scope to user, but the service layer enforces ownership before any state read or write — correct. No `Policy`/`Gate` used; consistent with the rest of the repo (service-level authorization). |
| A02 | Cryptographic Failures | N/A — feature does not introduce new credentials, secrets, or cryptographic operations. The JWT auth guard (`auth('api')`) is reused from existing infrastructure. |
| A03 | Injection | PASS | `whereRaw('LOWER(TRIM(name)) = ?', [$normalized])` at `ListItemService.php:95` uses positional binding — value cannot escape the binder. The `$normalized` value is `mb_strtolower(trim((string) $data['name']))`. Even if a payload `nombre` contained SQL meta-characters, it is bound, not interpolated. No other `whereRaw`/`DB::raw`/`selectRaw` introduced by this feature. Frontend rendering uses React JSX `{value}` (auto-escaped) for `product.nombre`, `product.reason`, `product.unidad_tipica`, `list.name`, `list.emoji`, `list.items_total` — no `dangerouslySetInnerHTML`, no `innerHTML`, no `eval`. Email Blade templates use `{{ }}` only (no `{!! !!}` in `resources/views/emails/weekly-summary.blade.php`). |
| A04 | Insecure Design | PASS WITH NOTES | The mutation of `payload_json` is irreversible (no undo); the PRD explicitly excludes "Soporte para deshacer guardado" — accepted by PO. Non-idempotency is mitigated by the pessimistic lock on the summary row (`whereKey()->lockForUpdate()->first()`, line 254): a second concurrent save sees the mutated payload and the original `selected_indices` are rejected with 422. Frontend disables the CTA while `isSaving=true`. The defensive `max:50` on `selected_indices` caps the per-request work. **Note**: no domain-event audit log of the save operation is emitted (intentional per `04-implementation-notes.md §Implementation Decisions #6`); for a destructive-by-design payload mutation an `ActivityLog` entry per saved item would be defensible — see Notes / Tech Debt. |
| A05 | Security Misconfiguration | PASS | New route is correctly placed inside `Route::middleware(['auth:api', JwtVersionCheck::class])->group(...)` at `routes/api.php:131`. No CORS change. Stateless JSON API — no cookie/CSRF surface added. The `OverflowException` handler at `WeeklySummaryController.php:77-81` returns a structured error code (`FREEMIUM_LIMIT`) and does not leak a stack trace. |
| A06 | Vulnerable Components | PASS | Composer audit clean. Npm audit reports 2 Moderate (postcss, follow-redirects); both pre-existing, neither imported by feature code. No new dependencies added by this feature. Lockfiles committed. |
| A07 | Auth Failures | N/A — no changes to authentication, password handling, MFA, sessions, or password reset. The route relies on the project's existing `auth:api` (JWT) guard. |
| A08 | Integrity Failures | PASS | No deserialization of untrusted input. `payload_json` is decoded via Laravel's JSON column cast (safe). No file uploads, no webhook ingestion, no JWT alg pinning concerns introduced. |
| A09 | Logging & Monitoring | PASS | No `Log::`/`logger()` calls in the feature's three backend files, so no risk of logging product names, list names, or token payloads. The PRD does not require an audit trail for this action; this is a deliberate non-goal (AC-only). The freemium 403 path is logged-as-error neither — same convention as `ShoppingListService::create`. |
| A10 | SSRF | N/A — no outbound HTTP from `saveSelection` or `createOrIncrement`. The Claude HTTP call is in `generateForUser` and is unchanged by this feature. |

---

### OWASP API Security Top 10 (2023) — distinct items

| ID | Status | Notes |
|----|--------|-------|
| API1 BOLA | PASS | Same controls as A01: per-row ownership verified inside the transaction. |
| API4 Unrestricted Resource Consumption | PASS WITH NOTES | `selected_indices` capped at `max:50` (`SaveWeeklySummarySelectionRequest.php:17`); typical N<10 from the prompt config. `new_list_name` capped at `max:80`. **No `throttle:` middleware** on the new route — see Required Changes / Tech Debt. |
| API6 Sensitive Business Flows | N/A — this is not a signup/checkout/comment/like flow; per-user freemium cap (3 active lists) is enforced at the data layer. |
| API9 Improper Inventory Management | PASS | Legacy `/convert-to-list` route was removed (not left behind). No debug/preview/swagger endpoints exposed. |

---

### OWASP LLM Top 10 v2 (2025)

> The feature itself makes **no new AI calls**. It consumes the previously generated and sanitized `payload_json` from a `WeeklySummary` row. The prompt sanitization (input → Claude) and response parsing live in `app/Services/WeeklySummaryService::generateForUser` and `app/Support/Ai/ClaudeClient::parseWeeklySummaryEntries`, both unchanged in this feature. Items below evaluate the residual risk introduced by **persisting** that AI-generated content into `list_items` and rendering it in the UI.

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| LLM01 | Prompt Injection | N/A — no new prompt is constructed from user input here. The `payload_json` is read-only data at this point. |
| LLM02 | Sensitive Information Disclosure | N/A — no new data is sent to a provider in this flow. |
| LLM03 | Supply Chain | N/A — no new model/dependency change. |
| LLM04 | Data & Model Poisoning | N/A — no training/RAG ingestion. |
| LLM05 | Improper Output Handling | PASS | The `payload_json` strings (`nombre`, `cantidad_tipica`, `unidad_tipica`, `categoria`) are persisted into `list_items.name/quantity/unit/category` and later rendered via React JSX (auto-escaped at `WeeklySummaryPage.jsx` lines 394, 406, 419-421 and downstream `ListDetailPage.jsx`). They are also bound (not interpolated) into `whereRaw('LOWER(TRIM(name)) = ?', [$normalized])`. `quantity` is cast to `(float)` in `WeeklySummaryService.php:305`. **No path where the model output reaches `eval`, `exec`, raw SQL, a shell, or `dangerouslySetInnerHTML`.** Stored XSS via a malicious-looking name produced by Claude is therefore not exploitable in the React surfaces inspected. Email Blade templates use `{{ }}` (escaped). The `nombre` field has no length cap at parse time (`ClaudeClient.php:344`); a very long name could bloat list rendering, but the column type and freemium item cap bound the impact (Low, not blocking — pre-existing). |
| LLM06 | Excessive Agency | N/A — no agent / tool-calling surface. |
| LLM07 | System Prompt Leakage | N/A — system prompt unchanged. |
| LLM08 | Vector & Embedding Weaknesses | N/A — no vector store. |
| LLM09 | Misinformation | N/A — UI already labels content as "IA Sugerencia" (`WeeklySummaryPage.jsx:304`) and the user explicitly opts in by selecting items before saving. |
| LLM10 | Unbounded Consumption | N/A — no token spend on the save path. |

---

### Cross-Cutting

- **Idempotency** — PASS with documented design choice. The endpoint is intentionally non-idempotent (a duplicate request is not a no-op): each call mutates `payload_json` and inserts/increments items. Two layers of mitigation are in place: (a) the frontend disables the CTA while `isSaving` (`WeeklySummaryPage.jsx:24, 91, 129`) and the sheet's confirm button while `isSubmitting` (`SaveTargetSheet.jsx:19, 295`); (b) the service serializes via `WeeklySummary::lockForUpdate()` (`WeeklySummaryService.php:254`) — the second concurrent transaction sees the mutated payload and the original indices are rejected with 422 (validated by `tests/Unit/Services/WeeklySummaryServiceTest.php::test_save_selection_rejects_out_of_range_indices`). This is the documented contract per `04-implementation-notes.md §Error Codes`. No `Idempotency-Key` header is required.
- **Rate Limiting** — FAIL (Low severity). The new route at `routes/api.php:131` has **no `throttle:` middleware**. Sibling routes in the same `auth:api` group (`/weekly-summary/dismiss`, `/weekly-summary/latest`) also lack throttle — this is a repository-wide pattern, not a feature-specific regression. There is no global API throttle in `bootstrap/app.php`. Impact in this feature: an authenticated abuser could attempt a burst of saves to fill a target list with items (bounded per-call by `max:50` indices and by the freemium cap of 3 lists × N items), or attempt a credential-stuffed brute-force against `WeeklySummary` IDs for IDOR (each blocked by the ownership check). Recommended fix: add `->middleware('throttle:30,1')` matching `/auth/refresh`. Logged as Low because: (a) the endpoint is authenticated, (b) per-user freemium cap bounds blast radius, (c) consistent with sibling endpoints — application-wide deficit rather than a feature gap.
- **Transactions** — PASS. Single `DB::transaction` (`WeeklySummaryService.php:252`) wraps: summary lock, index validation, target-list resolution (locked or freshly created), per-item upsert (each with its own `lockForUpdate` on the candidate row), payload mutation, status transition to `Actioned`, and counter sync. Rollback on any exception (validation, query, `OverflowException`) leaves no partial state. AC-15 covered structurally; the code-review's noted absence of a deterministic concurrency test is acknowledged but the lock placement is correct by inspection.

---

### Required Changes

| # | Severity | OWASP | File:Line | Issue | Required Fix |
|---|----------|-------|-----------|-------|--------------|
| 1 | Low | API4 / A04 | `routes/api.php:131` | New `POST /weekly-summary/{summary}/save` is registered without a `throttle:` middleware. Authenticated abuse remains bounded by the freemium item cap and by per-row locks, but a per-user limiter is application-best-practice for a write endpoint that runs a transaction with multiple `lockForUpdate` calls. | Add `->middleware('throttle:30,1')` (matching `/auth/refresh`) **OR** introduce a default API-group throttle in `bootstrap/app.php` to cover this and the sibling untrottled `weekly-summary/*` and `lists/*` routes. Non-blocking; logged as repo-wide debt. |

No Critical/High/Medium findings.

---

### Recommendation
- [ ] Approve
- [x] Approve with notes (Low only)
- [ ] Request changes (blocking)

**S5-SEC gate: PASS WITH NOTES.** The feature does not block release. The single Low finding (rate limiting) reflects a repository-wide pattern and may be addressed as a follow-up.

---

### Notes / Tech Debt

1. **Rate limiting application-wide** (Low) — many `auth:api` routes lack a `throttle:` middleware (`/weekly-summary/dismiss`, `/weekly-summary/latest`, `/lists`, `/lists/{list}/items`, etc.). Defer to a dedicated cross-cutting feature; do not block this one. Tracked above as Required Changes #1.
2. **Pre-existing Psalm errors** in `WeeklySummaryService.php:169-170` (`InvalidArrayOffset` on `$result['input_tokens']`/`$result['output_tokens']`) — introduced by commit `83ef439` (AI token tracking), unrelated to this feature. Out of scope for the S5-SEC gate.
3. **Secret-scan tooling** — `gitleaks`/`trufflehog` not installed on dev machine; CI is expected to run one. Manual regex pass clean over the feature's diff. Logged as tooling debt.
4. **Audit log of save action** (Informational, not Required) — `ListItemService::createOrIncrement` deliberately does not call `logActivity()` (asymmetry vs `create()`). Per `05-code-review.md §Maintainability` this is documented as intentional. Acceptable for a private-list operation; no security requirement is breached. If a future feature exposes the list to share tokens, revisit.
5. **`payload_json` length / character set of `nombre`** — `ClaudeClient.php:344` does `trim((string) $row['nombre'])` with no length or character-class restriction. Stored XSS is closed by output-side escaping (React JSX, escaped Blade); a defense-in-depth length cap (e.g. `mb_substr(..., 200)`) at parse time would be a good hardening step but is unrelated to this feature's scope.
6. **No deterministic concurrency test** for two-tab races. Lock placement is correct by inspection; PHPUnit cannot easily reproduce a real lock contention. The `out_of_range_indices` test exercises the same failure surface a losing-race transaction would hit. Acceptable.
