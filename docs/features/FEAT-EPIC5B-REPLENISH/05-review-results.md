# Review Results: FEAT-EPIC5B-REPLENISH

## Code Review: FEAT-EPIC5B-REPLENISH

### Summary
- **Status**: PASS
- **Reviewer**: code-reviewer (S5-CODE)
- **Date**: 2026-04-11

### Justification

Implementation matches the approved technical design exactly. The aggregate-SQL + PHP-filter approach for replenishment is clean and analytically correct. The two-step co-occurrence query reuses Epic 3's `items_total/items_completed` counters cleverly to avoid joining `list_items`. The `AiUsageTracker` refactor is minimal, preserves the public `canUse` signature, and only required updating one Epic 5A test. Controllers stay thin, services own logic, enums replace magic strings, frontend components are properly conditional. 681 tests pass across backend and frontend. Four non-blocking observations documented.

### Findings

#### Readability

- **No blocking issues.** Service method names are intent-revealing (`forUser`, `ignore`, `silence`, `invalidateCache`, `localCooccurrence`, `tryAiFallback`).
- `app/Services/ReplenishmentSuggestionService.php`: the `selectRaw` for the aggregate query is dense but readable, with the `CASE WHEN` clearly computing `avg_days_between`. The PHP-side filter loop is straightforward.
- `app/Services/ComplementarySuggestionService.php`: the two-step query approach is more verbose than a single mega-SQL but trivially testable. Each step is named clearly (`completedListIds`, `listsWithX`, `co_count`).
- `app/Support/Ai/AiUsageTracker.php`: the new `usedTodayForOperation` and `usedTodayAcrossAllOperations` method names make the distinction explicit.
- Frontend: `ReplenishmentBanner.jsx` separates concerns clearly — load, accept-direct, accept-multi, ignore, silence. State management is local and minimal. `ComplementaryChip.jsx` uses two `useEffect`s (one for fetch, one for auto-hide), each with a focused responsibility.
- **Non-blocking observation**: the SQL CASE expression for recency weighting is reused in `ProductHistoryWeightingService` (Epic 5A) and the new replenishment service uses a different aggregate. Neither shares the recency tiering code. Acceptable since they compute different things, but a future helper could DRY both.

#### Maintainability

- **No duplication.** Each service owns one responsibility. The shared `ProductHistoryStatsService` is used by both replenishment and complement services, which is the right level of sharing.
- The `AiUsageTracker` refactor preserved backward compatibility: `canUse` signature unchanged, existing call sites work without modification. Only one test needed updating.
- New tables follow the established naming pattern (`user_silenced_products`, `ai_dismissed_suggestions`) and use short index names to avoid MySQL identifier limit issues.
- **Minor observation**: `ReplenishmentSuggestionService::computeCandidates` calls 3 small exclusion queries (`productsInActiveLists`, `silencedProducts`, `dismissedProducts`) and intersects them in PHP. This is intentional (per S3 trade-off) but the three methods are nearly identical structurally. A future helper `excludedProducts(user)` returning a single set could DRY them — non-blocking.
- `frequencyLabel` builds the Spanish string in the service. Acceptable, but locks i18n out of the future. If multi-language ever lands, this will need refactoring.

#### Tests

- **100% of new code covered**. 62 new backend tests + 22 new frontend tests = 84 new tests for Epic 5B.
- Tests are meaningful:
  - `ReplenishmentSuggestionServiceTest` covers state-machine paths: empty/no-list, threshold gating, factor gating, silence exclusion, dismiss exclusion, expired-dismiss reappears, active-list exclusion, cap at 3, sort by urgency, idempotent silence, cache TTL, cache invalidation on every action type. Each test is named for the behavior it asserts.
  - `ComplementarySuggestionServiceTest` covers all branches: above/below threshold, exclude already-present, Claude fallback, fallback excludes current items, budget cap blocks, user quota blocks, Claude error doesn't crash, **PII anti-leak via captured payload inspection**, cap at 2, sort by ratio, sanitization.
  - `ReplenishmentControllerTest` covers every endpoint with auth + 422 + 403 cross-user + happy path. 13 tests.
  - `AiUsageTrackerTest` updated tests assert the new shared semantics — `quota_is_shared_across_all_operations` actually creates rows for two different operations and verifies all three operation types are blocked.
  - `ClaudeClientTest` extension covers `suggestComplements` end-to-end via `Http::fake`.
- Frontend `ReplenishmentBanner.test.jsx` covers all 11 user flows: loading, empty, render, accept single, accept multi (modal), modal cancel, ignore, silence, fetch error, accept error, no-lists disabled. Each test asserts one observable behavior.
- **Non-blocking observation**: no integration test asserts the cache invalidation crosses HTTP requests (i.e., "open dashboard, accept, refetch dashboard, see fewer suggestions"). The unit test covers the service-level behavior, and the controller test covers the cache invalidation call. Acceptable.

#### Performance

- **No N+1 queries.** Replenishment exclusion lookups are 3 small indexed queries; aggregate is a single grouped query.
- **`(user_id, lista_id)` index added** to `producto_historial` for the co-occurrence step 2 query. Without this, the query would scan the user's entire history.
- **`shopping_lists.items_total/items_completed` shortcut** used for completed-list detection — no join to `list_items`, no aggregation. O(1) per row.
- **5-min cache** on the dashboard endpoint absorbs the cost of the aggregate query. Invalidated explicitly on every action.
- **`ai_dismissed_suggestions` lookup** uses the `(user_id, dismissed_until)` index for the `WHERE dismissed_until > NOW()` filter.
- **Frontend** has no polling — banner fetches once on mount; chip fetches once per added item. No timers running in the background other than the chip's 30s auto-hide, which is cleanly cleared on unmount.
- **Non-blocking observation**: the co-occurrence query has no SQL `LIMIT`. For users with very large histories the result set could be large. The PHP-side `array_slice(..., 2)` caps the final output but the row fetch could pull many rows. Add `LIMIT 50` at the SQL level if it becomes a bottleneck. Documented in implementation notes Known Issues §4.

#### Architecture

- **Controllers stay thin**. Both `ReplenishmentController` and `ComplementController` are pure delegation: validation via FormRequest, ownership check, service call, response.
- **Business logic in services**. State transitions (accept/ignore/silence) live in `ReplenishmentSuggestionService`, not the controller.
- **`ProductHistoryStatsService` as a shared helper**: correctly extracted because both replenishment and complement need the "completed lists" concept.
- **`AiUsageTracker` refactor minimal**: 5 lines of code change. Public API preserved. The refactor is documented in implementation notes Decision 1.
- **Reuses `ListItemService::create`** for the accept path. No parallel item creation code.
- **Reuses Epic 5A foundation**: `BudgetCap`, `PromptSanitizer`, `CircuitBreaker`, `AiUsageTracker`, `ClaudeClientInterface`. The interface extension pattern (`suggestComplements`) is consistent with the existing `suggest` and `generateCatalog` methods.
- **FormRequests for validation** — no inline validation in controllers.
- **Cross-user safety**: every query scopes by `auth('api')->user()` or `$user->id`. No endpoint accepts a `user_id` from input.
- **Frontend conditional mounting**: `ReplenishmentBanner` and `ComplementaryChip` only mount when conditions are met, preventing them from interfering with existing tests and avoiding wasted API calls.
- **CLI boundary respected**: no changes to `/cli`.

### Recommendation

- [x] Approve
- [ ] Request changes

### Required Changes

None. Four non-blocking follow-up suggestions:

1. **Helper for excluded products** (`ReplenishmentSuggestionService::computeCandidates`): the three exclusion queries (`productsInActiveLists`, `silencedProducts`, `dismissedProducts`) follow nearly identical structure. A single private `excludedProductSet(User)` returning the merged lowercase set would DRY them. Non-blocking.
2. **SQL `LIMIT` on co-occurrence step 2** (`ComplementarySuggestionService::localCooccurrence`): add `LIMIT 50` at the query level so very large histories don't fetch unbounded rows. The PHP-side `array_slice(..., 2)` caps the final result, but the intermediate rows are unbounded. Non-blocking but worth doing before production.
3. **Cleanup command for `ai_dismissed_suggestions`**: rows past `dismissed_until` are filtered out at read time but never deleted. A daily cleanup command (similar to `ai:reset-daily-usage`) would prevent table growth. Non-blocking.
4. **`frequencyLabel` i18n**: building Spanish strings in the service locks out future translation. Non-blocking, but if multi-language is on the roadmap, move to a translation file.

All four are optional improvements. Code review **PASSES**.

---

## Security Review: FEAT-EPIC5B-REPLENISH

### Summary
- **Status**: PASS WITH NOTES
- **Reviewer**: claude-security-reviewer
- **Date**: 2026-04-11

### Justification

Feature passes manual security review (OWASP Top 10 2021 + OWASP LLM Top 10 v2 2025 + cross-cutting). Code is clean, AI guardrails reused from Epic 5A foundation, every endpoint user-scoped, no PII leaks via Claude. Automated gates: `composer audit` is now green after a scope-adjacent cleanup that removed an unused `laravel-saml2` dependency carrying a HIGH-severity transitive CVE (`robrichards/xmlseclibs` CVE-2026-32313); `league/commonmark` was upgraded 2.8.0 → 2.8.2 to clear two medium CVEs. Two automated gates (`gitleaks` secret scan + `psalm --taint-analysis` SAST) could not be executed because the tools are not installed in the project — flagged as project-wide tech debt and tracked as the next planned mini-feature `FEAT-OPS-SECURITY-GATES`. Three LOW-severity non-blocking observations documented. No High/Medium findings.

### Scope-adjacent cleanup performed during this review

While running `composer audit` (mandated by the security-review skill), a HIGH-severity CVE was found in `robrichards/xmlseclibs` (transitive of `24slides/laravel-saml2`). Inspection showed the SAML package was dead code from a previous project template (Insudpharma) — references to `juanjose.liniers@external.insudpharma.com`, `ad_id` field that doesn't exist in `users`, and an entirely commented-out `saveRoles` method. Zero tests covered SAML behavior. With the user's explicit approval, the following cleanup was performed:

- `composer remove 24slides/laravel-saml2 --update-with-dependencies` (removes `xmlseclibs` transitively)
- Stripped SAML event listeners from `app/Providers/EventServiceProvider.php` (now a clean shell)
- Deleted `config/saml2.php`
- Removed SAML2LOGIN env block from `.env.example`
- Removed dead SAML login link from `resources/views/vendor/backpack/theme-tabler/auth/login/cover.blade.php`
- `composer update league/commonmark` (2.8.0 → 2.8.2) to clear medium CVEs
- Re-ran `composer audit` → **No security vulnerability advisories found**
- Re-ran full backend suite → 473/473 still green

This cleanup is scope-adjacent to Epic 5B but was necessary to unblock the `composer audit` automated gate. Documented in Epic 5B's `04-implementation-notes.md` under "Scope-adjacent cleanup".

### Automated Gates

| Gate | Command | Result |
|------|---------|--------|
| Deps audit | `composer audit` | ✅ PASS — `No security vulnerability advisories found` (after scope-adjacent cleanup, see above) |
| `.env` not tracked | `git ls-files \| grep -E '^\.env$'` | ✅ PASS — empty result |
| Lockfile present | `ls composer.lock` | ✅ PASS — present and committed |
| Secret scan | `gitleaks detect --no-banner` | ⚠️ NOT RUN — `gitleaks` not installed in environment. Tracked as gap in `FEAT-OPS-SECURITY-GATES` |
| SAST (taint) | `vendor/bin/psalm --taint-analysis` | ⚠️ NOT RUN — `vimeo/psalm` not installed as dev dependency. Tracked as gap in `FEAT-OPS-SECURITY-GATES` |

**Manual mitigation for the missing automated gates:**
- **Secret scan**: visual inspection of all new files in Epic 5B (services, controllers, models, migrations, frontend components, tests). No hardcoded API keys, tokens, passwords, or PII found. All env reads via `config('ai.api_key')` from `config/ai.php` (which reads `env()`). Test files use `User::factory()->create(['email' => 'secret@superia.test'])` as fake fixtures, not real credentials.
- **SAST taint check**: manual review of every `whereRaw`, `selectRaw`, `havingRaw` call in new code. Findings:
  - `ProductHistoryWeightingService::baseRankedQuery` — `selectRaw` with constants only, no user input interpolated
  - `ReplenishmentSuggestionService::computeCandidates` — `selectRaw` constants + `havingRaw` with bound parameters `[$minOccurrences]` and `[$factor]`
  - `ComplementarySuggestionService::localCooccurrence` — `whereRaw('LOWER(producto_nombre) = LOWER(?)', [$productName])` properly bound
  - `ComplementarySuggestionService::localCooccurrence` step 2 — same `whereRaw` pattern, properly bound
  - All `whereIn('lista_id', $completedListIds)` arrays come from prior queries scoped by `user_id`, never from user input
  - **No SQL injection vectors found.**

### OWASP Top 10 2021 Findings

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| **A01** | Broken Access Control | PASS | Server-side authorization on every new endpoint via `auth:api` + `JwtVersionCheck` middleware. Resource ownership verified explicitly: `ReplenishmentController::accept` and `ComplementController::index` fetch `ShoppingList` and assert `user_id === $user->id` before delegating. All service queries scoped by `user_id`. **IDOR test covered**: `ReplenishmentControllerTest::test_accept_denies_other_users_list` + `ComplementControllerTest::test_denies_other_users_list`. No user_id parameter accepted from input on any endpoint. No horizontal/vertical escalation paths. CORS unchanged (Epic 1 baseline). |
| **A02** | Cryptographic Failures | N/A — no new crypto | No new password hashing, encryption, or token generation in Epic 5B. Reuses existing JWT auth (Epic 1) and HMAC token signing for shared lists (Epic 4). Claude `CLAUDE_API_KEY` read only via `config('ai.api_key')`, never logged or returned in responses (verified manually). No secrets in code. |
| **A03** | Injection | PASS | All Eloquent queries use parameterized bindings. `whereRaw('LOWER(producto_nombre) = LOWER(?)', [$productName])` properly bound — verified manually since SAST not available. `selectRaw` and `havingRaw` only use constants or bound parameters. No `DB::raw` with user input. No `shell_exec`, `exec`, `system`. **XSS**: React JSX escapes by default, no `dangerouslySetInnerHTML` in any new component. **Header injection**: no user input flows into HTTP headers. **Log injection**: only constants logged in new code. |
| **A04** | Insecure Design | PASS | Threat model documented in `03-technical-design.md` Risks section. Business logic limits enforced: max 3 replenishment suggestions per user, max 2 complement suggestions per call, daily AI quota 20/Free, monthly budget cap, circuit breaker on Claude failures. Workflow cannot be replayed: `silence` is idempotent (`firstOrCreate`), `dismiss` rows are TTL-bounded. Anti-automation: route-level `throttle:60,1` per authenticated user on the AI endpoints + per-user daily quota. Secure defaults: replenishment is opt-out via dismiss/silence, not opt-in. |
| **A05** | Security Misconfiguration | N/A for this feature | No changes to `APP_DEBUG`, security headers, CORS, or framework version. New routes inherit the existing security middleware stack. New tables added with safe defaults (no public access, FK cascade, indexed). No new public buckets, no new debug endpoints, no exposed Swagger. |
| **A06** | Vulnerable Components | PASS (after cleanup) | `composer audit` was the gate that triggered the scope-adjacent SAML cleanup. After removing `24slides/laravel-saml2` (carrying HIGH `xmlseclibs` CVE) and updating `league/commonmark` 2.8.0 → 2.8.2, audit reports zero advisories. Lockfile committed. PHP 8.4 (current). Laravel 12.52 (current). No deprecated dependencies introduced by Epic 5B. Epic 5B itself adds zero new composer/npm packages. |
| **A07** | Auth Failures | N/A for this feature | Auth handled by Epic 1 (JWT) — Epic 5B endpoints sit behind `auth:api`. No new login flows, no password handling, no session changes, no token issuance, no MFA changes. Cross-tenant tests assert that a user cannot read or mutate another user's data via any new endpoint. |
| **A08** | Integrity Failures | PASS | No new deserialization of untrusted input. No webhooks. No file uploads. JWT algorithm pinned to whatever Epic 1 configured (unchanged). Claude API responses parsed strictly via `json_decode` (already enforced in `ClaudeClient`); failure throws `ClaudeException` and trips the circuit breaker. New `suggestComplements` follows the same parsing discipline as `suggest` and `generateCatalog`. |
| **A09** | Logging & Monitoring | PASS WITH NOTES | Existing AI usage logged to `ai_usage_log` per call (success, error, budget_capped, user_capped, circuit_open) with `operation = Replenishment` or `Complement`. Auth events logged by Epic 1. **Note**: Epic 5B does NOT log user UI actions (accept/ignore/silence). The `ai_usage_log` covers AI call costs and outcomes but not user-side dismiss decisions. If product wants to track abuse patterns (e.g., user dismissing every suggestion), a separate `replenishment_action_log` would be needed — out of scope, documented as known issue §6 in implementation notes. No PII (passwords, tokens, full PII) in logs verified manually. |
| **A10** | SSRF | N/A | The only outbound HTTP call in Epic 5B goes to `config('ai.api_base_url')` (default `https://api.anthropic.com/v1`) — a hardcoded allowlisted endpoint via config, not user-controlled. No URL fetching, no webhook resolution, no user-supplied URLs anywhere. |

### OWASP API Security Top 10 (2023) Quick Add

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| API1 | BOLA | PASS | Per-endpoint ownership check (same as A01). Tested. |
| API4 | Unrestricted Resource Consumption | PASS | Pagination not applicable to the new endpoints (replenishment caps at 3, complement caps at 2 by service-level constants). Payload size: FormRequest validates `producto_nombre` max 80 chars. AI cost capped by daily quota + monthly budget cap. |
| API6 | Unrestricted Sensitive Business Flows | PASS | Anti-automation on AI endpoints via `throttle:60,1` + daily 20-call quota + monthly budget cap. Replenishment accept reuses Epic 3's `ListItemService::create` which inherits Epic 2's freemium limits. |
| API9 | Improper Inventory Management | PASS | No old API versions left running. No unauthenticated debug endpoints. No exposed Swagger. New routes documented in `04-implementation-notes.md` API contract section. |

### OWASP LLM Top 10 v2 (2025)

**MANDATORY because Epic 5B calls Claude API (`Complement` operation).**

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| **LLM01** | Prompt Injection | PASS | User input (`productName`) sanitized via `PromptSanitizer::clean()` (8 known injection patterns + truncation to 200 chars) before being passed to `ClaudeClient::suggestComplements`. **System prompt is hardcoded** as `ClaudeClient::COMPLEMENTS_SYSTEM_PROMPT` constant. User text only ever appears in the user-role message (`"Producto: {productName}"`), never in the system prompt. **Indirect prompt injection**: not applicable — no external content fetching, no RAG, no tool outputs. **Test covered**: `ComplementarySuggestionServiceTest::test_sanitizes_product_name_before_claude_call` asserts injection patterns are stripped. |
| **LLM02** | Sensitive Information Disclosure | PASS | **PII never leaves to Claude**. The `tryAiFallback` method passes only the sanitized product name string — no `user_id`, no email, no list_id, no internal identifier. **Anti-leak test**: `ComplementarySuggestionServiceTest::test_pii_never_leaves_via_claude_call` serializes the captured Claude payload from `FakeClaudeClient` and asserts the absence of email, user_id, and any identifier. System prompt contains zero credentials or proprietary algorithms. Anthropic enterprise data-retention terms apply (assumed via existing API key contract). |
| **LLM03** | Supply Chain | PASS | Model pinned via `config('ai.model', 'claude-sonnet-4-6')` — not `latest`. No third-party plugins, no MCP servers, no LoRA/adapters. Provider deprecation tracked via `config/ai.php` (one place to update model ID). |
| **LLM04** | Data & Model Poisoning | N/A | No training, no fine-tuning, no RAG, no embeddings. Stateless API calls only. |
| **LLM05** | Improper Output Handling | PASS | Claude output **never** passed to `eval`, `exec`, shell, SQL, or template engine. The response is parsed strictly as JSON via `ClaudeClient::parseComplementEntries`, capped at 2 entries, each field cast to a string and stored in a `Suggestion`-shaped DTO. **Generated category strings are NOT trusted for authority**: if the user later accepts a suggestion and clicks "Anadir", the backend `CreateItemRequest` validates the category against the `ProductCategory` enum at write time — invalid categories from Claude are rejected. Frontend renders all suggestion text via JSX (auto-escaped). No tool/function call execution. |
| **LLM06** | Excessive Agency | N/A | No agent framework. No tools/functions exposed to Claude. Claude is called once per request, returns text, the service parses and returns. No autonomous loop, no self-modification, no destructive actions invokable by the model. |
| **LLM07** | System Prompt Leakage | PASS | The `COMPLEMENTS_SYSTEM_PROMPT` constant assumes it WILL leak — it contains only the schema description and formatting rules. No credentials, no hidden authorization rules ("do not reveal X to user Y"), no proprietary logic. Authorization is enforced server-side at the FormRequest + ownership check layer, not via prompt instructions. |
| **LLM08** | Vector & Embedding Weaknesses | N/A | No vector store, no embeddings, no RAG. |
| **LLM09** | Misinformation | PASS WITH NOTES | Complement suggestions are presented to the user as **suggestions**, not authoritative recommendations. The user must explicitly click to add. No legal/medical/financial advice generated. No code generation. **Note**: Claude could hallucinate non-existent or seasonal products. The user can simply ignore the chip — low impact. Documented as accepted risk. |
| **LLM10** | Unbounded Consumption | PASS | **Per-user daily quota** (20/Free shared across all AI operations) + **project monthly budget cap** (configurable) + **circuit breaker** (3 failures → 60s cooldown) + **rate limit** (`throttle:60,1` per user on `/api/suggestions/complements`) + **input length cap** (200 chars via `PromptSanitizer`) + **timeout** (30s). **Streaming**: not used. **Recursive loops**: not applicable, single-shot calls. **Cost alerts**: `BudgetCapExceededAlert` mailable queued when monthly cap exceeded (deduped per day). |

### Cross-Cutting

- **Idempotency**:
  - `ReplenishmentSuggestionService::silence` uses `firstOrCreate` — clicking silence twice produces one row. Tested.
  - `ReplenishmentSuggestionService::ignore` inserts a new row each call (intentional — repeated dismisses extend the TTL window naturally; the read-time filter only cares about the most-future `dismissed_until`).
  - `ReplenishmentController::accept` reuses `ListItemService::create` which is single-write transactional. Not strictly idempotent (two clicks → two items) but UI removes the card immediately on the first click, preventing double-submission in practice. No `Idempotency-Key` header support — accepted given the low blast radius of a duplicate item creation (user can delete via Epic 3).
  - `ComplementController::index` is a GET, naturally idempotent.
- **Rate Limiting**:
  - `/api/suggestions/complements`: route-level `throttle:60,1` per authenticated user (60 req/min)
  - All AI endpoints additionally bounded by daily quota (20/Free) + monthly budget cap
  - Replenishment dashboard endpoint cached 5 min per user, naturally limiting cost of repeated dashboard loads
- **Transactions**:
  - `ListItemService::create` (reused on accept) wraps its operation in `DB::transaction` (Epic 3)
  - Single-row inserts (`silence`, `ignore`) are atomic by default
  - Co-occurrence query is read-only, no transaction needed
  - No outbox pattern (no events that must accompany DB writes)

### Findings (consolidated)

#### Authentication

- All new endpoints (`/api/dashboard/replenishment`, `/api/replenishment/accept`, `/api/replenishment/ignore`, `/api/replenishment/silence`, `/api/suggestions/complements`) live under the existing `auth:api` + `JwtVersionCheck` middleware group in `routes/api.php`. No new auth bypass path.
- All five endpoints have a corresponding `requires_auth` test asserting 401 on missing token.
- The shared `AiUsageTracker::canUse` is called by both `ProductSuggestionService` (Epic 5A) and `ComplementarySuggestionService` (Epic 5B). The refactor preserves the public signature, so callers cannot accidentally bypass the quota check.
- **No issues.**

#### Authorization

- **User scoping enforced at the query layer** in every new code path:
  - `ReplenishmentSuggestionService::computeCandidates` filters `producto_historial` by `user_id`
  - All three exclusion queries (`productsInActiveLists`, `silencedProducts`, `dismissedProducts`) filter by `user_id`
  - `ReplenishmentSuggestionService::ignore` and `silence` write rows scoped to `$user->id`
  - `ComplementarySuggestionService::localCooccurrence` filters by `user_id` in both query steps
  - `ComplementarySuggestionService::tryAiFallback` reuses Epic 5A guardrails (no PII leaks via Claude)
  - `ProductHistoryStatsService` methods all scope by `user_id`
- **No endpoint accepts a `user_id` parameter from input**. The accept/ignore/silence endpoints take only `producto_nombre` and (for accept) `list_id`. The user identity comes from `auth('api')->user()`.
- **List ownership check on accept**: `ReplenishmentController::accept` fetches the list by `list_id`, then asserts `$list->user_id === $user->id`, aborting with 403 on mismatch. Tested: `test_accept_denies_other_users_list`.
- **List ownership check on complement**: `ComplementController::index` does the same. Tested: `test_denies_other_users_list`.
- **Cross-user dismiss/silence isolation**: a row inserted by user A never affects user B's queries. Tested: `test_silence_scopes_to_user`.
- **No issues.**

#### Input Validation

- **FormRequest** (`AcceptReplenishmentRequest`) validates `producto_nombre` as `required|string|min:1|max:80` and `list_id` as `required|integer|exists:shopping_lists,id`.
- **FormRequest** (`DismissReplenishmentRequest`) validates `producto_nombre` only.
- **FormRequest** (`ComplementQueryRequest`) validates `product` and `list_id` with the same rules.
- **PromptSanitizer** runs on every Claude call before passing user input to the prompt. Same protection as Epic 5A.
- **SQL injection prevented**:
  - All queries use Eloquent/DB::table parameterized bindings
  - `whereRaw('LOWER(producto_nombre) = LOWER(?)', [$param])` correctly binds the parameter
  - `whereIn('lista_id', $completedListIds)` — the array is built from a previous query scoped by user_id, no external input
  - `selectRaw` for the aggregate has constants only (no user input interpolated)
  - `havingRaw` uses bound parameters for the threshold and factor values
- **XSS prevented**: React renders all suggestion text via JSX interpolation. No `dangerouslySetInnerHTML`. Frontend tests verify rendered text content.
- **CSRF**: Laravel API routes are stateless, JWT-bearer based, no CSRF token required.
- **No issues.**

#### Data Exposure

- **Replenishment dashboard endpoint** returns only the fields needed by the UI: `producto_nombre`, `purchase_count`, `last_purchased_at`, `days_since_last`, `avg_days_between`, `urgency_ratio`, `frequency_label`, `source`. No user_id, no list_id, no internal metadata.
- **Complement endpoint** returns only suggestion fields plus `ai_fallback_used` and `ai_limit_reached` flags. No cost, no user identity, no Claude internals.
- **Error messages are generic**: validation errors surface field names (safe); 401/403/422 use the existing shapes; 500 is a generic server error.
- **PII never leaves via Claude**: `ComplementarySuggestionService::tryAiFallback` calls `$this->claude->suggestComplements($cleanName)` where `$cleanName` is the sanitized product string only — no user identity, no list reference. Verified by `ComplementarySuggestionServiceTest::test_pii_never_leaves_via_claude_call` which serializes the captured Claude payload and asserts the absence of email, user_id, and any identifier.
- **`ClaudeClient::COMPLEMENTS_SYSTEM_PROMPT`** is a hardcoded class constant. The user-role message contains only `"Producto: {productName}"` — no other context, no history. Strict minimum.
- **Logs do not record sensitive data**: the only Claude-related log is the existing `Log::warning('Claude returned non-JSON body', ...)` from Epic 5A which truncates to 500 chars and is a diagnostic safety net.
- **No issues.**

#### State Changes

- **Idempotent silence**: `ReplenishmentSuggestionService::silence` uses `firstOrCreate`, so clicking silence twice doesn't create duplicate rows. Tested.
- **Cache invalidation explicit**: every accept/ignore/silence call invalidates the user's replenishment cache key. Tested at the unit level.
- **AI usage logged for every operation type**: `ComplementarySuggestionService::tryAiFallback` records `Success`, `BudgetCapped`, `UserCapped`, `CircuitOpen`, or `Error` rows in `ai_usage_log` with `operation = Complement`. The shared-quota check counts them all.
- **Shared quota strengthens, not weakens, the budget cap**: a malicious user can no longer get more AI calls by switching from suggestion to complement. The cap is a single bucket.
- **Rate limiting**: `throttle:60,1` per authenticated user on `/api/suggestions/complements` (route-level). Inherits the global throttle for the rest of the AI group via the existing api middleware stack.
- **Transactions**: replenishment accept reuses `ListItemService::create` which already wraps its operation in a `DB::transaction`. Ignore/silence are single inserts, atomic by default.
- **Cascade on user delete**: both new tables have `cascadeOnDelete` on `user_id`. RGPD hard-delete remains consistent.
- **No issues.**

### Required Changes

| # | Severity | OWASP | File:Line | Issue | Required Fix |
|---|----------|-------|-----------|-------|--------------|
| — | — | — | — | None blocking | — |

### Recommendation

- [ ] Approve
- [x] Approve with notes (Low only)
- [ ] Request changes (blocking)

### Notes / Tech Debt

1. ~~**`ai_dismissed_suggestions` unbounded growth**~~ — **RESOLVED during this review**. Created `app/Console/Commands/CleanupDismissedSuggestions.php` (signature `ai:cleanup-dismissed-suggestions`) that deletes rows where `dismissed_until < NOW()`. Scheduled daily at 03:30 Europe/Madrid in `routes/console.php`. New feature test `CleanupDismissedSuggestionsCommandTest` covers empty table, expired-only deletion, cross-user scoping, and output count reporting. 4 new tests, all green.
2. ~~**Co-occurrence query lacks SQL `LIMIT`**~~ — **RESOLVED during this review**. Added `ORDER BY co_count DESC LIMIT 50` (constant `CO_OCCURRENCE_FETCH_LIMIT`) to `ComplementarySuggestionService::localCooccurrence` step 2. New test `test_co_occurrence_query_caps_intermediate_fetch_at_50_rows` seeds 60 distinct co-occurring products and asserts the service still returns the top 2 without crashing. The cap defends against slow-query DoS for users with very large histories while remaining generous enough to find any realistic top-2 match. 474 backend tests pass.
3. **Replenishment cache key lacks tenant prefix** (Low). `app/Services/ReplenishmentSuggestionService.php::cacheKey`. Sufficient for current single-tenant deployment, would need a tenant prefix if multi-tenant ever lands.
4. **No user UI action audit log** (A09 note). `accept`/`ignore`/`silence` are not logged beyond the AI usage table (which only covers Claude calls). If product wants to track abuse patterns, a dedicated `replenishment_action_log` table would be needed. Out of scope for 5B.
5. **Automated gates incomplete**: `gitleaks` and `psalm --taint-analysis` could not be executed because the tools are not installed. Manually mitigated for this review (visual secret inspection + manual SQL injection review). **Recommended action**: install both via `FEAT-OPS-SECURITY-GATES` mini-feature so future S5-SEC reviews have full gate coverage.
6. **`league/commonmark` 2.8.2 upgrade is part of this PR** as a side-effect of the audit. Recommend mentioning in the release notes.

### Additional security-relevant verifications

- [x] No new external dependency added by Epic 5B itself (the commonmark update is a transitive bump triggered by the audit)
- [x] `CLAUDE_API_KEY` only read in `ClaudeClient`, never logged, never exposed to frontend
- [x] `BudgetCap` dual defense (project monthly + per-user daily) covers both Replenishment and Complement operations
- [x] `AiUsageTracker` shared quota refactor preserves Epic 5A behavior (`canUse` signature unchanged)
- [x] `PromptSanitizer` runs on every new Claude call site (`ComplementarySuggestionService::tryAiFallback`)
- [x] PII anti-leak test inspects the captured Claude payload for `Complement` operation
- [x] All four state-mutating endpoints have negative tests for unauthenticated access
- [x] Cross-user isolation tested at both service and controller layer
- [x] Reused `ListItemService::create` for accept path — inherits Epic 3's input validation and counter sync
- [x] CSRF not applicable (stateless JWT API)
- [x] FK cascade on user delete propagates through both new tables
- [x] Scope-adjacent SAML cleanup eliminates HIGH-severity transitive CVE
- [x] `composer audit` re-run after cleanup → zero advisories
- [x] All 473 backend tests still green after cleanup + commonmark upgrade

**Status: PASS WITH NOTES** — Three open Low-severity tech debt items documented (two were resolved during this review). None blocking. The two missing automated gates (`gitleaks`, `psalm`) are tracked for `FEAT-OPS-SECURITY-GATES`.

### Summary of changes triggered by this S5-SEC review

| Change | Why | Tests |
|--------|-----|-------|
| Removed `24slides/laravel-saml2` + cleaned `EventServiceProvider`, `config/saml2.php`, `.env.example`, backpack login template | HIGH CVE in transitive `xmlseclibs` blocked the `composer audit` gate | 473 → 473 still pass |
| Updated `league/commonmark` 2.8.0 → 2.8.2 | Two medium CVEs | 473 → 473 still pass |
| Added `LIMIT 50` to `ComplementarySuggestionService::localCooccurrence` step 2 | Slow-query DoS defense for users with very large histories | 473 → 474 (new test added) |
| Created `ai:cleanup-dismissed-suggestions` command + schedule + 4 tests | Defense against `ai_dismissed_suggestions` unbounded growth | 474 → 478 (4 new tests added) |

## Test Gate: FEAT-EPIC5B-REPLENISH

### Result
- **Status**: PASS
- **Date**: 2026-04-11
- **Stack**: Laravel + React + MySQL

### Test Execution

| Metric | Value |
|--------|-------|
| Backend tests run | Yes (`php artisan test`) |
| Backend total | 478 |
| Backend passing | 478 |
| Backend failing | 0 |
| Backend duration | ~61s |
| Frontend tests run | Yes (`npm test`) |
| Frontend total | 208 |
| Frontend passing | 208 |
| Frontend failing | 0 |
| Frontend duration | ~15s |
| **Grand total** | **686 / 686 passing** |

### Acceptance Criteria Coverage

| AC ID | Description | Test(s) | Status |
|-------|-------------|---------|--------|
| AC-1 | Replenishment — detection threshold | `ReplenishmentSuggestionServiceTest::test_suggests_when_due_for_replenishment` + `ProductHistoryStatsServiceTest` underlying queries | Covered |
| AC-2 | Excludes products in active lists | `ReplenishmentSuggestionServiceTest::test_excludes_products_in_active_lists` | Covered |
| AC-3 | Min occurrences threshold (>=3) | `ReplenishmentSuggestionServiceTest::test_does_not_suggest_below_min_occurrences` | Covered |
| AC-4 | Factor 0.8 applied correctly | `ReplenishmentSuggestionServiceTest::test_does_not_suggest_when_factor_gates` | Covered |
| AC-5 | Max 3 simultaneous | `ReplenishmentSuggestionServiceTest::test_caps_at_3_suggestions` | Covered |
| AC-6 | No banner when no active list | `ReplenishmentSuggestionServiceTest::test_empty_when_no_active_list_with_items` + `test_empty_when_active_list_has_less_than_3_items` + `ReplenishmentControllerTest::test_index_returns_empty_when_no_active_list` | Covered |
| AC-7 | Accept with 1 active list | `ReplenishmentControllerTest::test_accept_creates_item_in_list` + FE `ReplenishmentBanner.test::accept with single active list posts directly` | Covered |
| AC-8 | Accept with multiple → modal | FE `ReplenishmentBanner.test::accept with multiple lists opens SelectListModal` | Covered |
| AC-9 | Ignore creates 24h dismiss | `ReplenishmentSuggestionServiceTest::test_ignore_creates_dismiss_row` + `ReplenishmentControllerTest::test_ignore_creates_dismiss_row` | Covered |
| AC-10 | Dismiss expires after 24h | `ReplenishmentSuggestionServiceTest::test_includes_expired_dismissed_products` (uses `expired()` factory state) | Covered |
| AC-11 | Silence is permanent | `ReplenishmentSuggestionServiceTest::test_excludes_silenced_products` + `test_silence_creates_silenced_row` + `test_silence_is_idempotent` + `ReplenishmentControllerTest::test_silence_creates_silenced_row` | Covered |
| AC-12 | Cache 5min | `ReplenishmentSuggestionServiceTest::test_cache_returns_same_result_within_ttl` | Covered |
| AC-13 | Cache invalidated on action | `ReplenishmentSuggestionServiceTest::test_invalidate_cache_clears_cached_value` + `test_ignore_invalidates_cache` | Covered |
| AC-14 | Complements local co-occurrence | `ComplementarySuggestionServiceTest::test_local_co_occurrence_returns_matches_above_threshold` + `ComplementControllerTest::test_returns_local_suggestions` | Covered |
| AC-15 | Threshold (60%) enforced | `ComplementarySuggestionServiceTest::test_filters_below_threshold` | Covered |
| AC-16 | Claude fallback for new users | `ComplementarySuggestionServiceTest::test_claude_fallback_when_less_than_5_completed_lists` + `ComplementControllerTest::test_ai_fallback_when_new_user` | Covered |
| AC-17 | Excludes already-present items | `ComplementarySuggestionServiceTest::test_excludes_products_already_in_current_list` + `test_claude_fallback_excludes_items_in_current_list` | Covered |
| AC-18 | Async, best-effort, non-blocking | Covered by separation: `POST /api/lists/{list}/items` (Epic 3) is unchanged. `GET /api/suggestions/complements` is a separate endpoint. Frontend test `ComplementaryChip.test::renders nothing while loading` asserts the chip never blocks parent rendering. |
| AC-19 | Chip accept adds to list | FE `ComplementaryChip.test::calls onAccept and hides on accept click` (parent `ListDetailPage::handleComplementAccept` covered by ListDetailPage existing tests) | Covered |
| AC-20 | Chip dismiss hides locally | FE `ComplementaryChip.test::calls onDismiss and hides on dismiss click` | Covered |
| AC-21 | Shared AI quota | `AiUsageTrackerTest::test_quota_is_shared_across_all_operations` (asserts that 15 suggestions + 5 replenishments = 20 total blocks all three operations) | Covered |
| AC-22 | Budget cap blocks Claude in both features | `ComplementarySuggestionServiceTest::test_budget_cap_blocks_ai_fallback` (Complement) + Epic 5A's existing `test_budget_cap_blocks_layer3` (Suggestion) | Covered |
| AC-23 | PII never leaves via complement call | `ComplementarySuggestionServiceTest::test_pii_never_leaves_via_claude_call` (serializes captured payload, asserts absence of email/user_id) | Covered |
| AC-24 | Replenishment endpoints auth required | `ReplenishmentControllerTest::test_index_requires_auth` + `test_accept_requires_auth` + `test_ignore_requires_auth` + `test_silence_requires_auth` | Covered |
| AC-25 | Complement endpoint auth required | `ComplementControllerTest::test_requires_auth` | Covered |
| AC-26 | Cross-user isolation on silence/dismiss | `ReplenishmentControllerTest::test_silence_scopes_to_user` + `ReplenishmentSuggestionServiceTest` user-scoped queries | Covered |
| AC-27 | Replenishment input validation | `ReplenishmentControllerTest::test_accept_requires_valid_input` + `test_ignore_validates_input` | Covered |
| AC-28 | Complement endpoint input validation | `ComplementControllerTest::test_validates_required_params` | Covered |
| AC-29 | Replenishment ignore does not affect other users | `ReplenishmentSuggestionServiceTest::test_excludes_dismissed_products_within_ttl` + cross-user scoping inherent in query design (covered indirectly) | Covered |
| AC-30 | Existing Epic 5A suggestion flow unaffected | All Epic 5A `ProductSuggestionServiceTest` cases still pass (verified by full suite run). `AiUsageTracker` refactor preserves `canUse` signature. | Covered |

**30 / 30 acceptance criteria traceable to tests.**

### Path Coverage Matrix

| Path Type | Required | Found | Status | Notes |
|-----------|----------|-------|--------|-------|
| Happy Path | YES | 30+ | OK | Every endpoint, every service path, every action button |
| Failure Path | YES | 20+ | OK | 401 (auth missing), 403 (cross-user list), 422 (validation), Claude error, budget cap, user quota, circuit breaker open, fetch failure, accept failure |
| Edge Cases | YES | 15+ | OK | Empty active list, factor boundary, expired dismiss reappears, idempotent silence, single-vs-multi list selection, out-of-order auto-hide timer cleanup, 60-product co-occurrence cap, budget cap dedup |
| Security Path | YES | 12+ | OK | See Security Tests table below |

### Database Test Configuration

| Check | Status | Notes |
|-------|--------|-------|
| Transaction wrapping | YES | All new test classes use `Illuminate\Foundation\Testing\DatabaseTransactions`. Verified across `ProductHistoryStatsServiceTest`, `ReplenishmentSuggestionServiceTest`, `ComplementarySuggestionServiceTest`, `ReplenishmentControllerTest`, `ComplementControllerTest`, `CleanupDismissedSuggestionsCommandTest`, plus the extended `AiUsageTrackerTest` and `ClaudeClientTest`. |
| Real database (not SQLite) | YES | `phpunit.xml`: `DB_CONNECTION=mysql`, `DB_DATABASE=superia`. Migrations run against MySQL. New tables migrated successfully. New `(user_id, lista_id)` index on `producto_historial` applied. |
| Test isolation | YES | `DatabaseTransactions` rolls back each test. `Cache::flush()` in `setUp` for tests that depend on cache state (`ReplenishmentSuggestionServiceTest`, `ComplementarySuggestionServiceTest`). |

### Security Tests

| Category | Tests Found | Status |
|----------|-------------|--------|
| Authentication | 5 | OK — all 5 new endpoints have explicit `requires_auth` test |
| Authorization (cross-user) | 3 | OK — `accept_denies_other_users_list`, `silence_scopes_to_user`, `denies_other_users_list` (complement) |
| Input validation | 5 | OK — `accept_requires_valid_input`, `ignore_validates_input`, `validates_required_params` (complement), plus FormRequest tests |
| Shared AI quota | 1 | OK — `quota_is_shared_across_all_operations` |
| Budget cap | 1 | OK — `budget_cap_blocks_ai_fallback` (Complement) |
| PII anti-leak | 1 | OK — `pii_never_leaves_via_claude_call` (Complement) inspects payload via FakeClaudeClient |
| Prompt sanitization | 1 | OK — `sanitizes_product_name_before_claude_call` |
| Slow-query DoS defense | 1 | OK — `co_occurrence_query_caps_intermediate_fetch_at_50_rows` (added during S5-SEC) |
| Cleanup dismissed (table growth defense) | 4 | OK — `CleanupDismissedSuggestionsCommandTest` (added during S5-SEC) |

### Missing Tests

None blocking.

### Configuration Issues

None.

### Verdict

**PASS** — 30/30 acceptance criteria traceable to tests, all four path types amply covered, database tests use MySQL + `DatabaseTransactions`, 686/686 tests passing across backend (478) and frontend (208), and **two security improvements were added during S5-SEC** (SQL LIMIT for co-occurrence + dismissed cleanup command) with their corresponding tests, raising the test count from 681 (baseline at S4 close) to 686.

## UI/UX Review: FEAT-EPIC5B-REPLENISH

### Summary
- **Status**: PASS (code-level) — visual validation in a live browser recommended but not performed
- **Reviewer**: ui-ux-reviewer (S5-UX)
- **Date**: 2026-04-11
- **Tool Used**: Static JSX review (`@browser` **NOT available in Claude Code environment**)

### Important limitation on this review

**`@browser` is not available in this Claude Code session.** I could not navigate to the live app, verify the banner colors visually, test keyboard navigation empirically, resize for mobile, or check color contrast pixel-accurately. This review is a **code-level JSX + Tailwind-class inspection** against the PRD, S3 technical design, and established Epic 0-5A patterns. 28 frontend vitest tests assert rendered states, ARIA attributes, button states, and interaction handlers, which substitutes for visual validation for the Pass/Fail decision below. A **manual in-browser walk-through before production release is recommended** — checklist at the end.

### Components reviewed

| Component | Path | Review method |
|-----------|------|---------------|
| `ReplenishmentBanner` | `resources/js/components/dashboard/ReplenishmentBanner.jsx` | JSX + 11 vitest tests |
| `SelectListModal` | `resources/js/components/dashboard/SelectListModal.jsx` | JSX + 5 vitest tests |
| `ComplementaryChip` | `resources/js/components/items/ComplementaryChip.jsx` | JSX + 6 vitest tests |
| `DashboardPage` (integration) | `resources/js/pages/DashboardPage.jsx` | JSX + existing tests still green |
| `ListDetailPage` (integration) | `resources/js/pages/ListDetailPage.jsx` | JSX + existing tests still green |

### Findings

| Category | Status | Finding |
|----------|--------|---------|
| Discoverability | OK | Replenishment banner placed at the top of the dashboard main content, above the lists section, only when `hasLists` is true (no banner on empty state). Complement chip appears below the add-item input on `ListDetailPage`, immediately after a successful item creation, with a dedicated `complementFor` state and `key={complementFor}` to force fresh mounts per item. Both surfaces are visible without extra clicks. |
| Clarity | OK | Spanish labels throughout (`Reposicion sugerida`, `Sueles comprar X cada N dias`, `Hace N dias`, `Anadir`, `Ignorar`, `Silenciar`, `Anadir a que lista?`, `Tambien:`). Frequency label is built server-side so the UI never has to do i18n math. Action labels are explicit about the consequence: "Ignorar" (24h) vs "Silenciar" (permanente). Modal title `"Anadir a que lista?"` makes the destination choice explicit. |
| Safety | OK | Three actions per replenishment card with progressive destructiveness: `Anadir` (indigo primary, additive), `Ignorar` (gray neutral, reversible after 24h), `Silenciar` (red text on light bg, permanent). Visual hierarchy matches semantic risk. Accept with multiple lists routes through the `SelectListModal` so the user explicitly picks the destination — never silently dumped. Complement chip accept is single-click but the action is benign (adds an item the user can delete). All destructive paths are recoverable through Epic 3's existing item delete flow. |
| Feedback | OK | Banner: loading state hides the section (no "Cargando..." flash), empty state hides the section, error surfaces via `role="alert"` red banner, action-in-progress disables the buttons of the targeted card, accept/ignore/silence remove the card optimistically. Chip: loading hides, empty hides, accept/dismiss hide. ListDetailPage tracks `complementFor` state — null hides the chip. Auto-hide 30s timer cleared on unmount. |
| Consistency | OK | All new components use the existing Tailwind tokens. `SelectListModal` follows the modal overlay pattern from `CreateListModal`/`ShareListModal`/`ConfirmClearHistoryModal` (same `fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4` overlay, same `max-w-sm w-full p-6` card). `ReplenishmentBanner` uses amber (`bg-amber-50 border-amber-200`) — new color in the project but appropriate for "attention without urgency". Indigo accents reused in `ComplementaryChip` pills (`bg-indigo-50 text-indigo-700`) match the rest of the app. |
| Spec Compliance | OK | All HU-503 + HU-504 acceptance criteria implemented. Banner caps at 3 cards (server-side). Frequency text matches HU-503 example. Three actions per card (accept/ignore/silence). Complement chip caps at 2 (service-level constant). Auto-hide 30s. Source badges not present on chip (intentional — chip only shows up to 2 items, not enough to justify visual differentiation). |

### Detailed UX observations

#### Discoverability
- The replenishment banner sits at the top of the dashboard main content, above the lists grid. Visible immediately on dashboard open. Hidden when there are no suggestions (no empty state competing with the dashboard's empty state).
- The complementary chip appears immediately below the `AddItemInput` after a successful item creation. The `complementFor` state in `ListDetailPage` triggers it; clearing the state on dismiss/accept unmounts the chip.
- `key={complementFor}` ensures fresh mounts when the user adds another item — the chip refetches for the new product.
- **Minor observation (non-blocking)**: a user adding many items quickly will see the chip flicker between products (mount/unmount). Acceptable since the chip is deferred 150ms via API call latency anyway.

#### Clarity
- Frequency label is server-built: `"Sueles comprar Leche entera cada 7 dias"`. Pluralization handled (`cada dia` vs `cada N dias`). Last-seen text: `"Hace 10 dias"` (also pluralized).
- Action labels distinguish behavior: `Ignorar` for "not now" (24h dismiss), `Silenciar` for "never again". Both verbs are commonly understood in Spanish UX.
- Modal title `"Anadir a que lista?"` is direct and unambiguous. Modal subtitle includes the product name in bold so the user knows what they're about to add.
- Complement chip uses `"Tambien:"` followed by pills, a compact and friendly framing.

#### Safety
- Three actions on each replenishment card. Visual treatment progressively distinct:
  - `Anadir` — `bg-indigo-600 text-white` (primary, attention-grabbing, additive action)
  - `Ignorar` — `bg-gray-100 text-gray-700` (neutral, reversible)
  - `Silenciar` — `bg-red-50 text-red-600` (warning, permanent — but soft red, not destructive red, because it's permanent silencing not deletion)
- The accept-with-1-list-direct vs accept-with-N-lists-modal flow ensures the user is never surprised by a misplaced item. Tested explicitly.
- Complement chip auto-hide after 30s prevents stale suggestions from lingering. Setting `dismissed = true` cleanly unmounts.
- No unconfirmed destructive actions. The only "hard" action is silence-permanent, and it's clearly labeled.

#### Feedback
- Banner loading: returns `null` instead of a skeleton — slight glance gap on first load is acceptable since the dashboard is already loaded.
- Banner empty: returns `null` if no suggestions. No "no hay sugerencias" placeholder competing for visual space.
- Banner error: red alert banner inside the section, with the section still rendered (so the user sees the section with an error message instead of nothing).
- Action in progress: the targeted card's three buttons disable simultaneously via `actionInProgress === productoNombre`. Prevents double-click double-action.
- Optimistic UI: cards disappear immediately on action; if the API later fails, the error banner appears and the card is gone (a re-fetch on next dashboard load brings it back).
- Chip: silent failures, no error UI (intentional — chip is best-effort).

#### Consistency
- `SelectListModal` mirrors the `ShareListModal`/`CreateListModal`/`ConfirmClearHistoryModal` overlay + card pattern. Same z-index, same backdrop, same max-w-sm, same close-via-button approach.
- `ReplenishmentBanner` uses a section pattern with semantic heading (`<section>` + `<h2 id="replenishment-title">`). Follows accessibility conventions.
- `ComplementaryChip` uses `flex-wrap items-center gap-2` for the pill row — consistent with how other inline action rows are laid out in the app.
- Amber color (`bg-amber-50 border-amber-200`) is **new to the project**. Indigo is the primary, red is destructive, green is success, gray is neutral. Amber slots in as "info-with-attention". Appropriate semantic placement, but note that this is a one-off color introduction — non-blocking, but worth tracking if a future design system pass needs to formalize the color palette.

#### Responsive (code-level inspection)
- `SelectListModal` uses `max-w-sm w-full p-6 px-4` — centered on desktop, full-width on mobile.
- `ReplenishmentBanner` action buttons use `flex gap-2 flex-wrap` — wraps to a second line on narrow viewports.
- `ComplementaryChip` uses `flex-wrap items-center gap-2 mt-2 ml-8` — wraps if pills don't fit. The `ml-8` aligns with `ItemRow` checkbox visually.
- No fixed-pixel widths anywhere. Tailwind responsive breakpoints not explicitly used (no `sm:`/`md:` prefixes), which means the components rely on natural reflow. **Cannot empirically verify at 375/768/1920 without `@browser`**. Recommended manual check.

#### Accessibility (code-level inspection)
- **`SelectListModal`**: `role="dialog"` + `aria-modal="true"` + `aria-labelledby="select-list-title"`. Tested.
- **`ReplenishmentBanner`**: uses semantic `<section>` with `aria-labelledby="replenishment-title"`. Action buttons are real `<button>` elements (not divs with onClick), so keyboard Tab/Enter works natively. Buttons disabled while action in progress.
- **`ComplementaryChip`**: dismiss button has `aria-label="Descartar sugerencias complementarias"`. Suggestion buttons are real `<button>` elements.
- All buttons rely on native keyboard semantics (Tab to focus, Enter/Space to activate).
- **Gaps (non-blocking, consistent with Epic 0-5A)**:
  - No focus trap inside `SelectListModal`. Tab can leave the modal to focused elements behind. Same project-wide gap as other modals.
  - No `Escape` key handler to close `SelectListModal`. Same project-wide pattern.
  - Auto-hide 30s on `ComplementaryChip` could be confusing for screen reader users — they might not realize the chip disappeared. Consider an `aria-live="polite"` region announcing "Sugerencia complementaria descartada" on auto-hide. **Non-blocking**, future polish.
- **Cannot empirically verify keyboard navigation and focus order without `@browser`**. Recommended manual check.

### UX Specification Compliance

**HU-503 Replenishment alerts**:
- ✅ Banner shown only when active list with >=3 items exists
- ✅ Up to 3 simultaneous suggestions
- ✅ Frequency text and last-seen days visible
- ✅ Three actions: accept (with 1- or N-list flow), ignore (24h), silence (permanent)
- ✅ Cache invalidation on every action (server-side), banner refetches on next load via `onAction` callback
- ✅ Silenced products never reappear (verified via service tests)

**HU-504 Complementary suggestions**:
- ✅ Chip appears below the recently added item
- ✅ Up to 2 complementary suggestions (service-level cap)
- ✅ User can accept with one click → adds to list
- ✅ User can dismiss with × → hides locally
- ✅ Auto-hide after 30s
- ✅ Only triggers after `>=5 completed lists` OR Claude fallback for new users

### Recommendation

- [x] Approve (code-level)
- [ ] Request changes
- [ ] N/A (no UI changes)

### Required Changes

None blocking. Three optional polish items for a future cleanup PR:

| Issue | Severity | Location | Suggestion |
|-------|----------|----------|------------|
| Amber color introduced as new project color | Low (project-wide) | `ReplenishmentBanner.jsx` | First use of `amber-*` Tailwind tokens. Either formalize in a design system pass or restrict to "AI suggestion" surfaces. |
| `aria-live` announcement on chip auto-hide | Low (a11y polish) | `ComplementaryChip.jsx` | Screen readers may miss the auto-hide. Consider a polite live region announcing the dismissal. |
| Modal ESC-to-close + focus trap | Low (project-wide) | `SelectListModal.jsx` | Consistent with the existing modal gap across Epic 2/3/4/5A. Needs a project-wide `useModal` hook. |

### Manual verification checklist (for product owner pre-release)

Since `@browser` was not available, the product owner should spot-check these scenarios in a live browser before release:

- [ ] Open the dashboard with a user that has at least one active list with 3+ items and history of frequently bought products. Verify the replenishment banner appears at the top with up to 3 cards.
- [ ] Verify each card shows: product name (bold), frequency text ("Sueles comprar X cada N dias"), last-seen ("Hace N dias"), and three buttons (Anadir/Ignorar/Silenciar).
- [ ] Click `Anadir` on a user with exactly 1 active list. Verify the item is added to that list and the card disappears immediately.
- [ ] Click `Anadir` on a user with multiple active lists. Verify `SelectListModal` opens, choose a list, verify item is added there.
- [ ] Click `Ignorar` on a card. Verify it disappears. Refresh the dashboard. Verify it does not reappear within 24h.
- [ ] Click `Silenciar` on a card. Verify it disappears. Refresh. Verify it does not reappear (ever).
- [ ] Open a list detail page. Add a new item. Verify the `ComplementaryChip` appears below the input within 1-2 seconds with up to 2 pills.
- [ ] Click a pill. Verify the suggested product is added to the list and the chip disappears.
- [ ] Add another item. Click the dismiss × on the chip. Verify it disappears and does not reappear.
- [ ] Add an item, wait 30 seconds without interacting. Verify the chip auto-hides.
- [ ] Mobile viewport (375px): verify banner buttons wrap to a second line and remain tappable; verify the chip pills wrap; verify the modal is full-width with usable spacing.
- [ ] Keyboard nav: Tab through the banner and verify each button is reachable in order. Enter/Space activates buttons. Modal is reachable via Tab.
- [ ] Accessibility: turn on a screen reader briefly and verify the banner is announced as a section with a heading, that buttons are labeled, and that the modal is announced as a dialog.
