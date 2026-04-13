# Scope Analysis: FEAT-OPS-SECURITY-GATES

## Feature Request

Install and configure the automated security gates mandated by `.cursor/skills/security-review.md` so that future S5-SEC reviews can run them automatically. Currently the skill mandates `composer audit`, `gitleaks detect`, and `psalm --taint-analysis` to run BEFORE manual review, but only `composer audit` is available in this project — `gitleaks` and `psalm` are not installed. This feature closes that gap as a permanent infrastructure investment that all future security reviews benefit from.

Deliverables:
- `vimeo/psalm` + `psalm/plugin-laravel` installed as dev dependencies
- `psalm.xml` configured for Laravel project (errorLevel 5)
- **Fix all psalm findings in existing codebase** (no baseline — option B per user decision; legacy code is brought up to standard during this feature)
- `composer security` script wrapping `composer audit && vendor/bin/psalm --taint-analysis`
- README + CLAUDE.md documentation for installing `gitleaks` (external binary)
- GitHub Actions workflow `.github/workflows/security.yml` running all gates on every PR
- Update `.cursor/skills/security-review.md` to reference the `composer security` wrapper as the entry point
- Test that `composer security` exits with code 0 on the current codebase

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 8-20 hours (highly variable, depends on # of psalm findings on legacy code) |
| Confidence | Low |

## Justification

HIGH because:
1. **Unbounded legacy fix work**. Per user decision (option B for question #1), this feature does NOT use a psalm baseline. Every issue psalm reports on the existing codebase must be fixed. The codebase has 6 epics worth of code (Epic 0-5B). Without running psalm first, the count of findings is unknown — could be dozens, could be thousands. This is the dominant risk and the reason for the HIGH classification.
2. **Affects every PHP file in the project**. Fixing psalm findings means touching `app/`, `config/`, `database/`, `routes/`, `tests/` — anywhere that has PHP.
3. **Test stability risk**: fixes to legacy code that the existing 478 tests cover may surface latent bugs or break tests. Each fix requires re-running the suite.
4. **Plugin compatibility**: `psalm/plugin-laravel` must support Laravel 12. If incompatible, manual config covering Laravel patterns is significantly harder and may flag many more "bugs" that are actually Laravel idioms.
5. **GitHub Actions secrets**: gitleaks in CI requires GitHub-level config, not just file commits.
6. **Skill update** modifies the workflow contract for every future S5-SEC review.

**Why this is risky**: a "fix everything" approach with psalm on a legacy codebase has historically been a multi-week undertaking on similar projects. The 8-20h estimate assumes findings are mostly minor (missing return types, undefined property accesses) but could explode if psalm finds large gaps in tipo coverage or genuine bugs.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | **High** | Unknown number of psalm findings in legacy codebase. Could be 50, could be 500. No baseline → every finding must be fixed. Fixing forces touching every PHP file. Plugin compatibility risk with Laravel 12. **Mitigation**: psalm exploration phase at S4 start before committing to fix-all (see Open Questions). |
| Data | N/A | No data/schema changes. |
| Security | Low | This feature **strengthens** security by adding automated gates. The only risk is misconfiguring psalm to ignore real findings — without baseline this risk is eliminated. |
| Performance | Low | `composer security` runs only on developer demand or in CI, not on every request. Psalm scans take 10-30s on this codebase size — acceptable. |
| Operational | Medium | Affects every developer's local workflow (new `composer security` command). Affects every PR (new CI gate that can block merge). Documentation must be clear or developers will be blocked by unexpected gate failures. **Without baseline**: every developer who pulls main must have a clean tree against psalm — no "broken main" tolerance. |
| Test stability | Medium | Fixing legacy code may surface latent bugs or break the existing 478 tests. Mitigation: re-run full backend suite after each batch of fixes. |

## Affected Areas

- `composer.json` — add `vimeo/psalm` + `psalm/plugin-laravel` as `require-dev`
- `composer.json` — add `composer security` script under `scripts`
- `composer.lock` — auto-updated by composer
- `psalm.xml` — new file, project root, configures error level 5 + included paths + plugin
- ~~`psalm-baseline.xml`~~ — **NOT created** per user decision (option B)
- `.gitignore` — verify psalm cache directory excluded
- **Legacy code fixes across `app/`, `config/`, `database/`, `routes/`, `tests/`** — every PHP file psalm reports issues in. **Unbounded scope until psalm runs.**
- `README.md` — new "Security gates" section with `composer security` usage + `gitleaks` install instructions per platform (brew/scoop/binary download)
- `CLAUDE.md` — short reference to `composer security` as the pre-review gate
- `.github/workflows/security.yml` — new file, runs `composer audit + composer security + gitleaks-action` on every PR
- `.cursor/skills/security-review.md` — update §1 Automated Gates to reference `composer security` wrapper as the canonical command, keep individual commands for transparency
- `tests/Feature/SecurityGatesIntegrationTest.php` — new feature test that runs `composer security` via `Process` and asserts exit code 0
- **NOT included**: `bin/install-gitleaks-hook.sh` — local pre-commit hook installer (per decision 3, gitleaks is doc + CI only)

## Resolved Questions

(Resolved by user explicit selection.)

1. **Psalm baseline strategy**: **Option B — NO baseline**. Start without baseline, fix all issues that appear in legacy code. User accepted the unbounded fix-all-findings approach. **Implication**: effort estimate is highly variable (8-20h+) and depends on what psalm finds at S4 start. The first action in S4 will be a "psalm exploration" pass to count findings before committing to fix all.

2. **Initial strictness level**: **`errorLevel="5"`** (psalm scale: 1=strict to 8=permissive). Mid-strict starting point. Without baseline (per #1), this combination is the dominant variable for the legacy fix workload.

3. **Gitleaks strategy**: **Option D — Documentation + GitHub Actions only**. No local pre-commit hook installer because forcing pre-commit hooks on other developers is invasive. Each developer can opt-in via personal `~/.git/hooks/pre-commit` if they want. CI is the obligatory gate.

4. **GitHub Actions workflow**: **Option A — Included in this feature**. The workflow runs `composer audit`, `composer security`, and `gitleaks-action` on every PR. Failure blocks merge.

5. **Skill update**: **Option A — Update `.cursor/skills/security-review.md` §1**. Add a one-line note that `composer security` is the wrapper that runs `composer audit + psalm --taint-analysis` together. Keep the individual commands listed for transparency. Add `gitleaks-action` as the GitHub Action equivalent.

6. **Laravel psalm plugin**: **Option A — Include `psalm/plugin-laravel`** as a dev dep. Without it, psalm reports hundreds of false positives on Laravel facades, container resolution, Eloquent magic methods. Plugin compatibility verified at S4 start before committing — if incompatible with Laravel 12, fallback documented.

7. **Composer scripts**: **Option A — Only `composer security`**. Single canonical command that runs `composer audit && vendor/bin/psalm --taint-analysis`. No `:gates` / `:full` variants. Tests are still `php artisan test`, separate concern.

## Open Questions

None blocking S1 → S2 transition.

**One operational question for S4 to resolve at start:**
- After installing psalm + plugin, run a "dry exploration" pass and count findings. If the count is **<50**, proceed with fix-all in this feature. If **50-200**, batch-fix with checkpoints between batches. If **>200**, **STOP and renegotiate scope** with user — likely options at that point: (a) revert to option A (baseline), (b) split into multiple features (5B-style), (c) accept multi-week effort. The S2 PRD will document this exploration phase as a hard checkpoint.

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)

## Note on this scope

This is an **operational/infrastructure feature**, not a product feature. It does not implement HU-X. It implements a permanent quality gate that all subsequent product features will benefit from. The workflow S1-S6 is followed because it changes shared codebase + CI + skill contract — the same gates apply.
