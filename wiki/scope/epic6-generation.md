# FEAT-EPIC6-GENERATION — AI List Generation

**Complexity:** HIGH (16-24h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-601 | Generate list from free-text description (≤500 chars), Claude returns ≤25 items, editable preview | Implemented |
| HU-602 | Adjust quantities by people count (default 2), regenerate without re-describing | Implemented |

## Complexity Classification

- Prompt security: HIGH — widest injection surface in the project (500-char free text)
- Preview flow: MEDIUM — client-side React state, ephemeral (no server persistence)
- Quota management: MEDIUM — 3-layer check (budget + shared quota + per-operation 5/day)

## Key Dependencies

- Claude Sonnet (quality requirement; Haiku not sufficient)
- Epic 5A guardrails (PromptSanitizer extended to 500 chars)
- `ShoppingListService` for freemium check at confirm time
- Zero new tables or migrations

## Design Decisions

- Preview is client-side state only (no server persistence, no tamper risk)
- Freemium check deferred to confirm time (not before Claude call — add-to-existing doesn't create list)
- Silent retry: catches Claude exception, retries once silently; shows error only on second failure
- "Crear lista nueva" + "Añadir a existente" both use `SelectListModal` (reused from Epic 5B)
- `people` field: default 2, Claude infers commercial rounding

## Deviations

None — catch-order bug found and fixed during S4.

## Review Findings

- Prompt injection surface widest in project; PromptSanitizer covers 8 regex patterns + 500-char limit
- React JSX auto-escapes Claude output (no XSS risk)
- 794 tests passing (45 new: 29 backend + 16 frontend)
