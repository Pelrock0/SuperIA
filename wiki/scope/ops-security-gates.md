# FEAT-OPS-SECURITY-GATES — CI Security Gates

**Complexity:** HIGH (infrastructure) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-OPS1 | Install vimeo/psalm + plugin-laravel, fix all 224 legacy findings | Implemented |
| HU-OPS2 | `composer security` wrapper script (composer audit) | Implemented |
| HU-OPS3 | GitHub Actions CI (.github/workflows/security.yml) | Implemented |
| HU-OPS4 | gitleaks documentation + CI integration | Implemented |
| HU-OPS5 | SecurityGatesIntegrationTest (subprocess test) | Implemented |

## Key Dependencies

- `vimeo/psalm` ^6.0
- `psalm/plugin-laravel` ^3.0 (triggered PHP 8.4→8.5.5 upgrade)
- gitleaks (external binary, CI-only)

## Design Decisions

- No baseline (option B: fix all 224 legacy findings upfront)
- Error level 5 (mid-strict)
- 3 narrowly-scoped suppressions: Backpack helpers, framework rebind, paginator generic leak
- `composer security` wrapper (not bare `composer audit`) for consistent output
- `--no-cache` flag added to script for CI reliability

## Deviations

- Psalm 6 chosen (design said ^5.26)
- plugin-laravel 3 (design said ^2.11)
- PHP 8.5.5 runtime (user-approved upgrade from 8.4.0)

## Legacy Fix Breakdown

| Category | Count |
|----------|-------|
| MissingOverrideAttribute | 87 (auto-fixed) |
| InvalidArgument (factories) | 75 (mass-replaced) |
| MissingTemplateParam | 20 (docblocks) |
| UndefinedDocblockClass | 9 (suppressions) |
| Other | 33 |

## Impact

Every future S5-SEC review gains automated gates. Codebase starts at zero psalm findings.

## Review Findings

- A08 LOW: GitHub Actions pinning by tag vs SHA hash (non-blocking)
- 479 backend + 208 frontend tests passing, zero regressions
