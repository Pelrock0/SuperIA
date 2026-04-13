# Sofia4Builders Glossary

Quick reference for terms and concepts used in the Sofia4Builders workflow system.

---

## Workflow Steps

| Step | Name | Description |
|------|------|-------------|
| S1 | Scope Analysis | Initial analysis to determine feature complexity and path |
| S1b | Mini-Scope | Quick implementation path for LOW complexity features |
| S2 | PRD | Product Requirements Document creation |
| S3 | Technical Design | Architecture and implementation planning |
| S4 | Implementation | Code development (backend, frontend, or both) |
| S5-CODE | Code Review | Code quality and standards verification |
| S5-SEC | Security Review | Security vulnerability assessment |
| S5-TEST | Test Gate | Test coverage and execution verification |
| S5-UX | UI/UX Review | User interface and experience validation |
| S6 | Done | Feature completed and ready for deployment |

---

## Complexity Levels

| Level | Criteria | Workflow Path |
|-------|----------|---------------|
| LOW | Simple feature, minimal changes, no new patterns | S1 → S1b → S4 → S5 → S6 (skip S2, S3) |
| MEDIUM | Standard feature, some complexity | Full workflow (S1 → S2 → S3 → S4 → S5 → S6) |
| HIGH | Complex feature, new patterns, multiple systems | Full workflow with extra scrutiny |

---

## Implementation Types

| Type | Code | Description |
|------|------|-------------|
| Backend Only | S4-BACKEND | API, services, database changes only |
| Frontend Only | S4-FRONTEND | UI components, views, styles only |
| Full Stack | S4-BOTH | Both backend and frontend changes |

---

## Agents

| Agent | File | Role |
|-------|------|------|
| Scope Analyzer | `scope-analyzer.md` | Analyzes initial requirements and determines complexity |
| Product Owner | `product-owner.md` | Creates and refines PRD documents |
| Architect | `architect.md` | Designs technical solutions |
| Backend Developer | `backend-developer.md` | Implements server-side code |
| Frontend Developer | `frontend-developer.md` | Implements client-side code |
| Code Reviewer | `code-reviewer.md` | Reviews code quality |
| Security Reviewer | `security-reviewer.md` | Reviews security concerns |
| Test Gate | `test-gate.md` | Validates test coverage |
| UI/UX Reviewer | `ui-ux-reviewer.md` | Reviews user interface |

---

## Skills

| Skill | File | Purpose |
|-------|------|---------|
| Scope Analysis | `scope-analysis.md` | Techniques for analyzing scope |
| PRD Writing | `prd-writing.md` | Guidelines for requirement documents |
| Technical Design | `technical-design.md` | Architecture documentation standards |
| Implementation | `implementation.md` | Coding standards and practices |
| Frontend Implementation | `frontend-implementation.md` | UI development guidelines |
| Code Review | `code-review.md` | Review criteria and process |
| Security Review | `security-review.md` | Security checklist |
| Test Enforcement | `test-enforcement.md` | Testing requirements |
| UI/UX Review | `ui-ux-review.md` | UX validation checklist |

---

## Artifacts

| Artifact | Pattern | Purpose |
|----------|---------|---------|
| Scope Analysis | `docs/features/{FEATURE-ID}/01-scope.md` | Initial analysis results |
| PRD | `docs/features/{FEATURE-ID}/02-prd.md` | Product requirements |
| Technical Design | `docs/features/{FEATURE-ID}/03-technical-design.md` | Architecture plan |
| Implementation Notes | `docs/features/{FEATURE-ID}/04-implementation-notes.md` | Development log |
| Review Results | `docs/features/{FEATURE-ID}/05-review-results.md` | Review findings |
| UX Wireframes | `docs/features/{FEATURE-ID}/ux-wireframes.html` | Visual mockups |

---

## CLI Commands

| Command | Usage | Description |
|---------|-------|-------------|
| `create` | `cli.py create FEAT-001` | Create new feature |
| `status` | `cli.py status FEAT-001` | Show feature state |
| `prepare` | `cli.py prepare FEAT-001` | Generate context for current step |
| `approve` | `cli.py approve FEAT-001` | Approve current step, move to next |
| `review-failed` | `cli.py review-failed FEAT-001 "reason"` | Mark review failed, return to S4 |
| `scope-change` | `cli.py scope-change FEAT-001 minor` | Request scope change |
| `escalate` | `cli.py escalate FEAT-001` | Escalate LOW to full workflow |
| `set-ui` | `cli.py set-ui FEAT-001 yes` | Set UI change flag |
| `detect-ui` | `cli.py detect-ui FEAT-001` | Analyze UI changes |
| `detect-stack` | `cli.py detect-stack` | Show detected tech stack |
| `init-stack` | `cli.py init-stack` | Create stack.yaml config |
| `metrics` | `cli.py metrics [FEAT-001]` | Show metrics and statistics |

---

## Supported Stacks

| Backend | Frontend | Database |
|---------|----------|----------|
| Laravel | Blade | MySQL |
| Node/Express | React | PostgreSQL |
| Django | Vue | SQLite |
| FastAPI | Django Templates | MongoDB |
| Go/Fiber | HTMX | Redis |
| Rust/Actix | None | - |

---

## Key Concepts

### Gate
A checkpoint that must pass before proceeding to the next step. Gates ensure quality and completeness.

### UI Change Detection
Automatic detection of whether a feature includes user interface changes, used to determine if S5-UX review is required.

### Scope Creep
When implementation requirements expand beyond the original scope. Handled by `scope-change` command.

### Path Coverage
Test coverage strategy requiring tests for:
- **Happy Path**: Success scenarios
- **Failure Path**: Expected error cases
- **Edge Cases**: Boundary conditions
- **Security Path**: Authentication, authorization, injection

### Transaction-Based Testing
Testing strategy using database transactions with automatic rollback for test isolation. No SQLite for tests.

---

## Feature ID Format

`FEAT-YYYY-NNN`

Examples:
- `FEAT-2024-001`
- `FEAT-2025-042`

---

## Output Modes

| Mode | Flag | Target |
|------|------|--------|
| Cursor | `--mode=cursor` | Cursor IDE with @ syntax |
| Claude Code | `--mode=claude` | Claude Code CLI with explicit files |

---

## Abbreviations

| Abbr | Full Form |
|------|-----------|
| PRD | Product Requirements Document |
| UX | User Experience |
| UI | User Interface |
| S1-S6 | Steps 1 through 6 |
| CLI | Command Line Interface |
| API | Application Programming Interface |
| CRUD | Create, Read, Update, Delete |
