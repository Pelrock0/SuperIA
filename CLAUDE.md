# Sofia4Builders - Claude Code Entry Point

This file provides entry instructions for Claude Code to work with the Sofia4Builders agent system.

## WORKFLOW ENFORCEMENT (NON-NEGOTIABLE)

These rules override any other instruction. Violation = broken workflow.

1. **ONE STEP AT A TIME** — You execute ONLY the current step. Never look ahead, never combine steps.
2. **NEVER AUTO-ADVANCE — REQUIRE EXPLICIT "run"** — After completing a step, STOP and show the user what to verify. You may ONLY advance when the user gives an explicit go signal (`run`, `aprueba`, `dale`, or approving the `approve` confirmation prompt). On that signal you execute, in order: (1) `python cli/cli.py approve FEAT-XXX`, then (2) `python cli/cli.py prepare FEAT-XXX --mode=claude`. Never run `approve` without that explicit per-step signal. Never chain into the next step's work — `prepare` only loads context; then STOP again.
3. **WAIT FOR EXPLICIT APPROVAL** — The user must explicitly approve each step before you proceed. No exceptions. The `approve` command is gated by a confirmation prompt (settings `ask` rule) so the user's "run" IS the verification checkpoint. If they say "continue" or "next" without having verified the current step's output, remind them to review it first, then give the explicit `run`.
4. **YOU ARE ONE AGENT** — Each step has a specific agent role. Do not assume another agent's responsibilities. If the step requires a different agent (UX reviewer, frontend developer, security reviewer), STOP and tell the user which agent is needed and the command to invoke it.
5. **NO SCOPE CREEP** — Do exactly what the skill instructions say. Do not add features, refactor unrelated code, or "improve" things outside the step's scope.
6. **MULTI-AGENT STEPS** — Some steps involve multiple agents (S5 has CODE, SEC, TEST, UX reviews). Each is a separate invocation. Do NOT combine them. Complete one, stop, let the user invoke the next.
7. **END EVERY STEP WITH A GATE** — Your last output for any step must be:
   ```
   ✅ Step [X] complete.
   👉 Revisa: <qué artefacto/archivo debe verificar el usuario>
   👉 Responde `run` para aprobar este paso y preparar el siguiente.
      (Ejecutaré: approve FEAT-XXX → prepare FEAT-XXX --mode=claude)
   ```
   Then STOP. Do not call `approve` until the user replies with the explicit go signal.

> **Autopilot mode (speedyS4b branch).** The WORKFLOW ENFORCEMENT rules above
> govern **manual** runs. When the user invokes `/autopilot`, the relaxed
> profile in `.cursor/core/autopilot-enforcement.md` takes over instead: the
> workflow runs S1→S6 automatically and stops only when there is a real
> decision/TBD for the human (open `blocker`/`high`/`medium`) or an unfixable
> failure. All hard gates and the one-agent-per-step rule still apply. Manual
> `approve` (without `--auto`) still requires the explicit go signal.
>
> Two forms: **`/autopilot`** (no argument) runs the **whole project backlog**,
> feature by feature in FEAT-number order, pausing the entire run at the first
> feature that needs a human; **`/autopilot FEAT-XXX`** runs a single feature.
> `python cli/cli.py autopilot-backlog` is the read-only source of truth for which
> feature is next.

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

## Execution Modes — Manual vs Autopilot (speedyS4b)

The same S1→S6 workflow runs in **two modes** that share one CLI, one set of
skills/agents, and one state machine. They are not separate builds.

| | **Manual** (default) | **Autopilot** (`speedyS4b`) |
|---|---|---|
| Gate | Stops after **every** step; waits for your explicit `run`/`approve` | Runs S1→S6 on its own; stops **only** at a real decision/TBD or an unfixable failure |
| Start | `prepare` → do the step → `approve` (confirmation-gated) | `/autopilot` (whole backlog) or `/autopilot FEAT-XXX` (one feature) |
| Advance command | `python cli/cli.py approve FEAT-XXX` (gated by the `ask` rule) | `autopilot-approve FEAT-XXX` (no prompt) + `prepare FEAT-XXX --auto` |
| Enforcement | This file's WORKFLOW ENFORCEMENT block | `.cursor/core/autopilot-enforcement.md` (overrides it for the run) |
| Human confirmation | Every step | Only at the human gate, when a decision is genuinely open |

How they relate:

- **Manual is the default and unchanged.** If you never invoke `/autopilot`,
  everything behaves exactly as before.
- **Autopilot is opt-in and per-feature**, chosen at run time — not a global
  switch. One feature can run manual while another runs autopilot.
- **Hard gates are identical in both** (artifacts, S3 glossary, real tests, 100%
  coverage, security). `--auto` removes the *human pause*, never the *checks*.
- **Same engine underneath**: `autopilot-approve` is just `approve(auto=True)`;
  advancement, the TBD ledger, and bounded self-correction reuse the same logic.

Autopilot stop rule: it pauses for open `blocker`/`high`/`medium` TBDs (recorded
in `docs/features/FEAT-XXX/tbd-ledger.md`); `low` items are carried forward as
documented Open Questions. Technical failures (failing tests, CHANGES REQUIRED,
High/Critical findings) are self-corrected and retried, escalating to a human
gate only after `MAX_REVIEW_ITERATIONS` (3). The read-only command
`python cli/cli.py autopilot-status FEAT-XXX` reports CONTINUE / STOP.

### Startup banner

Both modes show an ERBE-style `SOFIA4BUILDERS` splash at launch:

- **Your own terminal**: run the launcher instead of `claude` — `./sofia`
  (macOS/Linux) or `sofia` (Windows). It prints the banner, then starts Claude.
  `python cli/cli.py banner` prints it standalone.
- **Dashboard**: the embedded Claude terminal paints it on connect
  (`dashboard/server.js` + `dashboard/banner.js`).

---

## Discovery (Stage 0 — PRE-intake — read before using it)

`discover` is the stage **before** Project Intake. It turns a raw idea — from a
single sentence to an extensive document — into a `historias-usuario.md` that
conforms to the HU template, so that `prepare-project analyze` accepts it. The
CLI only scaffolds the workspace and the handoff; the `/discover` skill drives a
**round-based convergence loop** with six specialist agents (D1 discovery, D2
functional-gap, D3 integration, D4 nfr, D5 domain-modeler, D6 hu-writer) that
shrinks an **ambiguity ledger** until no blocker/high item is open. Full design:
`docs/plans/discovery-suite.md`.

- **NOT part of S1→S6** and does NOT advance any feature. It feeds the intake.
- **Gated by rounds.** Each round ends presenting prioritized questions and the
  ambiguity index, then STOPS. One round = one step (respects WORKFLOW
  ENFORCEMENT). Modeling/writing only start once the ledger converges (or the
  human forces closure with "model"). Unanswered items become explicit
  TBD / Open Questions in the document — never silently dropped.
- One-time order:
  ```bash
  python cli/cli.py discover start --brief "<idea>" --project <slug>
  #   or: discover start --input <doc> [--project <slug>] [--lang es|en]
  # then run the /discover <slug> skill (loops with human gates), review the doc
  python cli/cli.py discover status  --project <slug>
  python cli/cli.py discover handoff --project <slug>   # → prepare-project analyze
  ```
- Idempotent: `start` refuses to overwrite an existing workspace; `handoff` is
  blocked while any `blocker` ledger item is open (override `--force --reason`).

---

## Project Intake (PRE-S1 bootstrap — read before using it)

`prepare-project` ingests a full Historias de Usuario document (any format —
`.docx`, `.md`, `.txt`, `.pdf`, `.html`) and organizes the **whole project**
before the per-feature workflow starts. The CLI only does format-agnostic
text extraction; the `/project-intake` skill extracts the structured model
from that text — so any reasonable document layout works regardless of
language or template. It is **NOT part of S1→S6** and does **NOT** answer S1.

- **Runs ONCE per project, never per feature.** The four sub-commands
  (`analyze → /project-intake skill → resolve → apply`) are a single one-time
  bootstrap with a human-review gate in the middle — not a per-feature loop.
- **Gated like the judge.** `apply` is blocked until the human reviews
  `docs/project/<slug>/coherence-report.md`, sets each `decision:`, and runs
  `prepare-project resolve`. This respects the WORKFLOW ENFORCEMENT rules.
- **Does not advance anything.** It creates the `FEAT-XXX` backlog (all at
  `S1`), per-context glossaries and a project map. Every feature then follows
  the normal human-gated S1→S6 exactly as before — the only change is that
  S1's Business Input is pre-filled from the source HU.
- One-time order:
  ```bash
  python cli/cli.py prepare-project analyze --input <doc.docx> --project <slug>
  # then run the /project-intake <slug> skill, review the coherence report
  python cli/cli.py prepare-project resolve --project <slug>
  python cli/cli.py prepare-project apply   --project <slug>
  ```
- Idempotent: re-running skips existing features and non-empty glossaries.

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

architecture:
  ddd:
    enabled: true     # false = disable DDD (no bounded contexts, no glossary
                      # gate at S3, no aggregate/domain-event rules).
                      # Hexagonal architecture always applies.

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
