# Agents_dev
Agents repository for developers only

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

Full security review checklist lives in `.cursor/skills/security-review.md` (OWASP Top 10 2021, OWASP API Top 10 2023, OWASP LLM Top 10 v2 2025).
