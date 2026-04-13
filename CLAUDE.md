# Sofia4Builders - Claude Code Entry Point

This file provides entry instructions for Claude Code to work with the Sofia4Builders agent system.

## WORKFLOW ENFORCEMENT (NON-NEGOTIABLE)

These rules override any other instruction. Violation = broken workflow.

1. **ONE STEP AT A TIME** — You execute ONLY the current step. Never look ahead, never combine steps.
2. **NEVER AUTO-ADVANCE** — After completing a step, STOP. Tell the user to run `python cli/cli.py approve FEAT-XXX` and then `python cli/cli.py prepare FEAT-XXX --mode=claude`. Do NOT run approve yourself.
3. **WAIT FOR EXPLICIT APPROVAL** — The user must approve each step before you proceed. No exceptions. If they say "continue" or "next", remind them to run `approve` first.
4. **YOU ARE ONE AGENT** — Each step has a specific agent role. Do not assume another agent's responsibilities. If the step requires a different agent (UX reviewer, frontend developer, security reviewer), STOP and tell the user which agent is needed and the command to invoke it.
5. **NO SCOPE CREEP** — Do exactly what the skill instructions say. Do not add features, refactor unrelated code, or "improve" things outside the step's scope.
6. **MULTI-AGENT STEPS** — Some steps involve multiple agents (S5 has CODE, SEC, TEST, UX reviews). Each is a separate invocation. Do NOT combine them. Complete one, stop, let the user invoke the next.
7. **END EVERY STEP WITH A GATE** — Your last output for any step must be:
   ```
   ✅ Step [X] complete.
   👉 Next: Run `python cli/cli.py approve FEAT-XXX` to approve, then `python cli/cli.py prepare FEAT-XXX --mode=claude` for the next step.
   ```

---

## Quick Start

1. **Read the context files**:
   - `.cursor/core/core.md` - Core rules for all agents
   - `.cursor/config/stack.yaml` - Project stack configuration (if exists)

2. **Run CLI commands**:
   ```bash
   python cli/cli.py detect-stack           # See current stack
   python cli/cli.py prepare FEAT-001 --mode=claude  # Get context for a feature
   ```

3. **Follow the workflow**: S1 → S2 → S3 → S4 → S5 → S6

---

## Project Structure

```
.cursor/
├── agents/           # Agent role definitions
├── skills/           # Skill instructions per step
├── core/
│   └── core.md       # Core rules (READ THIS FIRST)
└── config/
    ├── stack.yaml    # Project stack config
    └── stacks/       # Stack templates

cli/
├── cli.py            # Workflow CLI
└── state/            # Feature state files

docs/                 # Feature documentation output
```

---

## Workflow Steps

| Step | Name | Agent | Skill |
|------|------|-------|-------|
| S1 | Scope Analysis | scope-analyzer | scope-analysis |
| S1b | Quick Scope (LOW) | scope-analyzer | scope-analysis |
| S2 | PRD Writing | product-owner | prd-writing |
| S3 | Technical Design | architect | technical-design |
| S4 | Implementation | backend/frontend-developer | implementation |
| S5-CODE | Code Review | code-reviewer | code-review |
| S5-SEC | Security Review | security-reviewer | security-review |
| S5-TEST | Test Gate | test-gate | test-enforcement |
| S5-UX | UX Review | ui-ux-reviewer | ui-ux-review |
| S6 | Completion | - | - |

---

## CLI Commands

```bash
# Feature management
python cli/cli.py create FEAT-001          # Create new feature
python cli/cli.py status FEAT-001          # Check feature status
python cli/cli.py approve FEAT-001         # Approve current step
python cli/cli.py prepare FEAT-001 --mode=claude  # Get context

# Stack management
python cli/cli.py detect-stack             # Auto-detect stack
python cli/cli.py init-stack               # Create stack.yaml
```

---

## Working with Features

### 1. Start a Feature

```bash
python cli/cli.py create FEAT-001
python cli/cli.py prepare FEAT-001 --mode=claude
```

### 2. Read Required Context

When working on a feature, always read:
- `.cursor/core/core.md`
- `.cursor/config/stack.yaml` (if exists)
- The agent file for current step
- The skill file for current step
- Previous step outputs in `docs/features/FEAT-001/`

### 3. Execute the Step

Follow the skill instructions exactly. Do not assume. Do not add scope.

### 4. Move to Next Step

```bash
python cli/cli.py approve FEAT-001
python cli/cli.py prepare FEAT-001 --mode=claude
```

---

## Stack Configuration

The system supports multiple technology stacks. Configuration is in `.cursor/config/stack.yaml`:

```yaml
stack:
  backend: laravel    # laravel | node | django | fastapi | go | rust
  frontend: react     # react | vue | blade | django-templates | none
  database: postgresql

testing:
  database:
    use_transactions: true
    use_real_db: true
  coverage:
    minimum: 100
```

### Auto-Detection

If no `stack.yaml` exists, the CLI auto-detects based on:
- `artisan` / `composer.json` → Laravel
- `package.json` / `tsconfig.json` → Node
- `manage.py` / `wsgi.py` → Django
- `Cargo.toml` → Rust
- `go.mod` → Go

---

## Testing Rules (NON-NEGOTIABLE)

1. **100% coverage required** - No exceptions
2. **Use transactions** - Every test must rollback
3. **Use real database** - No SQLite for tests
4. **Cover all paths**:
   - Happy path (success)
   - Failure path (errors)
   - Edge cases (boundaries)
   - Security path (auth bypass, injection)

---

## Key Differences from Cursor

| Aspect | Cursor | Claude Code |
|--------|--------|-------------|
| File references | `@file` syntax | Explicit file reads |
| Context loading | Automatic | Manual via CLI |
| Visual validation | `@browser` | N/A |
| Mode flag | Default | `--mode=claude` |

---

## Example Session

```bash
# 1. Create feature
python cli/cli.py create FEAT-AUTH-LOGIN

# 2. Get context for S1
python cli/cli.py prepare FEAT-AUTH-LOGIN --mode=claude

# 3. Read the context files
# (Claude Code reads .cursor/core/core.md and agent/skill files)

# 4. Execute S1: Scope Analysis
# (Output to docs/features/FEAT-AUTH-LOGIN/scope.md)

# 5. Approve and continue
python cli/cli.py approve FEAT-AUTH-LOGIN
python cli/cli.py prepare FEAT-AUTH-LOGIN --mode=claude
# ... continue through workflow
```

---

## Important Notes

- **Never assume** - Ask if requirements are unclear
- **Follow the stack** - Use stack-specific patterns from core.md
- **Test everything** - 100% coverage is mandatory
- **No shortcuts** - Every rule is NON-NEGOTIABLE

---

## Security Reviews (S5-SEC)

Before running an S5-SEC review, execute `composer security` to run the automated gates (`composer audit` + `psalm --taint-analysis`). The full skill at `.cursor/skills/security-review.md` is the source of truth for the manual review checklist (OWASP Top 10 2021, OWASP API Top 10 2023, OWASP LLM Top 10 v2 2025). Both the wrapper and the skill are mandatory.
