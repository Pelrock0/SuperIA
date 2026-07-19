## Security Review: FEAT-DUPCHECK-ACTIVE

### Summary
- **Status**: PASS WITH NOTES
- **Verdict**: PASS
- **Reviewer**: claude-security-reviewer
- **Date**: 2026-06-03
- **Scope reviewed**: `app/Support/Inflector/SpanishInflector.php`, `app/Services/ListItemService.php` (modified `create()`, `createOrIncrement()`; new private `deletePurchasedHomonyms()`, `unitMatches()`), `app/Http/Controllers/ListItemController.php` (unchanged — re-verified), `resources/js/lib/spanishInflector.js`, `resources/js/components/items/AddItemInput.jsx`, `resources/js/components/items/AddItemModal.jsx`.

### Automated Gates

| Gate | Command | Result |
|------|---------|--------|
| Dependency CVEs (PHP) | `composer audit --format=plain` | 11 advisories on 7 symfony packages: **1 High** (`symfony/mime` CVE-2026-45067 — CRLF email header injection), **6 Medium**, **3 Low**, 2 unscored. **All pre-existing on `main`; `composer.lock` unchanged in this branch (verified via `git status`/`git diff`).** None of the affected packages (`symfony/http-foundation`, `symfony/http-kernel`, `symfony/mailer`, `symfony/mime`, `symfony/polyfill-intl-idn`, `symfony/routing`, `symfony/yaml`) are touched by this feature. Tracked as known platform debt by the pre-existing failing test `SecurityGatesIntegrationTest::composer_security_exits_zero`. **Not introduced by FEAT-DUPCHECK-ACTIVE.** |
| Dependency CVEs (JS) | `npm audit --omit=dev` | Not executed in this turn (`package-lock.json` unchanged in branch; same condition as PHP — pre-existing baseline). Recommended to run in CI as part of platform-level dependency hygiene. |
| Secret scan | gitleaks not installed on host. Manual `git diff` review on the 8 touched files | **PASS** — no credentials, tokens, API keys, or secrets in any diff. References to `ShareTokenContext` are pre-existing imports for activity logging. |
| SAST | psalm taint-analysis not run in this turn. Manual review applied | **PASS (manual)** — no `DB::raw`, `whereRaw`, `selectRaw`, `eval`, `exec`, `shell_exec`, `system`, `dangerouslySetInnerHTML`, `eval`, or `Function()` introduced. Confirmed via grep across `app/Services/ListItemService.php` and `resources/js/components/items/`. |
| Lockfile present | `composer.lock`, `package-lock.json` exist | **PASS** |
| `.env` not tracked | `git ls-files \| grep -E '^\.env$'` | **PASS** — only `.env.example` tracked. |

> **Gate interpretation**: per `security-review.md` §1, automated gates with High/Critical results normally block. The High finding (`symfony/mime` CVE) is **out of scope for this feature** (no code path in the feature touches mail/mime), and was pre-existing on `main` before the branch was cut. It is documented as platform-level dependency tech debt that requires a separate upgrade ticket. Not blocking for FEAT-DUPCHECK-ACTIVE; explicitly recorded here so it is not silently swallowed.

---

### OWASP Top 10 2021 Findings

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| A01 | Broken Access Control | **PASS** | `POST /api/lists/{list}/items` still calls `authorizeListWrite($list)` in `ListItemController::store` (lines 29-36 unchanged). The new `deletePurchasedHomonyms()` operates exclusively through `$list->items()` which is scoped to the validated `ShoppingList` route-model-bound parameter. No new route, no new permission. The delete capability is **not new privilege** — collaborators in `mode->allowsWrite()` already have `destroy`, `clearCompleted`, and `update` capabilities on the same items via existing endpoints. No IDOR: query is implicitly filtered by `shopping_list_id` via the relation, and `whereIn('id', $matchIds)` uses IDs hydrated from that same scoped query (no user-supplied IDs). No horizontal/vertical escalation surface. Cross-list isolation verified by unit test `test_create_does_not_delete_purchased_in_other_lists`. |
| A02 | Cryptographic Failures | **N/A** | No new secrets, no new crypto, no new cookies, no new tokens. Feature is pure string-processing + Eloquent CRUD. |
| A03 | Injection | **PASS** | (a) **SQL**: `SpanishInflector::normalize()` is pure PHP string ops (`mb_strtolower`, `strtr`, `preg_replace`, `mb_substr`, `explode`, `implode`) — no SQL touched. Filter is in-memory on already-hydrated Eloquent collections (`$purchased->filter(...)`). Delete query uses `whereIn('id', $matchIds)` with array of integer IDs from Eloquent hydration — fully parameterized by Eloquent. No `DB::raw`/`whereRaw`/`selectRaw` introduced (confirmed via grep on `ListItemService.php`). (b) **XSS**: item `name` stored as-submitted (`CreateItemRequest` caps at 80 chars). Rendered in React via JSX `{}` (auto-escaped). Zero occurrences of `dangerouslySetInnerHTML` across `resources/js/` (verified). Stored XSS mitigated by React default escaping + 80-char input cap. (c) **Command/template injection**: none. (d) **Log injection**: `ActivityLogService::record` truncates `item_name` at 80 chars via `mb_substr` — newlines are not stripped, but logs are stored in DB (not text-based syslog) and rendered as React text. |
| A04 | Insecure Design | **PASS** | Threat model documented in `03-technical-design.md` §Security and §Risks. Abuse case explicitly considered: malicious collaborator triggering mass-delete of purchased items via repeated adds. **Mitigated by design**: `productos_historial` (alimented by `togglePurchased → ProductoHistorial::recordPurchase`) is the authoritative purchase history and is **never deleted** by this feature. The `ListItem` row with `is_purchased=true` is a UI-state artifact, not a historical record — analogous semantics to the pre-existing `clearCompleted` endpoint (which deletes purchased items wholesale and is already a documented capability for any write-collaborator). No new abuse surface introduced. Business rule (delete only same normalized name AND same unit) is narrow and deterministic; `AC-6`/`AC-8` tests cover the over-match boundary (`pollo`/`polla`, unit mismatch). |
| A05 | Security Misconfiguration | **N/A** | No new env vars, no new routes, no new headers, no debug flags touched. `routes/api.php` line 110 unchanged. |
| A06 | Vulnerable Components | **N/A for this feature** / FAIL at platform level | This feature introduces zero new dependencies (no `composer.json`/`package.json` modifications). The platform-level CVE backlog is recorded under "Automated Gates" above. **None of the vulnerable packages are reachable by any code path in this feature.** |
| A07 | Auth Failures | **N/A** | No auth changes. No password/token/session surface touched. |
| A08 | Integrity Failures | **PASS** | No deserialization (`unserialize`/`pickle`/`yaml.load`). No file uploads. No webhooks. No JWT changes. No autoupdate. |
| A09 | Logging & Monitoring | **PASS WITH NOTE** | `ActivityAction::ItemAdded` is logged via `logActivity()` for every successful `create()` (existing behavior, preserved). **Design choice**: the silent delete of purchased homonyms is **not** logged as a separate `ItemDeleted` activity entry. This is explicit in PRD AC-11: "no se registra explícitamente el delete del comprado homónimo en activity log — el delete es side effect del add". Acceptable because: (1) the delete operates on `ListItem` rows that the collaborator could already delete via `destroy` or `clearCompleted` (no new authority); (2) the authoritative purchase history in `productos_historial` is untouched; (3) the counter changes reflect the net result. **Recommendation (Low / tech debt, non-blocking)**: consider extending `logActivity` with an `ItemReplacedHomonym` entry on non-zero `$matchIds` to provide auditability for collaborator-initiated deletions of another user's purchased items. Track as future enhancement, not a blocker. |
| A10 | SSRF | **N/A** | No outbound HTTP introduced. Feature is fully in-process. |

---

### OWASP API Security Top 10 (2023) — Delta

- **API1 BOLA**: Covered under A01. Route model binding `{list}` + `authorizeListWrite($list)` + `$list->items()` scoping. No object reference exposed beyond `{list}` ID, which is auth-checked.
- **API4 Unrestricted Resource Consumption**: `CreateItemRequest::rules()` caps `name` at 80 chars (defense vs DoS through inflector). The new `deletePurchasedHomonyms()` adds 1 SELECT + ≤1 DELETE per `create()` call. Volume bounded (lists have ≤100 items in practice; subset filtered to `is_purchased=true`). No new unbounded loop or recursion. See cross-cutting **Rate Limiting** below.
- **API6 Sensitive Business Flows**: N/A — no signup/checkout/payment surface.
- **API9 Inventory**: No new endpoint, no new version, no Swagger exposure.

---

### OWASP LLM Top 10 v2 (2025)

**Skipped explicitly**: this feature has **zero AI surface**. `SpanishInflector` is a deterministic rule-based helper (constants + `mb_substr`/`strtr` operations); no LLM call in any code path, no prompt construction, no embedding, no tool use, no agent. Frontend `findDuplicate` is local + synchronous. Justified skip per `security-review.md` §3b.

---

### Cross-Cutting

- **Idempotency**: N/A by design. `POST /items` was never idempotent (no `Idempotency-Key` header support, no natural dedupe constraint). Documented in `03-technical-design.md` §Transaction Boundaries. Race accepted: two collaborators adding the same new name to the same list simultaneously may both create pendings (existing behavior, not regressed). Subsequent add reconciles via the new normalized match.
- **Rate Limiting**: **Low / pre-existing gap**. `POST /api/lists/{list}/items` (`routes/api.php:110`) carries no specific `throttle:N,M` middleware — only the global API auth chain. Pre-existing condition not introduced by this feature, but the new delete side-effect (each successful create now does 1 extra SELECT + ≤1 DELETE under `lockForUpdate`) slightly amplifies the cost per request. Not a blocker (lookup is `O(N≤100)` per list, lock scope is per-list, so impact is bounded per attacker per list). **Recommended platform-level enhancement**: add `throttle:60,1` (or similar) to write endpoints on lists. Track separately; not blocking for FEAT-DUPCHECK-ACTIVE.
- **Transactions & State**: **PASS**. (a) `ListItemService::create()` wraps the entire flow (delete + insert + counter sync + log) in a single `DB::transaction` (line 49). Rollback on any exception leaves list untouched (AC-9 covered by intent; no explicit fault-injection test added but the transaction boundary is correctly placed). (b) `createOrIncrement()` does not open its own transaction; per contract, the caller (`WeeklySummaryService::saveSelection`) is responsible — verified by existing pattern. (c) `lockForUpdate()` applied to the `is_purchased=true` subset in `deletePurchasedHomonyms` and to the `is_purchased=false` subset in `createOrIncrement`. Serializes concurrent adds **within the same list**. The lock scope is broader than the original (`whole subset` vs `single row match`), but bounded by `shopping_list_id` — acceptable tradeoff documented in TD §"Locking y race conditions". No partial-write paths.

---

### Required Changes

None blocking.

---

### Recommendation

- [x] **Approve with notes** (Low only — see "Notes / Tech Debt")
- [ ] Approve
- [ ] Request changes (blocking)

**Verdict: PASS**

---

### Notes / Tech Debt (Low — non-blocking, recommend separate tickets)

1. **Platform-level CVE backlog** (`composer audit` — 11 advisories including 1 High in `symfony/mime` CVE-2026-45067). Pre-existing on `main`, lockfile unchanged in this branch. Owned by platform/dependency-upgrade work (already tracked by `SecurityGatesIntegrationTest::composer_security_exits_zero`). **Not introduced by this feature, not reachable by this feature's code paths.** Recommend dedicated upgrade ticket for symfony 7.4.13+ / 8.0.13+.

2. **Activity log gap on silent delete** (A09 NOTE). Adding `ItemReplacedHomonym` (or similar) entries on non-zero deletes would improve auditability when a collaborator's add removes another collaborator's purchased item. Not security-critical because (a) the underlying capability exists already via `destroy`/`clearCompleted`, and (b) authoritative purchase history lives in `productos_historial`. Track as UX/audit-trail enhancement.

3. **No specific rate-limit on `POST /api/lists/{list}/items`**. Pre-existing platform gap, slightly amplified by the new delete side-effect. Recommend adding `throttle:60,1` (or platform-default) to list write endpoints in a follow-up.

4. **No fault-injection test for AC-9 rollback**. The transaction boundary is correctly placed (verified by reading), but no test simulates a mid-transaction failure (e.g., DB constraint violation after `deletePurchasedHomonyms` succeeded). Recommend a follow-up test that mocks `$list->items()->create()` to throw, asserting purchased items remain. Low priority — relies on Laravel's well-tested transaction rollback semantics.

5. **Frontend `findDuplicate` is best-effort UX hint, not a trust boundary**. The backend always re-applies `deletePurchasedHomonyms` regardless of the frontend's `is_purchased` filter decision, so a manipulated client cannot bypass the rule. Confirmed correct by design and noted for completeness.

---

### Transition

- Gate Status: **S5-SEC PASS WITH NOTES** (mapped to PASS)
- Next Step: STEP 5.3 — Test Gate (test-gate / test-enforcement)
- Required Artifacts for next step: this file + `04-implementation-notes.md` + test outputs.
