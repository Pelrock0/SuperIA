# Implementation Notes: FEAT-OPS-SECURITY-GATES

## Summary

Installed `vimeo/psalm ^6.0` + `psalm/plugin-laravel ^3.0` as dev dependencies, created `psalm.xml` (errorLevel 5, Laravel plugin, taint analysis ready), added `composer security` script (`composer audit` + `psalm --taint-analysis`), created `.github/workflows/security.yml` (three parallel jobs: composer-audit, psalm-taint, gitleaks), added `SecurityGatesIntegrationTest` invoking `composer security` via Symfony Process, fixed all 224 pre-existing psalm findings in the legacy codebase to reach the zero-findings baseline, and wired documentation into README.md, CLAUDE.md, and `.cursor/skills/security-review.md`.

PHP runtime upgraded from 8.4.0 to 8.5.5 as a prerequisite — psalm 6 requires PHP ≥ 8.4.3 and plugin-laravel 3 requires Laravel 12 compatibility, both satisfied by 8.5.5.

## Psalm Exploration (AC-13 checkpoint)

| Measurement | Count |
|-------------|-------|
| Initial errors (post-install) | **224** |
| After `psalm --alter` auto-fix (`MissingOverrideAttribute`) | 137 |
| After factory `create()` → `createOne()` mass replacement + Admin suppressions | 61 |
| After manual bucket cleanup (10 issue types) | **0** |

224 exceeded the AC-13 "proceed" threshold (< 50) and the "batched" threshold (50-200), putting it in the "**> 200 → STOP and renegotiate**" bracket. User was consulted and chose to proceed with full cleanup (option B, no baseline). All findings were resolved inside this feature's scope.

## Legacy fix breakdown

| Issue | Count | Fix strategy |
|-------|-------|-------------|
| `MissingOverrideAttribute` | 87 | `psalm --alter --issues=MissingOverrideAttribute` auto-fix |
| `InvalidArgument` (factory `create()` ambiguity) | 75 | Mass replace `factory()->create(` → `factory()->createOne(` in `tests/` (sed + perl passes) |
| `MissingTemplateParam` | 20 | Added `@extends Factory<Model>` to 10 factories + `@use HasFactory<Factory>` to 10 models |
| `UndefinedDocblockClass` (paginator `TValue` leak) | 9 | Added `@var list<\stdClass>` + `@psalm-suppress UndefinedDocblockClass` on `$paginator->items()` call sites |
| `UndefinedThisPropertyFetch` | 8 | Added `@property` docblocks to Eloquent models (`ListShareToken`, `ShoppingList`, `User`, `WaitlistEntry`) |
| `UndefinedInterfaceMethod` (Auth, json_decode array) | 7 | `/** @var \App\Models\User $user */` narrow in `ProfileController`; `@var list<array<string, mixed>>` narrow on `json_decode` result in test |
| `InvalidTemplateParam` (Eloquent `Collection->map()` → non-Model) | 5 | Added `->toBase()` before `->map()` to drop to `Illuminate\Support\Collection` |
| `UndefinedFunction` (Backpack helpers) | 4 | Extended psalm.xml UndefinedFunction suppression to `CheckIfAdmin` middleware + `UserRequest` |
| `InvalidPropertyAssignmentValue` (FakeClaudeClient strict types) | 3 | Broadened `FakeClaudeClient` canned-array types to `array<string, mixed>` |
| `LessSpecificImplementedReturnType` | 2 | Added `array<string, string>` to `UserRequest::attributes()` / `messages()` docblocks |
| `InvalidArgument` (SeedProductCatalog $outputPath) | 2 | Cast `$this->option('output')` to `(string)` |
| `InvalidScope` (Artisan::command closure `$this`) | 1 | Inline `@psalm-suppress InvalidScope` |

**Test regression check**: backend suite ran after each major batch (post-auto-fix: 478/478, post-createOne mass replace: 478/478, post-bucket cleanup: 479/479 including the new integration test). Frontend suite unaffected (208/208).

## Files Changed

| File | Change Type | Description |
|------|-------------|-------------|
| `composer.json` | Modified | Added `vimeo/psalm ^6.0`, `psalm/plugin-laravel ^3.0` to require-dev; added `security` script |
| `composer.lock` | Modified | Lockfile for new deps |
| `psalm.xml` | Created | errorLevel 5, Laravel plugin, UndefinedFunction suppressions for Backpack glue |
| `.github/workflows/security.yml` | Created | 3-job workflow: composer-audit, psalm-taint, gitleaks |
| `tests/Feature/SecurityGatesIntegrationTest.php` | Created | Symfony Process subprocess asserting `composer security` exits 0 |
| `.gitignore` | Modified | Added `/storage/framework/psalm` |
| `config/database.php` | Modified | Replaced deprecated `PDO::MYSQL_ATTR_SSL_CA` with `Pdo\Mysql::ATTR_SSL_CA` (PHP 8.5 compat) |
| `README.md` | Modified | Added Security Gates section |
| `CLAUDE.md` | Modified | Added S5-SEC wrapper reference |
| `.cursor/skills/security-review.md` | Modified | Added wrapper row + note to §1 Automated Gates |
| `routes/console.php` | Modified | `@psalm-suppress InvalidScope` on Artisan closure |
| `app/Support/Ai/FakeClaudeClient.php` | Modified | Broadened canned array types |
| `app/Models/*` (10 files) | Modified | `@use HasFactory<Factory>` docblocks + `@property` docblocks |
| `database/factories/*` (10 files) | Modified | `@extends Factory<Model>` docblocks |
| `app/Http/Controllers/Auth/ProfileController.php` | Modified | User type narrowing, paginator items docblock |
| `app/Http/Controllers/ShareTokenController.php` | Modified | `->toBase()` before `->map()` |
| `app/Http/Controllers/ShoppingListController.php` | Modified | `->toBase()` before `->map()` |
| `app/Services/ProductSuggestionService.php` | Modified | `->toBase()` before `->map()` |
| `app/Http/Requests/UserRequest.php` | Modified | `array<string, string>` return types |
| `app/Console/Commands/SeedProductCatalog.php` | Modified | Cast `$outputPath` to string |
| `tests/Feature/SeedProductCatalogCommandTest.php` | Modified | `@var list<array<string, mixed>>` on json_decode result |
| `tests/Unit/Services/ProductHistoryWeightingServiceTest.php` | Modified | `@var list<\stdClass>` on paginator items |
| ~70 test files | Modified | `#[\Override]` added by psalm auto-fix; `factory()->create()` → `factory()->createOne()` |

## Migrations

None — this feature touches zero database schema.

## Tests Added

| Test File | Type | What it tests |
|-----------|------|---------------|
| `tests/Feature/SecurityGatesIntegrationTest.php` | Feature/Integration | `composer security` subprocess exits 0 (marks skipped if composer binary absent from test environment) |

## Test Coverage Report

| Component | Status |
|-----------|--------|
| `composer security` wrapper | Covered by `SecurityGatesIntegrationTest::test_composer_security_exits_zero` |
| Backend suite (pre-existing 478 + new 1) | **479/479 passing** (911 assertions) |
| Frontend suite | **208/208 passing** (unaffected) |
| Psalm (taint analysis on) | **0 errors** across `app/`, `database/`, `routes/`, `tests/` |
| Composer audit | **0 advisories** |

## Notes for Reviewers

- **Composer deprecation noise**: `composer` itself emits PHP 8.5 deprecation warnings (`curl_close`, `react/promise` case-semicolons, etc.) during `composer security` on the local Windows/WAMP setup. These are upstream Composer phar issues, not our code, and do not affect exit codes. CI ubuntu-latest runners with PHP 8.5 via `shivammathur/setup-php` may or may not reproduce — if the workflow fails on deprecation output with a strict error handler, bump `COMPOSER_DISABLE_XDEBUG_WARN` or revisit. Tested locally with PHP 8.5.5 + xdebug 3.5.1 and exit code was 0.

- **Integration test duration**: `test_composer_security_exits_zero` takes ~40-45s because psalm runs without cache inside the subprocess (`--no-cache` in the script). If the test suite runtime becomes painful, consider dropping `--no-cache` from the script and letting the test use cached analysis, at the cost of slightly less certainty that the test exercises a full scan.

- **Docblock-only annotations**: Most model `@property` docblocks are minimum-viable (just the fields psalm complained about). Full Eloquent property introspection is out of scope — that's what IDE helper files are for (`barryvdh/laravel-ide-helper`), which could be a future enhancement.

- **Suppressions added**:
  - `psalm.xml`: `UndefinedFunction` suppressed in `app/Http/Controllers/Admin/`, `app/Http/Middleware/CheckIfAdmin.php`, `app/Http/Requests/UserRequest.php` — all Backpack-glue files using `backpack_url()`, `backpack_auth()`, `backpack_user()` which are globally-registered helpers psalm can't scan.
  - `routes/console.php`: `@psalm-suppress InvalidScope` on Laravel's `Artisan::command` closure `$this` — framework rebinds it at runtime, psalm can't see.
  - `app/Http/Controllers/Auth/ProfileController.php`: `@psalm-suppress UndefinedDocblockClass` on paginator `items()` call — `Illuminate\Contracts\Pagination\TValue` leaks from a generic template psalm can't resolve in this context.
  - None of these suppressions hide real security or correctness issues. Each is scoped as narrowly as possible.

- **Branch protection (AC-12)**: The workflow file is in place; enabling required-checks on `main` is a GitHub admin UI task documented in README and out of scope for this feature.

## Deviations from Design

- **Psalm version**: design said `^5.26`, installed `^6.0` (latest stable, requires PHP ≥ 8.4.3). Triggered PHP upgrade from 8.4.0 → 8.5.5. User approved the upgrade mid-implementation.
- **`psalm/plugin-laravel` version**: design said `^2.11`, installed `^3.0` (matches psalm 6 major version).
- **CI `php-version`**: design said `'8.4'`, workflow uses `'8.5'` to match the runtime the project is now built on.
- **Script `psalm` flags**: added `--no-cache --no-progress` to the `composer security` script for deterministic CI output. Not in the design but a sensible default.
- **Integration test timeout**: design said 120s, implemented 180s to give cold-cache psalm + audit enough headroom on slower CI runners.

## Known Issues / Technical Debt

- **Composer's own PHP 8.5 deprecation noise** on local Windows runs — not our code, tracked upstream.
- **1576 psalm "info" findings** remain (not errors, not blocking the gate). Mostly `ClassMustBeFinal`, `MissingClosureParamType`, `MixedReturnStatement` across legacy code. Candidates for a future `psalm-info-cleanup` feature but explicitly out of scope per errorLevel 5.
- **`psalm --taint-analysis`** found zero taint sources on the current codebase; this is a negative-proof result. First real taint finding will land during S5-SEC of a future feature that accepts user input and writes it to a sensitive sink.
- **Model `@property` docblocks** are minimum-viable. Adding a full-class `@property` block via IDE helper is future work.
