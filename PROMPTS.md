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

## Autopilot — run S1→S6 autonomously (speedyS4b)

Runs the whole workflow without stopping at every step; pauses only when there is
a real decision/TBD or an unfixable failure.

```
Lee CLAUDE.md y .cursor/core/autopilot-enforcement.md.
Ejecuta: python cli/cli.py prepare FEAT-XXX --auto --mode=claude
Luego lanza el skill: /autopilot FEAT-XXX
```

- Stops only at open blocker/high/medium TBDs (shown as a plain-language gate) or
  after exhausting self-correction. Answer in chat, then re-invoke `/autopilot FEAT-XXX`.
- Manual mode is unchanged: omit `/autopilot` and approve each step yourself.

---

## Launch with the banner

Run the launcher instead of `claude` from the project root:

```
./sofia        # macOS/Linux
sofia          # Windows
```

Prints the SOFIA4BUILDERS splash, then starts Claude (args pass through, e.g. `./sofia --resume`).

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
