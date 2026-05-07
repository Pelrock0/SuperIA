# Technical Design — FEAT-OPS-SECURITY-GATES

## Architecture

Three-gate security pipeline: static analysis (Psalm), dependency audit (Composer), secret scanning (gitleaks). All wired into GitHub Actions CI.

## Gate Definitions

```yaml
# .github/workflows/security.yml
jobs:
  psalm:
    - run: composer install
    - run: vendor/bin/psalm --no-cache

  composer-audit:
    - run: composer security   # wrapper in composer.json scripts

  gitleaks:
    - uses: gitleaks/gitleaks-action@v2
```

## Psalm Configuration (psalm.xml)

```xml
errorLevel="5"  <!-- mid-strict; catches real bugs without noise -->
<plugins>
  <pluginClass class="Psalm\LaravelPlugin\Plugin"/>
</plugins>
<issueHandlers>
  <!-- 3 suppressions: Backpack helpers, framework rebind, paginator generics -->
</issueHandlers>
```

## Legacy Fix Categories

| Category | Count | Method |
|----------|-------|--------|
| MissingOverrideAttribute | 87 | Auto-fixed (psalm --alter) |
| InvalidArgument (factories) | 75 | Mass-replaced factory patterns |
| MissingTemplateParam | 20 | Docblock additions |
| UndefinedDocblockClass | 9 | Suppressions (third-party) |
| Other | 33 | Case-by-case |

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| No baseline (fix all) | Baseline accumulates debt; zero findings is maintainable |
| errorLevel 5 (not 1) | Level 1 is too strict for framework magic; 5 catches real issues |
| gitleaks CI-only (not local hook) | Local hook installer is complex; CI is the enforcement point |
| `composer security` wrapper | Consistent output format; easier to parse in CI |

## Gotchas

- Psalm 6 requires PHP ≥8.5 (triggered upgrade from 8.4.0 → 8.5.5)
- `--no-cache` flag essential in CI (stale cache causes false negatives)
- GitHub Actions gitleaks action pins by tag (LOW risk; SHA pinning is stricter but not standard yet)
- SAML transitive dependency CVE discovered and removed during security audit (unrelated HIGH severity)
