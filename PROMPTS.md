# Sofia4Builders - Prompt Templates for Claude Code

Copy-paste these prompts to start working with Sofia4Builders in Claude Code.

---

## New Feature (from scratch)

```
Lee CLAUDE.md y .cursor/core/core.md.
Ejecuta: python cli/cli.py create FEAT-XXX
Luego: python cli/cli.py prepare FEAT-XXX --mode=claude
Sigue las instrucciones del paso resultante.
```

Replace `FEAT-XXX` with your feature ID (e.g., `FEAT-AUTH-LOGIN`).

---

## New Feature (with description)

```
Lee CLAUDE.md y .cursor/core/core.md.
Crea feature FEAT-XXX con descripción: "<tu descripción aquí>".
Ejecuta: python cli/cli.py create FEAT-XXX
Luego: python cli/cli.py prepare FEAT-XXX --mode=claude
Sigue las instrucciones del paso resultante.
```

---

## Continue Existing Feature

```
Lee CLAUDE.md. Ejecuta: python cli/cli.py prepare FEAT-XXX --mode=claude
Sigue las instrucciones del paso resultante.
```

---

## Check Feature Status

```
Lee CLAUDE.md. Ejecuta: python cli/cli.py status FEAT-XXX
```

---

## After Approval (advance to next step)

```
Ejecuta: python cli/cli.py prepare FEAT-XXX --mode=claude
Sigue las instrucciones del paso resultante.
```

---

## Tips

- **Always start with "Lee CLAUDE.md"** — without it, Claude Code doesn't know the workflow exists.
- **One step at a time** — don't ask Claude to do multiple steps.
- **Approve between steps** — run `python cli/cli.py approve FEAT-XXX` yourself before asking for the next step.
- **S5 has 4 sub-reviews** — each is a separate invocation (CODE, SEC, TEST, UX).

## MANDATORY
- Mandatory, siempre pregunta antes de ejecutar cualquier comando de cli.py
