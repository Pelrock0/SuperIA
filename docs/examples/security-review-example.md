# Security Review Example — Reference Output

This is a **reference example** of a completed Security Review report for the `security-reviewer` agent at step S5-SEC.

It is **not** tied to a real feature. Use it as a template to understand the expected level of detail, format, and decision-making.

The fictional feature reviewed below: **FEAT-AI-SUPPORT-CHAT** — an authenticated customer support chat that uses an LLM with RAG over per-tenant knowledge bases. Includes file uploads and webhook callbacks to a CRM.

---

## Security Review: FEAT-AI-SUPPORT-CHAT

### Summary
- **Status**: CHANGES REQUIRED
- **Reviewer**: claude-security-reviewer
- **Date**: 2026-04-11

### Automated Gates

| Gate | Command | Result |
|------|---------|--------|
| Deps audit | `composer audit` | PASS — 0 advisories |
| Deps audit (frontend) | `npm audit --omit=dev` | **FAIL** — 1 High (`axios@1.6.0` → CVE-2024-39338 SSRF) |
| Secret scan | `gitleaks detect --no-banner` | PASS |
| SAST | `semgrep --config auto app/ resources/js/` | 2 findings (Medium) |
| Lockfile present | `composer.lock`, `package-lock.json` | PASS |
| `.env` not tracked | `git ls-files \| grep -E '^\.env$'` | PASS |

> Gate failure on `axios` is itself blocking — must upgrade to ≥ 1.7.4 before re-review.

### OWASP Top 10 2021 Findings

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| A01 | Broken Access Control | **FAIL** | Tenant ID read from request body in `ChatController@send`, not from authenticated session — see Finding #1 |
| A02 | Cryptographic Failures | PASS | Passwords via `Hash::make` (bcrypt cost 12). TLS enforced by load balancer. HSTS header present. |
| A03 | Injection | PASS | All Eloquent queries parameterized. Blade autoescaping not bypassed. |
| A04 | Insecure Design | PASS WITH NOTES | Threat model in `03-technical-design.md` § 4. Missing: rate-limit category for chat endpoint not specified — see Finding #4 |
| A05 | Security Misconfiguration | PASS | `APP_DEBUG=false` in `.env.production`. Security headers via `secure-headers` middleware. CSP without `unsafe-inline`. |
| A06 | Vulnerable Components | **FAIL** | `axios@1.6.0` — see Automated Gates |
| A07 | Auth Failures | PASS | Sanctum bearer tokens. Login rate-limited via `throttle:5,1`. Session rotated on login. |
| A08 | Integrity Failures | **FAIL** | CRM webhook callback handler does not verify HMAC signature — see Finding #2 |
| A09 | Logging & Monitoring | PASS WITH NOTES | Auth events logged. Missing: log entry for tenant context switch — see Finding #5 (Low) |
| A10 | SSRF | **FAIL** | RAG ingestion fetches user-provided URLs via raw `Http::get($url)` with no allowlist — see Finding #3 |

### OWASP LLM Top 10 v2 (2025)

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| LLM01 | Prompt Injection | PASS WITH NOTES | User messages wrapped in `<user_message>` delimiters. Indirect injection risk from RAG chunks acknowledged but no output filtering — see Finding #6 (Medium) |
| LLM02 | Sensitive Info Disclosure | PASS | System prompt contains no secrets. Anthropic enterprise terms reviewed (zero retention). |
| LLM03 | Supply Chain | PASS | Model ID pinned to `claude-sonnet-4-6`. No third-party MCP tools connected. |
| LLM04 | Data & Model Poisoning | PASS | RAG ingestion restricted to authenticated tenant admins. Per-tenant index isolation. |
| LLM05 | Improper Output Handling | PASS | LLM output rendered through DOMPurify. No `eval`, no SQL generation, no shell. |
| LLM06 | Excessive Agency | PASS | Read-only tools only (search KB, fetch ticket). No destructive actions exposed. |
| LLM07 | System Prompt Leakage | PASS | Assumed leakable. No credentials inside. Authorization enforced server-side. |
| LLM08 | Vector & Embedding Weaknesses | **FAIL** | Pinecone query filters by `tenant_id` only at insert time, not at query time — see Finding #7 |
| LLM09 | Misinformation | PASS WITH NOTES | Responses cite KB sources. Disclaimer present. No legal/medical context. |
| LLM10 | Unbounded Consumption | **FAIL** | No per-user token budget. Streaming has no timeout. Cost alerts not wired — see Finding #8 |

### Cross-Cutting

- **Idempotency**: PASS — CRM webhook handler dedupes on `event_id` via unique constraint on `crm_events.event_id`. Once Finding #2 is fixed, idempotency remains intact.
- **Rate Limiting**: PARTIAL — login/signup rate-limited. Chat endpoint and RAG ingestion **not** rate-limited. See Findings #4 and #8.
- **Transactions**: PASS — `DB::transaction()` wraps message persist + token usage debit.

### Required Changes

| # | Severity | OWASP | File:Line | Issue | Required Fix |
|---|----------|-------|-----------|-------|--------------|
| 1 | **High** | A01 | `app/Http/Controllers/ChatController.php:58` | `tenant_id` taken from `$request->input('tenant_id')` enables horizontal escalation | Read from `auth()->user()->tenant_id`. Add policy `ChatPolicy@send`. Add test: user from tenant A cannot post to tenant B. |
| 2 | **High** | A08 | `app/Http/Controllers/Webhooks/CrmController.php:23` | Webhook accepts payload without HMAC verification — anyone can forge CRM events | Verify `X-CRM-Signature` header against shared secret using `hash_equals`. Reject on mismatch. Add integration test for invalid signature. |
| 3 | **High** | A10 | `app/Services/RagIngestionService.php:41` | `Http::get($userUrl)` allows SSRF to metadata endpoint and internal services | Use a wrapper that resolves DNS, blocks RFC1918 + `169.254.0.0/16` + loopback, then connects by IP. Add allowlist of permitted domains where possible. |
| 4 | Medium | A04 | `routes/api.php:34` | Chat endpoint has no rate limit — abuse and cost risk | Apply `throttle:30,1` per user and `throttle:60,1` per IP. Document chosen limits in technical design. |
| 5 | Low | A09 | `app/Services/TenantContextService.php:18` | Tenant context switch not logged | Add `Log::info('tenant_switch', [...])` with actor ID, source/target tenant, request ID. |
| 6 | Medium | LLM01 | `app/Services/ChatLlmService.php:72` | RAG chunks injected into prompt without output guardrails — indirect prompt injection from poisoned KB content | Add output filter that detects role-switching tokens in model response. Constrain tools to read-only (already done). Document residual risk. |
| 7 | **High** | LLM08 | `app/Services/VectorSearchService.php:29` | Pinecone query lacks `filter: { tenant_id }` — cross-tenant retrieval possible | Add metadata filter on every query. Add test: tenant A query never returns tenant B vectors. |
| 8 | Medium | LLM10 | `app/Http/Controllers/ChatController.php:91` | No token budget per user; no streaming timeout; no cost alerts | Implement `users.monthly_token_quota`. Enforce on each request. Set 60s streaming timeout. Wire CloudWatch alarm on Anthropic spend > $X/day. |

### Recommendation

- [ ] Approve
- [ ] Approve with notes (Low only)
- [x] **Request changes (blocking)**

**Blocking findings**: #1, #2, #3, #6, #7, #8 (and the `axios` CVE from automated gates).

### Notes / Tech Debt

- Finding #5 (Low) deferred only if Findings #1–#3 are fixed in this iteration. Otherwise, fix together.
- Consider rotating Pinecone API key after Finding #7 fix — assume cross-tenant data may have been retrieved during testing.
- Future hardening: add CSP `report-uri`, enable Sanctum token expiry, add `axios` to Dependabot allow-list.

---

## How to use this example

When the `security-reviewer` agent runs at S5-SEC, its output should match the structure above:

1. **Summary block** with status, reviewer, date
2. **Automated Gates table** — every gate, command run, result
3. **OWASP Top 10 2021 table** — all 10 items, no skips
4. **OWASP LLM Top 10 v2 table** — only if AI surface present
5. **Cross-cutting block** — idempotency, rate limiting, transactions
6. **Required Changes table** — one row per finding, OWASP-mapped, with file:line and required fix
7. **Recommendation** — single check
8. **Notes / Tech Debt** — deferred Low items with rationale

Severity drives the recommendation:

- Any **Critical** or **High** → `CHANGES REQUIRED`
- Any **Medium** → `CHANGES REQUIRED`
- Only **Low** → `PASS WITH NOTES`
- Nothing → `PASS`

The full checklist and severity definitions live in `.cursor/skills/security-review.md`.
