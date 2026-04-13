# Technical Design: FEAT-OPS-SECURITY-GATES

## Overview

Pure infrastructure feature with three deliverables glued together by one composer script: (1) install `vimeo/psalm` + `psalm/plugin-laravel` as dev dependencies and configure them via `psalm.xml` for Laravel 12 + PHP 8.4, (2) wrap `composer audit` and `psalm --taint-analysis` behind a single `composer security` command that exits non-zero on any failure, (3) wire the same gates into a GitHub Actions workflow that blocks PR merges. The skill `.cursor/skills/security-review.md` is updated to reference the wrapper as the canonical entry point so future S5-SEC reviews use one command instead of three.

The dominant risk is unknown — we don't know how many psalm findings the legacy codebase has until we run the tool. The design therefore mandates a **psalm exploration checkpoint** at S4 start: install psalm, run a dry pass, count findings, branch the implementation strategy based on the count (<50 / 50-200 / >200). The PRD's AC-13 gates this checkpoint. Above 200 findings, the design forces a STOP and scope renegotiation with the user.

A Symfony Process based feature test runs `composer security` as a subprocess and asserts exit code 0. The test skips gracefully if `composer` is not findable in PATH so CI environments without composer don't break the suite. The test is the proof-of-life that the wrapper actually works on the current codebase.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | N/A — no business entities | — |
| Services | N/A — no business logic | — |
| Infrastructure | Composer dependencies, psalm config, GitHub Actions workflow, git hooks (none) | `composer.json`, `composer.lock`, `psalm.xml`, `.github/workflows/security.yml` |
| Documentation | Developer docs, AI agent docs, skill updates | `README.md`, `CLAUDE.md`, `.cursor/skills/security-review.md` |
| Tests | Integration test asserting `composer security` exits 0 | `tests/Feature/SecurityGatesIntegrationTest.php` |
| Legacy code fixes | All `app/`, `database/`, `routes/`, `tests/` PHP files psalm flags. **Bounded by exploration checkpoint at S4 start.** | Anywhere psalm reports issues |

### Data Flow

#### `composer security` (developer machine, on-demand)
```
1. Developer runs `composer security` from project root
2. Composer reads scripts.security from composer.json
3. Composer executes `@composer audit`:
     a. composer fetches advisories DB
     b. compares against composer.lock
     c. exits 0 if clean, non-zero if any advisory found
4. If audit succeeds, composer executes `vendor/bin/psalm --taint-analysis`:
     a. psalm reads psalm.xml
     b. psalm scans included paths (app/, database/, routes/, tests/)
     c. psalm reports findings to stdout
     d. exits 0 if zero findings, non-zero if any finding
5. composer security final exit code = max(audit_code, psalm_code)
```

#### CI security workflow (GitHub Actions, on PR)
```
1. PR opened or updated against main
2. .github/workflows/security.yml triggers
3. Three jobs run in parallel:
   a. composer-audit:
      - actions/checkout
      - shivammathur/setup-php with PHP 8.4
      - composer install --no-dev --no-progress
      - composer audit
   b. psalm-taint:
      - actions/checkout
      - shivammathur/setup-php with PHP 8.4
      - composer install --no-progress (with dev deps)
      - composer security
   c. gitleaks:
      - actions/checkout with fetch-depth 0
      - gitleaks/gitleaks-action@v2
4. Any job failure marks the PR check as failed
5. Branch protection (configured in GitHub admin UI, out of scope) blocks merge
```

#### Integration test (test suite, every `php artisan test` run)
```
1. SecurityGatesIntegrationTest::test_composer_security_exits_zero
2. Locate composer binary via `(new ExecutableFinder)->find('composer')`
3. If not found → markTestSkipped (CI without composer is acceptable)
4. Run `composer security` via Symfony Process with timeout 120s
5. Assert exit code 0
6. On failure, output stdout/stderr in the assertion message for debugging
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| `composer security` | None (no DB writes) | Pure read-only static analysis + dependency check |
| Integration test | None | Subprocess invocation, no DB |
| Legacy fixes | Each batch is its own commit (suggested) | Ability to revert a batch if it breaks tests, without losing other batch progress |

## Data Model

### New Tables
None — this feature touches zero database schema.

### Migrations
None.

### API Changes
None.

### Config Files

| File | Purpose |
|------|---------|
| `psalm.xml` | psalm configuration: errorLevel 5, paths, plugin registration, cache dir |
| `.github/workflows/security.yml` | GitHub Actions CI workflow with 3 jobs (composer-audit, psalm-taint, gitleaks) |

### Composer Manifest Changes

```json
{
    "require-dev": {
        "...": "...",
        "vimeo/psalm": "^5.26",
        "psalm/plugin-laravel": "^2.11"
    },
    "scripts": {
        "...": "...",
        "security": [
            "@composer audit",
            "vendor/bin/psalm --taint-analysis"
        ]
    }
}
```

Versions chosen at S4 start based on Packagist availability + PHP 8.4 / Laravel 12 compatibility. Above are starting points; if newer stable versions exist they take precedence.

## psalm.xml structure

```xml
<?xml version="1.0"?>
<psalm
    errorLevel="5"
    resolveFromConfigFile="true"
    findUnusedBaselineEntry="false"
    findUnusedCode="false"
    cacheDirectory="storage/framework/psalm"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="https://getpsalm.org/schema/config"
    xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd"
>
    <projectFiles>
        <directory name="app" />
        <directory name="database" />
        <directory name="routes" />
        <directory name="tests" />
        <ignoreFiles>
            <directory name="vendor" />
            <directory name="storage" />
            <directory name="bootstrap/cache" />
            <directory name="node_modules" />
        </ignoreFiles>
    </projectFiles>

    <plugins>
        <pluginClass class="Psalm\LaravelPlugin\Plugin" />
    </plugins>
</psalm>
```

Decisions baked in:
- `errorLevel="5"`: per S1 question 2 — balanced strict starting point.
- `findUnusedBaselineEntry="false"`: per S1 question 1 (option B) — no baseline used.
- `findUnusedCode="false"`: Laravel magic (controllers, listeners, jobs) is full of "unused" methods that are actually invoked via routing/events. Enable later if cleaner.
- `cacheDirectory="storage/framework/psalm"`: keeps psalm cache out of git but inside Laravel's standard storage tree.
- Plugin registered via `<plugins><pluginClass>` — psalm-plugin-laravel exposes `Psalm\LaravelPlugin\Plugin`.

## .github/workflows/security.yml structure

```yaml
name: Security Gates

on:
  pull_request:
  push:
    branches: [main]

jobs:
  composer-audit:
    name: composer audit
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          tools: composer:v2
      - run: composer install --no-progress --no-interaction
      - run: composer audit

  psalm-taint:
    name: psalm + taint analysis
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          tools: composer:v2
      - run: composer install --no-progress --no-interaction
      - run: composer security

  gitleaks:
    name: gitleaks secret scan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - uses: gitleaks/gitleaks-action@v2
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

Decisions:
- Both `pull_request` and `push: main` triggers — PR validates before merge, push catches anything that bypassed PR (force pushes etc).
- Three independent jobs in parallel for fast feedback. A failure in one doesn't block visibility of others.
- `gitleaks` job fetches full history (`fetch-depth: 0`) so the action can compare against base branch. The gitleaks action itself decides what to scan.
- `GITHUB_TOKEN` passed to gitleaks for PR comment posting (default behavior of gitleaks-action v2).
- No matrix builds, no caching — keep it simple. Add caching if runtime exceeds 5 minutes.

## SecurityGatesIntegrationTest design

```php
<?php

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SecurityGatesIntegrationTest extends TestCase
{
    public function test_composer_security_exits_zero(): void
    {
        $composer = (new ExecutableFinder())->find('composer');

        if ($composer === null) {
            $this->markTestSkipped('composer binary not available in test environment.');
        }

        $process = new Process(
            command: [$composer, 'security'],
            cwd: base_path(),
            timeout: 120,
        );

        $process->run();

        $this->assertSame(
            0,
            $process->getExitCode(),
            "composer security failed with exit code {$process->getExitCode()}.\n"
            ."STDOUT:\n{$process->getOutput()}\n"
            ."STDERR:\n{$process->getErrorOutput()}",
        );
    }
}
```

Notes:
- `ExecutableFinder` is part of `symfony/process`, already a Laravel transitive dep — no new package.
- `markTestSkipped` instead of `fail` if composer is missing — CI environments without composer should not fail this specific test.
- Timeout 120s covers psalm cold-cache runs. Subsequent runs are cached and faster.
- The full stdout/stderr is dumped on failure so debugging is possible from the test report.
- This test is intentionally **slow** (psalm scan is the heavy part). If it adds >30s to the suite, mark it `@group slow` or similar to allow opt-out — decision deferred to S4 measurement.

## Skill update

Add to `.cursor/skills/security-review.md` §1 Automated Gates, just below the existing intro:

```markdown
> **Wrapper command**: run `composer security` to invoke `composer audit + psalm --taint-analysis` together (Laravel/PHP stacks). Equivalent multi-command sequences for other stacks listed below for transparency.
```

And add a new row at the top of the gates table:

```markdown
| **Wrapper (Laravel)** | `composer security` | High/Critical (audit) or any psalm finding |
```

The existing rows for individual commands stay in place — they document the pieces. The wrapper is the canonical entry point.

## README documentation structure

New section after the existing setup instructions:

```markdown
## Security Gates

This project enforces three automated security gates on every PR:

1. **`composer audit`** — fails on any High/Critical CVE in dependencies
2. **`psalm --taint-analysis`** — fails on any new psalm finding (taint or static type)
3. **`gitleaks`** — fails on any verified secret in the PR diff

### Running locally

```bash
composer security        # runs gates 1 + 2
gitleaks detect          # runs gate 3 (requires gitleaks installed, see below)
```

### Installing gitleaks

| Platform | Command |
|----------|---------|
| macOS    | `brew install gitleaks` |
| Windows  | `scoop install gitleaks` |
| Linux    | `go install github.com/gitleaks/gitleaks/v8@latest` or download binary from [GitHub releases](https://github.com/gitleaks/gitleaks/releases) |

### CI

The same gates run on every PR via `.github/workflows/security.yml`. Failed gates block the PR check; branch protection rules (configured separately in GitHub admin) require all three to pass before merge.

### Reference

Full security review checklist lives in `.cursor/skills/security-review.md`.
```

## CLAUDE.md addition

Single paragraph appended after the existing workflow rules:

```markdown
### Security Reviews (S5-SEC)

Before running an S5-SEC review, execute `composer security` to run the automated gates (composer audit + psalm --taint-analysis). The full skill at `.cursor/skills/security-review.md` is the source of truth for the manual review checklist (OWASP Top 10 2021, OWASP API Top 10 2023, OWASP LLM Top 10 v2 2025). Both the wrapper and the skill are mandatory.
```

## Performance

### Query optimization
N/A — no DB queries.

### Caching strategy

| Cache | Key | TTL | Invalidation |
|-------|-----|-----|--------------|
| psalm analysis cache | `storage/framework/psalm/*` | until file changes | psalm auto-invalidates per file |

Psalm uses content-hash-based per-file caching. First run is slow (~30s on this codebase size estimate), subsequent runs are fast (~3-5s) when only a few files changed.

### Async processing
N/A.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Psalm with baseline (S1 option A) | Quick adoption, no legacy fix work | Legacy code stays unverified | Rejected per user decision (chose option B) |
| Psalm without baseline (S1 option B, chosen) | Clean codebase from day 1, all future PRs measured against zero | Unbounded legacy fix work, requires exploration checkpoint | **Selected** per user decision |
| `phpstan` + `larastan` instead of psalm | Larger ecosystem, more Laravel-aware out of box | Switches engine, no taint-analysis built-in (security skill specifically requires taint) | Rejected — taint analysis is the security-critical feature |
| `composer security` wrapper (chosen) | One command, easy to remember, easy to wire into skill + CI | Hides the individual commands behind an alias | **Selected** — transparency preserved by listing individual commands in skill |
| Multiple `security:*` script variants | Granular control | Confusing, more docs, scope creep | Rejected per S1 decision 7 |
| GitHub Actions workflow in this feature (chosen) | CI gate enforced from day 1 | Adds CI complexity that this team may not have prior experience with | **Selected** — without CI the gates are aspirational |
| Defer GitHub Actions to a future feature | Smaller scope now | Gates aren't enforced in CI until then; risk of forgetting | Rejected |
| `gitleaks` as local pre-commit hook installer | Catches secrets before commit | Forces hooks on all devs (invasive) | Rejected per S1 decision 3 |
| `gitleaks` as doc + GitHub Actions only (chosen) | CI-enforced, dev choice for local | Local mistakes only caught at PR time | **Selected** — best balance |
| `findUnusedCode="true"` | Detects dead code | Laravel magic produces tons of false positives (controllers, listeners, jobs) | Rejected for V1 — enable later after cleanup |
| `errorLevel="3"` (stricter) | More bugs caught | Probably blows up the legacy fix workload | Rejected — start at 5, lower over time |
| `errorLevel="7"` (more permissive) | Fewer findings | Misses meaningful bugs | Rejected — chosen 5 per user |
| Symfony Process for the integration test (chosen) | Real subprocess invocation, real exit codes | Test is slow (~30s with cold cache) | **Selected** — real proof-of-life is worth the slowness |
| Mock subprocess for the integration test | Fast test | Doesn't actually verify the wrapper works | Rejected — defeats the purpose |
| Branch protection enforced via Terraform / API call | Enforced as code | New tooling burden | Rejected — manual GitHub admin task documented in README |
| `psalm-suppress` comments to bypass legacy issues | Faster delivery | Defeats the gate's purpose | Rejected per AC-14 — suppressions require explicit approval per case |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Psalm reports >200 findings on legacy code | High (multi-week effort) | Medium-High (unknown until measured) | **Hard checkpoint at S4 start** (AC-13). Above 200 → STOP and renegotiate with user. Below → proceed. |
| `psalm/plugin-laravel` incompatible with Laravel 12 | High | Medium | Verify at S4 start before any code changes. Fallback: psalm without plugin + accept higher false positive rate documented. |
| Legacy fixes break existing 478 tests | Medium | Medium | After every batch of fixes, re-run full backend suite. Revert any batch causing regressions. |
| Composer audit catches a new CVE post-merge | Medium | Low | The CI workflow runs on every PR, so future CVEs are caught at PR time. The integration test runs `composer security` in the test suite, so any audit failure is also caught locally. |
| GitHub Actions YAML syntax error | Low | Low | Workflow is small, manually validatable. Test in a draft PR before merging the feature. |
| Branch protection not configured by GitHub admin | Medium (gates exist but don't block) | Medium | Documented as a manual one-time admin task in the README. The workflow file works regardless; enabling required-checks is independent. |
| Subprocess invocation fails on CI runners | Medium (test always skipped, never validates) | Low | The test uses `markTestSkipped` if composer is not in PATH. CI runners with composer (which is the standard) will exercise it. Verify in the actual GitHub Actions run. |
| psalm cache corruption causes false negatives | Low | Low | `composer security` does not use `--diff` mode by default; full scan every run. Cache only speeds up repeated runs. |
| New psalm finding introduced silently between PRs | Low | Low | CI runs psalm on every PR; the gate catches it. Local runs catch it before push for diligent devs. |
| `findUnusedCode="false"` hides genuine dead code | Low | Medium | Acceptable trade-off for V1. Enable later as a separate cleanup feature. |
| psalm.xml schema URL changes | Low | Low | Schema URL is versioned in vendor; if it breaks, regenerate. |
| Skill update breaks compatibility with Cursor agent file reading | Low | Low | The skill is a Markdown file, additions are append-only; existing readers continue to work. |

## Implementation Notes

### Suggested execution order for S4

1. **Psalm exploration phase** (gate-keeper for the rest of S4):
   - Add `vimeo/psalm` + `psalm/plugin-laravel` to composer.json require-dev
   - Run `composer install`
   - Verify Laravel 12 compatibility — if plugin install fails, escalate to user
   - Create minimal `psalm.xml` (errorLevel 5, paths)
   - Run `vendor/bin/psalm --no-cache --show-info=false` and **count findings**
   - Document the count in `04-implementation-notes.md`
   - **Apply AC-13 thresholds**:
     - <50 → continue with fix-all in this feature
     - 50-200 → batch-fix with checkpoints between batches
     - **>200 → STOP and ask user to choose: revert to baseline (option A), split into multiple features, or accept multi-week effort**
2. **Fix all psalm findings in batches**:
   - Group by file or by component
   - After each batch: run `vendor/bin/psalm` to verify the batch is clean, run `php artisan test` to verify no regressions
   - Document each batch in implementation notes
3. **Add `composer security` script** to composer.json scripts section
4. **Run `composer security`** to verify the full pipeline works
5. **Create `.github/workflows/security.yml`**
6. **Create `tests/Feature/SecurityGatesIntegrationTest.php`** + run it
7. **Update `README.md`** with the Security Gates section
8. **Update `CLAUDE.md`** with the wrapper reference
9. **Update `.cursor/skills/security-review.md`** §1 + table
10. **Verify .gitignore** excludes `storage/framework/psalm`
11. **Run full backend + frontend suites** as final regression check
12. **Run `composer security` one final time** and confirm exit 0
13. **Write `04-implementation-notes.md`** with final findings count, batch breakdown, any suppressions documented

### Critical invariants to assert in tests

- `composer security` exits 0 on the current main branch state (after fixes)
- `composer security` exits non-zero when a new finding is introduced
- Existing 478 backend tests still pass
- Existing 208 frontend tests still pass
- `psalm.xml` is valid and references the Laravel plugin
- `.github/workflows/security.yml` is valid YAML

### Frontend work identified
None. This is a backend/infrastructure feature with zero JS changes.

## Open Questions

None blocking S3 → S4 transition. The S4 exploration phase contains the only operational checkpoint (psalm finding count threshold per AC-13).

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation
- Required Artifacts: 01-scope.md, 02-prd.md, 03-technical-design.md
