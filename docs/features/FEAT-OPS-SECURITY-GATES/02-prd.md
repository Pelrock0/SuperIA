# PRD: FEAT-OPS-SECURITY-GATES - Automated Security Gates Infrastructure

## Business Objective

Close the gap between what `.cursor/skills/security-review.md` mandates and what is actually executable in this project. Today the skill says "run `gitleaks` and `psalm --taint-analysis` BEFORE manual review", but neither tool is installed. As a result, every S5-SEC review either skips the gates (silent risk) or runs them only in the reviewer's head. This feature installs the tools, wires them into a single `composer security` command, automates them in CI, and updates the skill to reference the canonical entry point. From this feature forward, every security review can run the gates automatically and block on failures.

The investment is one-time but compounds across every future feature: Epic 5C, Epic 6, and every PR after will benefit from `composer security` running locally and `.github/workflows/security.yml` running on CI.

A secondary benefit is bringing the existing legacy codebase up to a clean static-analysis baseline: per user decision (S1 question 1, option B), this feature does NOT use a psalm baseline. Every issue psalm finds in existing code must be fixed during this feature. The result is a codebase that starts clean and stays clean — every future PR will be measured against zero psalm findings.

## Problem Statement

- **The security-review skill mandates automated gates that don't exist**. `composer audit` works (we proved it during Epic 5B). `gitleaks` and `psalm --taint-analysis` are listed in the skill but neither tool is installed in the project.
- **Manual security reviews miss things automated tools catch**. The Epic 5B review found 3 CVEs (1 high, 2 medium) only because the user explicitly asked us to read the skill and run the gates. Without the tools wired in, future reviews could silently skip them.
- **Legacy code has unknown static-analysis state**. Six epics worth of code (Epic 0 through Epic 5B) have never been scanned for type issues, taint vulnerabilities, dead code, or impossible types. We don't know what's there until we run psalm.
- **No CI security enforcement**. Even after `composer audit` is available, nothing prevents a developer from committing a CVE-introducing dependency without running it. CI must enforce the gate at PR time.
- **Skill contract drifts from reality**. If the skill says "run X" and X is not runnable, future reviewers learn to skip Section 1 of the skill. The skill should be updated to reference the wrapper command.

## Scope

### In Scope

- **Composer dev dependencies**:
  - `vimeo/psalm` ^5 (or latest stable compatible with PHP 8.4)
  - `psalm/plugin-laravel` (latest stable compatible with Laravel 12)
- **`psalm.xml`** at project root, configured with:
  - `errorLevel="5"`
  - `findUnusedBaselineEntry="false"` (no baseline used)
  - `findUnusedCode="false"` initially (avoid noise on Laravel magic)
  - Project paths included: `app/`, `database/`, `routes/`, `tests/`
  - Vendor + node_modules + storage + bootstrap/cache excluded
  - `psalm-plugin-laravel` registered
  - Cache directory configured under `storage/framework/psalm` (gitignored)
- **`composer.json` script**: `"security": "@composer audit && vendor/bin/psalm --taint-analysis"` under the `scripts` section
- **Legacy code fixes**: every psalm finding on the existing codebase resolved. Type hints added, return types declared, taint sources annotated where needed. **Scope checkpoint at S4 start**: count findings before committing to fix-all (per S1 Open Questions checkpoint).
- **`.github/workflows/security.yml`** with three jobs:
  - `composer-audit` — runs `composer audit` on the locked dependencies
  - `psalm-taint` — runs `composer security` (which includes psalm)
  - `gitleaks` — runs `gitleaks/gitleaks-action@v2` against the PR diff
  - All three jobs run on `pull_request` events. Failure of any blocks merge (via branch protection rules, configured manually in GitHub repo settings — out of scope of file-only changes).
- **Documentation**:
  - `README.md` new "Security Gates" section with: list of gates, how to run `composer security`, how to install `gitleaks` per platform (brew/scoop/binary download/`go install`), pointer to CI workflow
  - `CLAUDE.md` short reference: "Before running S5-SEC review, execute `composer security`. The security-review skill in `.cursor/skills/security-review.md` is the source of truth."
- **Skill update** `.cursor/skills/security-review.md` §1 Automated Gates: add a top note "Run `composer security` to invoke `composer audit + psalm --taint-analysis` together. Individual commands listed below for transparency." Add a row in the gates table for the wrapper.
- **Test**: `tests/Feature/SecurityGatesIntegrationTest.php` that runs `composer security` via Symfony Process and asserts exit code 0. Skipped if running in environment without composer (CI fallback).
- **`.gitignore`**: ensure `storage/framework/psalm` excluded.

### Out of Scope

- **Local pre-commit gitleaks hook installer** (`bin/install-gitleaks-hook.sh`) — per S1 decision 3, gitleaks is documentation + CI only.
- **GitHub branch protection rules** — file-based changes can't configure repo-level protection. Documented in README as a manual GitHub admin task.
- **PHPStan / Larastan** as alternative SAST — chose psalm + Laravel plugin per S1 decision 6.
- **GitLab CI / Bitbucket Pipelines / Jenkins** workflows — only GitHub Actions, since the project hosts on GitHub.
- **Storybook for visual regression** — not security related.
- **Backwards-fixing other Epics' security review docs** — Epic 5A retrofit is a separate planned feature, not bundled here.
- **Adding psalm-suppress comments to bypass findings** — every finding must be fixed, not suppressed. Exceptions require explicit user approval per finding.
- **Fixing non-psalm-related legacy issues** discovered during the fix pass — log them as separate tech debt, do NOT expand scope.
- **Custom psalm plugins or rules** — use plugin-laravel out of the box.
- **Multiple composer scripts variants** (`security:gates`, `security:full`) — per S1 decision 7.
- **Performance baselines for psalm** — accept whatever psalm runtime is and document it.
- **Running gitleaks on git history** (not just PR diff) — full history scan is a separate one-off task with its own remediation flow.

## Acceptance Criteria

### AC-1: Psalm and plugin installed
- **Given**: a fresh `composer install` on this project
- **When**: composer resolves dependencies
- **Then**: `vendor/bin/psalm` exists and `vendor/psalm/plugin-laravel` is present in `vendor/`. Both come from `require-dev`.

### AC-2: Psalm config exists and is valid
- **Given**: the psalm.xml file at project root
- **When**: `vendor/bin/psalm --show-info=false` runs
- **Then**: psalm parses the config without errors. `errorLevel="5"`. The Laravel plugin is registered. The included paths cover `app`, `database`, `routes`, `tests`. The cache directory points to `storage/framework/psalm`.

### AC-3: composer security wrapper works
- **Given**: psalm is installed and configured
- **When**: a developer runs `composer security` from the project root
- **Then**: composer first runs `composer audit` (exits 0 if no advisories), then runs `vendor/bin/psalm --taint-analysis`. Final exit code is 0 only if both succeed.

### AC-4: composer security exits 0 on a clean codebase
- **Given**: the codebase after legacy fixes from this feature
- **When**: `composer security` runs in a clean checkout
- **Then**: exit code is 0. No psalm findings reported. No `composer audit` advisories.

### AC-5: composer security exits non-zero when a new psalm finding is introduced
- **Given**: a clean codebase
- **When**: a developer introduces a deliberately broken file (e.g., a function with no return type returning a mixed value)
- **Then**: `composer security` exits with non-zero code and reports the finding via psalm output.

### AC-6: GitHub Actions workflow exists and is valid YAML
- **Given**: the file `.github/workflows/security.yml`
- **When**: GitHub Actions parses the workflow on PR
- **Then**: the workflow has three jobs (`composer-audit`, `psalm-taint`, `gitleaks`). All three jobs run on `pull_request` events. The workflow file is valid YAML.

### AC-7: README documents installation and usage
- **Given**: a developer reading the project README
- **When**: they navigate to the "Security Gates" section
- **Then**: they find: (a) what `composer security` does, (b) how to install gitleaks on macOS / Windows / Linux, (c) a pointer to `.cursor/skills/security-review.md` and `.github/workflows/security.yml`.

### AC-8: CLAUDE.md references the wrapper
- **Given**: a future Claude Code session reads CLAUDE.md
- **When**: it reaches the security review section
- **Then**: there is a line "Before running S5-SEC review, execute `composer security`" with a pointer to the skill file.

### AC-9: Skill file updated
- **Given**: `.cursor/skills/security-review.md` after this feature
- **When**: a reviewer opens §1 Automated Gates
- **Then**: there is a top note referencing `composer security` as the wrapper command, and the gates table includes a row for it.

### AC-10: Integration test verifies the wrapper
- **Given**: the test `tests/Feature/SecurityGatesIntegrationTest.php`
- **When**: it runs as part of the backend suite
- **Then**: it executes `composer security` via Symfony Process and asserts exit code 0. The test skips gracefully if `composer` binary is not available in the test environment.

### AC-11: Existing 478+ backend tests still pass
- **Given**: the legacy code fixes applied during this feature
- **When**: `php artisan test` runs the full backend suite
- **Then**: every test that was passing before this feature is still passing. No regressions introduced by the type hints / return type additions / refactors.

### AC-12: Existing 208 frontend tests still pass
- **Given**: the legacy code fixes only touch PHP
- **When**: `npm test` runs
- **Then**: all 208 frontend tests still pass (sanity check, since this feature doesn't touch JS).

### AC-13: Psalm exploration checkpoint at S4 start
- **Given**: psalm and plugin freshly installed at S4 start
- **When**: a "dry exploration" pass runs (`vendor/bin/psalm --show-info=false`) and counts findings
- **Then**: the count is documented in `04-implementation-notes.md`. If <50 → proceed with fix-all in this feature. If 50-200 → batch-fix with checkpoints. If >200 → STOP and renegotiate scope with the user before continuing S4.

### AC-14: No psalm-suppress comments added without explicit approval
- **Given**: a finding that is genuinely a false positive (e.g., Laravel facade behavior the plugin can't model)
- **When**: a developer wants to suppress it
- **Then**: they ask the user explicitly, justify the suppression, and document each suppression in `04-implementation-notes.md` with rationale. Default is to fix, not suppress.

### AC-15: gitleaks installation documented per platform
- **Given**: a new developer clones the project on macOS, Windows, or Linux
- **When**: they read the README "Security Gates" section
- **Then**: they find platform-specific install instructions for gitleaks (brew on macOS, scoop on Windows, binary download or `go install` on Linux).

### AC-16: CI workflow gitleaks job runs on PR diff only
- **Given**: a PR opened on the GitHub repo
- **When**: the security workflow triggers
- **Then**: the gitleaks job runs against the PR diff (not the full git history). Full-history scans are out of scope per the Out of Scope list.

## UX Decision

- **UX Designer Required**: NO
- **UX Artifacts**: N/A — this is a developer infrastructure feature with zero user-facing surface.
- **Basic UX Notes**: The only "UX" surface is the developer experience (DX) of running `composer security`. The output is psalm's standard CLI output (already established UX). README documentation should be clear enough that a new developer can install and use without asking questions.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Unknown number of psalm findings on legacy code | Technical | S4 starts with a "psalm exploration" pass. Hard checkpoints at <50 / 50-200 / >200 findings. Above 200 forces a scope renegotiation before any fixes. |
| `psalm/plugin-laravel` incompatible with Laravel 12 | Technical | Verify compatibility at S4 start (composer install will fail or warn). Fallback: install psalm without plugin, accept higher false positive rate, document it. If impossible, escalate to scope change. |
| Legacy fixes introduce regressions in existing 478 tests | Test stability | After every batch of fixes, run full backend suite. Revert any fix that causes a regression and document why. Keep batches small (one component at a time). |
| Psalm runtime too slow for `composer security` to be practical | Performance | Measure runtime at S4. If >60s on full project, configure incremental cache. If still slow, scope a smaller default path set and document. |
| GitHub Actions workflow YAML syntax errors blocking all PRs | Operational | Validate the workflow file locally with `yamllint` (if available) or against GitHub's schema before committing. Test in a draft PR first. |
| Branch protection rules require manual GitHub admin setup | Operational | Documented in README as a separate admin task. The file-based workflow will exist; enabling it as required is a one-time GitHub UI step. |
| Developers blocked by `composer security` failing on every commit during the legacy-fix phase | Operational | The legacy fixes happen in this feature, so by the time it merges, the codebase is clean. Until merge, this feature is the only one touching legacy fixes. After merge, every PR starts clean. |
| `composer security` integration test depends on subprocess invocation in test environment | Technical | Use Symfony `Process` with try/catch. Skip the test gracefully (`$this->markTestSkipped`) if composer binary is not findable. Document in test class. |
| Suppressions accumulate over time and undermine the gate | Quality | AC-14 requires explicit approval per suppression + documentation in implementation notes. Make suppressions hard to add silently. |
| Psalm plugin Laravel needs additional config for Eloquent models | Technical | Plugin documentation typically requires generating Eloquent IDE helpers. Run `php artisan ide-helper:generate` if needed and commit the generated files. Out of scope to install ide-helper if not present — document as prerequisite. |

## Assumptions

- `vimeo/psalm` ^5 supports PHP 8.4 (current project version). Verified at S4 start.
- `psalm/plugin-laravel` supports Laravel 12. **Risk** if not — fallback documented above.
- The developer running `composer security` has `composer` and PHP 8.4 in PATH. CI uses standard `setup-php` action.
- The GitHub repo has Actions enabled. (If not, the workflow file is dormant — non-blocking.)
- Branch protection rules enabling required checks is a manual one-time admin task and out of scope of file-based deliverables.
- The psalm "fix-all" workload is bounded enough to fit in 8-20 hours. The exploration phase at S4 start validates this assumption.
- Existing tests have meaningful coverage so that regressions from legacy fixes are caught.
- `gitleaks/gitleaks-action@v2` is the canonical GitHub Action for gitleaks (verified at S4 start by checking the marketplace).

## Open Questions

None blocking S2 → S3 transition. The S4 exploration phase contains the only operational checkpoint (psalm finding count threshold).

## Approval

- [ ] PRD approved by [user] on [date]

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
- Required Artifacts for Next Step: 01-scope.md, 02-prd.md
