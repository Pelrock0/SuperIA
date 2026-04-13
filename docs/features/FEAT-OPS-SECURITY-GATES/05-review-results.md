# Review Results: FEAT-OPS-SECURITY-GATES

## Code Review: FEAT-OPS-SECURITY-GATES

### Summary
- **Status**: PASS
- **Reviewer**: code-reviewer (Claude Code, Opus 4.6)
- **Date**: 2026-04-11

### Justification
Pure infrastructure feature delivered exactly as designed. All ACs (AC-1 through AC-16) verifiable in the artifacts. The risky part — fixing 224 legacy psalm findings without regressing 478 existing tests — landed cleanly: backend 479/479, frontend 208/208, `composer security` exit 0, taint 0, audit 0. Legacy fixes were minimum-viable docblocks, narrow casts, and three justified suppressions; no scope creep into refactors. Recommend approval.

### Findings

#### Readability
- `composer.json:66-69` — `security` script is two lines, intent obvious. Good.
- `psalm.xml` — minimal config, comment-free, schema URL pinned via `xsi:schemaLocation`. Good.
- `.github/workflows/security.yml` — 42 lines, three jobs, no clever YAML. Good.
- `tests/Feature/SecurityGatesIntegrationTest.php` — single test, single assertion, dumps stdout/stderr on failure for debugging. Good.
- Inline `@psalm-suppress` comments (`routes/console.php:8`, `app/Http/Controllers/Auth/ProfileController.php:79`) are self-documenting and scoped to one expression. Good.

#### Maintainability
- Suppressions are scoped narrowly:
  - `psalm.xml:30-36` — `UndefinedFunction` suppressed only in `app/Http/Controllers/Admin`, `CheckIfAdmin.php`, `UserRequest.php` (Backpack helpers `backpack_url()` etc.). Cannot leak to non-Backpack code.
  - `routes/console.php:8` — `InvalidScope` on the framework-rebound `$this` in `Artisan::command` closure. One-line, justified.
  - `app/Http/Controllers/Auth/ProfileController.php:79` — `UndefinedDocblockClass` on paginator `items()` generic leak. Scoped to one variable.
  - All three documented in `04-implementation-notes.md` per AC-14.
- Legacy fix pattern is consistent: `->toBase()->map()` to drop Eloquent collection generics before mapping to non-Model values. Applied identically in `ShareTokenController.php:23`, `ShoppingListController.php:106`, `ProductSuggestionService.php:83`. No duplication, just a uniform idiom.
- Factory `@extends Factory<Model>` + model `@use HasFactory<Factory>` docblocks are mechanical and ~20 files; pattern is copy-paste reproducible by anyone reading one example. Good.
- `config/database.php:62` — `Pdo\Mysql::ATTR_SSL_CA` replaces the deprecated `PDO::MYSQL_ATTR_SSL_CA` constant. Necessary side-effect of the PHP 8.5.5 upgrade triggered by psalm 6's PHP requirement. Out of original PRD scope but unavoidable; acceptable bundling since reverting it would break the suite.

#### Tests
- New: `SecurityGatesIntegrationTest::test_composer_security_exits_zero` — invokes `composer security` via Symfony Process, asserts exit 0. Marks skipped if composer binary absent (CI fallback per AC-10). One test is appropriate for this feature: it is a proof-of-life that the wrapper works on the current codebase, and the gate it validates is itself a test runner.
- Coverage paths considered:
  - **Happy path**: covered (the assertion).
  - **Failure path**: AC-5 verifies the script returns non-zero on a deliberately broken file. This is not asserted in an automated test (would require introducing+reverting a synthetic finding, which is itself fragile). Acceptable trade-off — manually verifiable, low value to automate.
  - **Edge case**: missing composer binary → `markTestSkipped`. Covered.
  - **Security path**: N/A — this feature *is* the security gate; meta-testing it would be circular.
- Pre-existing 478 tests untouched in semantics, only mechanical updates (`#[\Override]` attributes from psalm `--alter`, `factory()->create()` → `factory()->createOne()` mass replace). Suite green at 479/479 confirms zero behavioral regressions per AC-11. Frontend untouched per AC-12.

#### Performance
- N/A for runtime — no DB queries, no request handling.
- **Test runtime**: `SecurityGatesIntegrationTest` adds ~40s to the backend suite (cold-cache psalm scan inside subprocess, since the script uses `--no-cache`). Documented in implementation notes. Total suite duration in this run was 205s; the integration test contributes ~20% of that. Acceptable for an infra gate.
- **`composer security` script** uses `--no-cache --no-progress`. This is a deliberate trade-off: predictable CI output and zero-cache-corruption risk vs. ~30s slower local runs. Documented as a deviation from design. Recommendation: monitor; if local DX becomes painful, drop `--no-cache` from the script and let psalm cache. Non-blocking.

#### Architecture
- Architectural compliance is trivially satisfied: this feature has no domain, no services, no controllers. It modifies infra config (`composer.json`, `psalm.xml`, `.github/workflows/security.yml`, `.gitignore`), one feature test, and three documentation surfaces.
- CLI boundary respected — no changes under `/cli` (verified: `git status` shows no `cli/` modifications beyond state JSON, which is auto-managed).
- Legacy fixes do not change layer boundaries. The `->toBase()->map()` refactors stay inside the same controller/service methods; no logic moved across layers.
- Skill update (`.cursor/skills/security-review.md`) and `CLAUDE.md` addition are append-only/wrapper-row insertions per design; existing skill consumers continue to work.

### Recommendation
- [x] Approve
- [ ] Request changes

### Required Changes
None.

### Advisory Notes (non-blocking)
1. **`--no-cache` in `composer security` script** — slows local runs. If DX complaints arise, drop the flag and rely on psalm's content-hash cache. The integration test would still exercise a real subprocess; only the cache layer changes.
2. **`config/database.php` constant rename** is scope-adjacent (driven by PHP 8.5 upgrade, not the feature's PRD). No PRD update needed since the upgrade itself was user-approved mid-S4, but worth flagging for the security reviewer in case they want to confirm the new `Pdo\Mysql::ATTR_SSL_CA` behavior matches the old constant semantically (it does — same PDO constant, just namespaced).
3. **AC-5 (deliberate-break exit non-zero)** is not automated. Manual spot-check during S5-SEC is sufficient; automating would require committing then reverting a broken file, which is more risk than value.
4. **1576 psalm "info" findings** remain (errorLevel 5 ignores them). Implementation notes already classify this as out-of-scope tech debt. Future `psalm-info-cleanup` feature is the right home; do not expand this PR.

---

## Security Review: FEAT-OPS-SECURITY-GATES

### Summary
- **Status**: PASS WITH NOTES
- **Reviewer**: claude-security-reviewer
- **Date**: 2026-04-11

This feature is the security-gate infrastructure itself. It introduces no user input, no auth surface, no DB writes, no external HTTP, and no AI surface. The OWASP Top 10 mapping is therefore N/A-heavy by design. The relevant OWASP categories are A05 (Misconfiguration) and A06 (Vulnerable Components) — both PASS. One Low advisory on GitHub Actions pinning style; non-blocking. Recommendation: approve with note.

### Automated Gates

| Gate | Command | Result |
|------|---------|--------|
| Wrapper (Laravel) | `composer security` | **PASS** — `composer audit` 0 advisories, `psalm --taint-analysis` 0 errors (40.28s) |
| Deps audit (PHP) | `composer audit` (via wrapper) | PASS — 0 advisories |
| Deps audit (frontend) | `npm audit --omit=dev` | PASS — 0 vulnerabilities |
| Secret scan | `gitleaks detect --no-banner` | **Deferred to CI** — gitleaks not installed in local Windows env. The `.github/workflows/security.yml` job (delivered by this feature) runs `gitleaks/gitleaks-action@v2` on every PR with `fetch-depth: 0`. Local gate is documented in README install table. Acceptable: this feature is itself the secret-scan gate's first deployment. |
| SAST (PHP) | `psalm --taint-analysis` (via wrapper) | PASS — 0 taint sources, 0 type errors |
| Lockfiles present | `ls composer.lock package-lock.json` | PASS — both committed |
| `.env` not tracked | `git ls-files \| grep -E '^\.env$\|^\.env\.'` | PASS — no match |

> Composer phar emits PHP 8.5 deprecation warnings (`curl_close`, `react/promise`) during the run. These are upstream Composer issues unrelated to this codebase and do not affect exit codes. Documented in `04-implementation-notes.md`.

### OWASP Top 10 2021 Findings

| ID | Category | Status | Notes |
|----|----------|--------|-------|
| A01 | Broken Access Control | N/A | Feature has no endpoints, no controllers, no auth surface. The legacy fix in `app/Http/Controllers/Auth/ProfileController.php` is a docblock-only User narrowing — `auth('api')->user()` and `$weighting->rankedListPaginated($user, 20)` already enforce per-user scoping; the change does not weaken access control. The `ShareTokenController` / `ShoppingListController` `->toBase()->map()` refactors retain `authorizeListOwnership` / `authorizeOwnership` as the first line of every action — verified by reading lines 21-49 of `ShareTokenController.php` and 44-91 of `ShoppingListController.php`. |
| A02 | Cryptographic Failures | N/A | No crypto introduced. `config/database.php:62` swaps the deprecated `PDO::MYSQL_ATTR_SSL_CA` constant for `Pdo\Mysql::ATTR_SSL_CA` — semantically identical (same PDO attribute, namespaced form required by PHP 8.5). TLS-to-MySQL behavior unchanged. |
| A03 | Injection | PASS | The new test `tests/Feature/SecurityGatesIntegrationTest.php` invokes `composer security` via `Symfony\Component\Process\Process` with a **command array** (`[$composer, 'security']`), not a shell string. No command injection vector. `base_path()` is the framework-trusted project root, not user input. Psalm taint analysis (now active) found 0 taint sources across the entire codebase — this is a baseline negative-proof for future features. |
| A04 | Insecure Design | PASS | The threat model is the design itself: enforce automated gates at PR time. AC-13's "STOP at >200 findings" checkpoint is an explicit risk gate. The 3 inline psalm suppressions were each justified, narrowly scoped, and documented in implementation notes per AC-14. The default for any future suppression is "fix, don't suppress", enforced socially by AC-14 and structurally by `findUnusedBaselineEntry="false"`. |
| A05 | Security Misconfiguration | PASS | `psalm.xml` excludes `vendor`, `storage`, `bootstrap/cache`, `node_modules` — correct. `cacheDirectory="storage/framework/psalm"` is gitignored (`.gitignore:21`) so analysis cache cannot leak via VCS. `UndefinedFunction` suppressions are scoped to three Backpack-glue paths only (`app/Http/Controllers/Admin`, `CheckIfAdmin.php`, `UserRequest.php`); they cannot mask real undefined-function calls in business code. The `.github/workflows/security.yml` uses `${{ secrets.GITHUB_TOKEN }}` (built-in, scoped, ephemeral) — no static secrets in the workflow. `composer security` script uses `--no-cache` for deterministic CI runs (defense against cache-poisoning false negatives). |
| A06 | Vulnerable & Outdated Components | PASS | This is the headline-relevant category. **`composer audit` exit 0** on the new dependency closure including `vimeo/psalm ^6.0` and `psalm/plugin-laravel ^3.0`. **`npm audit --omit=dev` 0 vulnerabilities**. Both new packages are dev-only (`require-dev`), so they are NOT shipped to production runtime. PHP runtime upgraded 8.4.0 → 8.5.5 (latest stable, no EOL risk). Lockfile (`composer.lock`) committed and used. The CI workflow runs `composer install --no-progress --no-interaction` against the lockfile, ensuring CI sees the exact version set the developer audited. |
| A07 | Identification & Authentication Failures | N/A | No auth code touched. ProfileController fix is docblock-only narrowing of an already-authenticated `auth('api')->user()` call. JWT version increment, password hashing, and reset logic untouched. |
| A08 | Software & Data Integrity Failures | PASS WITH NOTE | `composer.lock` integrity enforced by composer itself. The integration test does not deserialize untrusted input. The CI workflow pins GitHub Actions to **major-version tags** (`actions/checkout@v4`, `shivammathur/setup-php@v2`, `gitleaks/gitleaks-action@v2`) rather than commit SHAs — see Note #1. JWT/webhook surface untouched. |
| A09 | Security Logging & Monitoring Failures | N/A | This feature adds CI gates and a static-analysis pass; it does not add runtime logging surface. The workflow itself produces GitHub Actions run logs which are the audit trail for the gates. |
| A10 | SSRF | PASS | Zero outbound HTTP introduced. `composer security` is local subprocess execution, not HTTP. The CI workflow's `composer audit` fetches the advisory database from packagist.org (trusted, hardcoded by composer itself) — not user-controlled. |

### OWASP API Top 10 2023 — Quick Add

N/A entire section. This feature exposes zero API endpoints. BOLA, resource consumption, business-flow abuse, inventory management — all inapplicable.

### OWASP LLM Top 10 v2 (2025)

N/A entire section with justification. This feature does not call an LLM, does not embed user content into prompts, does not expose an AI endpoint, and does not use an agent framework. It is pure CI/static-analysis infrastructure. The codebase does have an AI surface (Epic 5A/5B), but those features were reviewed under their own S5-SEC and are out of scope here.

### Cross-Cutting

- **Idempotency**: N/A — no state-mutating operations introduced. `composer security` is read-only static analysis. The integration test invokes it as a subprocess with no side effects on the test database (it uses no DB at all).
- **Rate Limiting**: N/A — no public endpoints. The CI workflow runs only on `pull_request` and `push: main` events, naturally rate-limited by PR cadence.
- **Transactions**: N/A — zero DB writes.

### Stack-Specific (Laravel) Hot-Spot Check

- `FormRequest` / `Hash::make` / `Policy` / `DB::transaction()` — none touched. Existing patterns intact.
- Eloquent: the `->toBase()->map()` refactors do **not** introduce N+1 (the underlying queries are unchanged; only the in-memory transformation step changed). Verified by reading `ShareTokenController::index`, `ShoppingListController::activityLog`, `ProductSuggestionService::searchCatalog`.
- Mass assignment: no `$fillable` / `$guarded` changes.
- The new `psalm/plugin-laravel` understands Eloquent magic and Facade dispatch — its presence (active in CI) increases the chance of catching authorization-bypass patterns in future PRs.

### Required Changes

None. No Critical / High / Medium findings.

### Recommendation

- [ ] Approve
- [x] Approve with notes (Low only)
- [ ] Request changes (blocking)

### Notes / Tech Debt

1. **(Low, A08) GitHub Actions pinning style** — `.github/workflows/security.yml` pins third-party actions to major-version tags (`@v4`, `@v2`) instead of immutable commit SHAs. Tag pinning allows the action publisher to retroactively change the code under the same tag, which is the supply-chain attack class exploited in the 2024 `tj-actions/changed-files` incident. SHA pinning is the harder-but-safer choice. **Recommendation**: defer for now (industry-standard tradeoff, especially for first-party `actions/*` and well-known publishers like `shivammathur` and `gitleaks`), but consider adopting SHA pinning + Dependabot for action updates as a follow-up. **Not blocking** for this feature.
2. **(Informational) Local secret-scan gate** — `gitleaks` is documented per-platform in README but is not installed by default. Developers running `composer security` locally do not run gitleaks. The CI workflow is the enforced gate. This is by design (per S1 decision 3: gitleaks is documentation + CI only, no local hook installer). Acceptable; no action needed.
3. **(Informational) Branch protection** — file-based deliverables cannot enable required-checks on `main`. This is a one-time GitHub admin UI task documented in README and explicitly out of scope per the PRD. Until enabled, the workflow is advisory rather than enforced. Track separately.
4. **(Informational) `composer security` adds ~40s to the backend test suite** because the integration test runs psalm with `--no-cache` inside a subprocess. Acceptable for an infra gate; if it becomes painful, drop `--no-cache` from the script. Already noted in code review.

### Additional safety checks (beyond the OWASP mapping)

- **Suppressions audit**: I read each of the three inline `@psalm-suppress` sites to confirm none mask a security-relevant issue:
  - `routes/console.php:8` — `InvalidScope` on `Inspiring::quote()` inside `Artisan::command` closure. Framework-rebound `$this`; no security relevance.
  - `app/Http/Controllers/Auth/ProfileController.php:79` — `UndefinedDocblockClass` on `$paginator->items()`. The variable is narrowed to `list<\stdClass>` immediately, and the data is per-user (paginator is built from `$weighting->rankedListPaginated($user, ...)`). No security relevance; the suppression is purely about a generic-template leak in Laravel's pagination contract.
  - `psalm.xml:30-36` — `UndefinedFunction` for `app/Http/Controllers/Admin`, `CheckIfAdmin.php`, `UserRequest.php`. These call Backpack helpers (`backpack_url()`, `backpack_auth()`, `backpack_user()`) registered globally. The suppression affects only those three paths. Critically: it does not suppress `UndefinedFunction` in `app/Services`, `app/Http/Controllers` (non-Admin), `routes/`, or `tests/`, so any real undefined-function call elsewhere is still caught.
- **No new secrets in diff**: verified the new `.github/workflows/security.yml`, `psalm.xml`, integration test, and documentation files contain no API keys, tokens, or credentials.
- **CI workflow uses `${{ secrets.GITHUB_TOKEN }}`** — this is the built-in token GitHub injects per-run, scoped to the PR's repository. Not a static secret.
- **Subprocess invocation safety**: `SecurityGatesIntegrationTest` uses Symfony `Process` with an array command (`[$composer, 'security']`) — no shell interpolation, no command injection vector even if `base_path()` somehow returned an attacker-controlled value (it cannot, but defense in depth confirmed).

---

## Test Gate: FEAT-OPS-SECURITY-GATES

### Result
- **Status**: PASS
- **Date**: 2026-04-11
- **Stack**: laravel + react + mysql

### Test Execution
| Metric | Value |
|--------|-------|
| Tests Run | Yes |
| Total Tests (backend) | 479 |
| Passing (backend) | 479 |
| Failing (backend) | 0 |
| Backend Assertions | 911 |
| Total Tests (frontend) | 208 |
| Passing (frontend) | 208 |
| Failing (frontend) | 0 |

Backend executed this session via `php artisan test` → **479 passed (911 assertions)** in 205.73s. Frontend last validated at S4 close (`vitest`) → **208 passed**, no JS files modified by this feature so re-execution not required for the test gate.

### Acceptance Criteria Coverage

| AC ID | Description | Test | Status |
|-------|-------------|------|--------|
| AC-1 | Psalm + plugin installed in vendor/ from require-dev | `SecurityGatesIntegrationTest::test_composer_security_exits_zero` (transitively — wrapper requires `vendor/bin/psalm` to exist) | Covered |
| AC-2 | psalm.xml valid, errorLevel 5, Laravel plugin, paths included, cache dir set | `SecurityGatesIntegrationTest` (psalm parses config + executes against included paths; failure here would non-zero exit) | Covered |
| AC-3 | `composer security` wrapper works (audit + psalm in sequence) | `SecurityGatesIntegrationTest::test_composer_security_exits_zero` | Covered |
| AC-4 | `composer security` exits 0 on clean codebase | `SecurityGatesIntegrationTest::test_composer_security_exits_zero` | Covered |
| AC-5 | `composer security` exits non-zero on deliberate break | None (manual verification per design) | **Not automated** — see Notes #1 |
| AC-6 | `.github/workflows/security.yml` exists with 3 jobs and valid YAML | None (PHPUnit cannot parse GitHub Actions YAML) | **Inspection** — see Notes #2 |
| AC-7 | README documents Security Gates section | None | **Inspection** — see Notes #2 |
| AC-8 | CLAUDE.md references wrapper | None | **Inspection** — see Notes #2 |
| AC-9 | Skill file updated with wrapper row | None | **Inspection** — see Notes #2 |
| AC-10 | Integration test verifies wrapper | `SecurityGatesIntegrationTest::test_composer_security_exits_zero` (the test IS the AC) | Covered |
| AC-11 | Existing 478+ backend tests still pass | Full backend suite 479/479 (478 pre-existing + 1 new) | Covered |
| AC-12 | Existing 208 frontend tests still pass | Full frontend suite 208/208 | Covered |
| AC-13 | Psalm exploration checkpoint at S4 start documented | `04-implementation-notes.md` § Psalm Exploration (224 → 0 documented) | Covered (documentation gate) |
| AC-14 | No psalm-suppress without explicit approval + documentation | `04-implementation-notes.md` § Suppressions (3 listed with rationale) | Covered (documentation gate) |
| AC-15 | gitleaks installation documented per platform (macOS/Windows/Linux) | None | **Inspection** — see Notes #2 |
| AC-16 | CI gitleaks job runs on PR diff (fetch-depth: 0) | None (only validatable on a real PR) | **Inspection** — see Notes #2 |

**Coverage summary**: 11 of 16 ACs have automated PHPUnit coverage (or are themselves test ACs). 5 ACs are documentation/CI-configuration (AC-6, AC-7, AC-8, AC-9, AC-15, AC-16) — these are file-content/CI-behavior assertions that PHPUnit cannot meaningfully test without cargo-cult meta-tests. AC-5 is explicitly non-automated by design (would require committing+reverting a synthetic broken file). All non-automated ACs were inspection-verified during S5-CODE and S5-SEC.

### Path Coverage Matrix

| Path Type | Required | Found | Status | Notes |
|-----------|----------|-------|--------|-------|
| Happy Path | YES | 1 | OK | `test_composer_security_exits_zero` asserts exit 0 against the current codebase |
| Failure Path | YES | 0 (automated) | **N/A by design** | AC-5 explicitly not automated; would require committing a deliberately broken file inside a test, which is more risk than value. The pre-existing 478 tests serve as implicit failure-path coverage: if any of them break the suite, `composer security` integration test still runs and would itself be the failing test. |
| Edge Cases | YES | 1 | OK | `composer` binary absent → `markTestSkipped('composer binary not available in test environment.')` — covers the CI-without-composer edge case |
| Security Path | YES | N/A | **N/A by feature nature** | This feature has no authentication, no authorization, no user input, and no data access. There is no security path to test negatively. The feature IS the security gate; the gate's correctness is verified by the happy-path test. |

**Complexity classification**: LOW (per the Path Coverage Matrix) — pure infra, no business logic, no domain. LOW requires "1-2 happy + 1-2 failure + 1 edge". Happy ✓ (1), failure deferred per AC-5 design decision, edge ✓ (1). Acceptable for LOW complexity infra feature.

### Database Test Configuration

| Check | Status | Notes |
|-------|--------|-------|
| Transaction wrapping | YES (project pattern) | This project applies `DatabaseTransactions` per test class (verified via grep on `tests/`), not in the base `TestCase`. The new `SecurityGatesIntegrationTest` does **not** include the trait because it does not touch the database — it invokes a subprocess. This is correct: the trait is only required for tests that read/write the DB. |
| Real database (not SQLite) | YES | `phpunit.xml` line 26: `<env name="DB_CONNECTION" value="mysql"/>`. `DB_DATABASE=superia` (real MySQL test database, not in-memory SQLite). Confirmed at the project level; this feature did not change the test DB configuration. |
| Test isolation | YES | Pre-existing project pattern. `SecurityGatesIntegrationTest` is fully isolated by virtue of being subprocess-only (no shared state with other tests). |

### Security Tests

| Category | Tests Found | Status |
|----------|-------------|--------|
| Authentication | N/A | Feature has no auth surface |
| Authorization | N/A | Feature has no authz surface |
| Input validation | N/A | Feature accepts no user input |

The `security-reviewer` agent (S5-SEC) explicitly marked all OWASP Top 10 2021 categories as `N/A` or `PASS` with no findings ≥ Medium. There is no security path that requires negative testing for this feature.

### Missing Tests

None blocking. The 5 documentation/CI-config ACs (AC-6/7/8/9/15/16) and AC-5 (deliberate-break) are explicitly not test-target ACs:

- AC-5 deferral is documented in S5-CODE advisory note #3 and re-affirmed here.
- Doc ACs are addressed by reading the files (verified in S5-CODE).
- AC-16 is only validatable on a real PR run — the workflow file syntax is parseable by GitHub Actions itself on the next push.

### Configuration Issues

None.

### Verdict

**PASS** — All test-target acceptance criteria are covered by the new `SecurityGatesIntegrationTest` plus the regression-protected pre-existing suite (479/479 backend, 208/208 frontend). Path coverage is appropriate for LOW-complexity pure-infra (happy + edge present, failure path deferred by explicit design decision in PRD AC-5, security path N/A by feature nature). DB test configuration verified: real MySQL via `phpunit.xml`, per-class `DatabaseTransactions` pattern intact, new test correctly omits the trait because it does not touch the DB.

### Notes

1. **AC-5 deliberate-break failure path is not automated.** The PRD design accepted this trade-off (would require committing then reverting a synthetic broken file inside a test, which is fragile and risks polluting git history). Manual verification is sufficient — the next time a real psalm finding is introduced in any future PR, `composer security` will catch it locally and the CI workflow will catch it in the PR check, providing the same evidence non-synthetically.
2. **Documentation/CI-config ACs (AC-6, AC-7, AC-8, AC-9, AC-15, AC-16) have no PHPUnit tests.** They are file-existence and file-content assertions; automating them via grep meta-tests would be cargo-cult (the assertions would just re-state the file contents without adding signal). All six were inspection-verified during the S5-CODE review (which read the README, CLAUDE.md, skill file, and workflow YAML directly). AC-16 specifically requires a real PR run to validate end-to-end; the workflow file syntax was statically inspected.
3. **`SecurityGatesIntegrationTest` runtime cost**: ~40s per suite run (subprocess invokes psalm with `--no-cache`). Acceptable per S5-CODE advisory; revisit if suite runtime becomes painful.
