# Scope Analysis: FEAT-EPIC6-GENERATION

## Feature Request

Implement HU-601 (Generar lista automática por contexto) and HU-602 (Ajustar lista generada por número de personas) from `docs/Superia_HU_v3.md` § Épica 6.

**HU-601**: The user describes what they need in natural language ("Cena de cumpleaños para 8 personas", "Semana de dieta mediterránea") and Claude generates a full shopping list with name, quantity, unit, and category per item. The user previews, edits (remove items, change quantities), and confirms to create a new list or add to an existing one. Rate limit: 5 generations/day on Free plan.

**HU-602**: Optional "number of people" field (default 2). Claude adjusts quantities proportionally. User can change the number and regenerate quantities without re-describing the context. Quantities rounded to commercial units.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 16–24 hours |
| Confidence | Medium |

## Justification

**HIGH** because:

- **Multi-step UI flow**: prompt input → Claude call → preview with editable items → confirm (create list or add to existing). This is the most complex frontend interaction in the project so far.
- **New Claude API integration**: 4th integration in the project (after autocomplete, complements, weekly summary). New method with a different prompt shape (free-text description → structured JSON list). New system prompt.
- **Ephemeral state management**: the preview must persist between the Claude response and the user's confirm action. The user can edit items, change quantities, remove products — this state lives somewhere (client or server) and must survive the edit phase.
- **HU-602 "regenerate without re-describing"**: implies the system remembers the original prompt context. If client-side, the prompt travels to the frontend. If server-side, needs persistence (table or cache).
- **Separate rate limit**: the HU specifies 5 generations/day (vs 20 suggestions/day). The existing `AiUsageTracker` uses a shared quota — decision needed on whether to separate or stay shared.
- **Performance target**: <10 seconds end-to-end including Claude latency, JSON parsing, and rendering.
- **Two HUs in one feature**: HU-601 + HU-602 are tightly coupled (the "number of people" is part of the generation flow, not a separate feature).
- **Stitch design dependency**: "Generar Lista con IA" screen exists in Stitch, must be fetched via MCP before frontend work.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Medium | Claude API integration is the 4th in the project — patterns are established. The new risk is free-text prompt (user can write anything, not just a product name). `PromptSanitizer` caps at 200 chars which may be too short for rich descriptions. |
| Data | Low | No new tables strictly required if preview is client-side. If server-side preview, one new table (`generated_previews` or similar) with TTL. Either way, the confirmed list creation uses existing `ShoppingList` + `ListItem` models. |
| Security | **High** | Free-text user input → Claude prompt is a wider attack surface than single product names. The existing `PromptSanitizer` must be evaluated for adequacy on longer, richer inputs. Prompt injection risk is elevated because the user is authoring the ENTIRE user message (not just a search query). Additionally, Claude's response directly becomes list items — any injected content in the response flows into the user's data. |
| Performance | Medium | The <10s target depends on Claude API latency (~1-3s typical for Haiku). If the user's description is complex and the response is large (20+ items), parsing + rendering adds. Retry-on-invalid-JSON doubles the worst case. Streaming could help perceived performance but adds complexity. |
| Operational | Low | Rate limit of 5/day is tighter than autocomplete (20/day). The AiUsageTracker already handles per-operation quotas. If the quota is shared, heavy autocomplete users get fewer generations. If separate, the tracker needs modification. |

## Affected Areas

### Backend
- `app/Support/Ai/ClaudeClientInterface.php` — new method `generateListFromContext`
- `app/Support/Ai/ClaudeClient.php` — new system prompt + implementation
- `app/Support/Ai/FakeClaudeClient.php` — new canned response + call tracking
- `app/Services/ListGenerationService.php` (NEW) — orchestrate prompt → Claude → preview → confirm
- `app/Http/Controllers/ListGenerationController.php` (NEW) — endpoints for generate + adjust + confirm
- `app/Http/Requests/GenerateListRequest.php` (NEW) — FormRequest for the prompt + people count
- `routes/api.php` — new routes
- `config/ai.php` — new section for generation (model, max_tokens, rate limit, max prompt length)
- `app/Enums/AiOperation.php` — `AiOperation::Generation` already exists (verified in S3 of FEAT-EPIC5C-SUMMARY)

### Frontend
- `resources/js/pages/AIGeneratePage.jsx` (NEW) — full page at `/app/generar`
- `resources/js/lib/listGenerationApi.js` (NEW) — API client
- `resources/js/app.jsx` — new route
- `resources/js/pages/DashboardPage.jsx` — entry point (button/link to `/app/generar`)
- Stitch screen: "Generar Lista con IA" (screen `942e92206ffe48c1b0462ac132e924a6`)

### Tests
- Service tests (generate, adjust, confirm, rate limits, Claude error, retry)
- Controller/endpoint tests (happy, auth, validation, freemium)
- Frontend tests (prompt form, preview list, edit items, adjust people, confirm flow, error states)

## Resolved Decisions (S1, 2026-04-12)

All 12 open questions resolved by the user during S1 review. Decisions below are binding inputs to S2 (PRD) and S3 (Tech Design).

| # | Decision | Source |
|---|----------|--------|
| 1 | **Ephemeral preview**: option (a) — **client-side only**. Claude response sent to frontend, user edits in React state, confirm sends final items. No new table. Tamper risk is nil (user edits their own list). | User |
| 2 | **Rate limit**: option (b) — **separate per-operation cap**. 5 generations/day via new `generation_per_day` config key in `config/ai.php`, alongside the existing shared pool. `AiUsageTracker` checks both. | User |
| 3 | **Max prompt length**: option (b) — **separate config**. Autocomplete stays at 200 chars. Generation gets `config('ai.generation.max_prompt_chars', 500)`. Passed to `PromptSanitizer::clean` as override. | User |
| 4 | **HU-602 re-generate**: option (a) — **client-side**. Frontend holds prompt + preview in React state. Re-generate sends same prompt with new `people` count. No server persistence. | User |
| 5 | **Add to existing list**: option (a) — **two buttons** after preview: "Crear lista nueva" + "Añadir a lista existente" with `SelectListModal` reused from ReplenishmentBanner. | User |
| 6 | **Freemium check**: option (a) — **at confirm time** (not before Claude call). User may add to existing list (no new list created), so pre-check would incorrectly block. | User |
| 7 | **Commercial rounding**: option (c) — **Claude infers** in the prompt. Explicit instruction: "Round all quantities to logical commercial units available in Spanish supermarkets." | User |
| 8 | **Navigation**: option (a) — **separate button on DashboardPage**. Visible and direct, not inside CreateListModal. | User |
| 9 | **JSON retry**: option (a) — **silent retry**. Show error only if both attempts fail. | User |
| 10 | **Response cap**: **25 items max** in the system prompt. | User |
| 11 | **Edit quantities in preview**: option (a) — **inline editable inputs** in the preview list. | User |
| 12 | **Stitch screen**: confirmed. `generar_lista_con_ia` screen (ID `942e92206ffe48c1b0462ac132e924a6`) must be fetched via MCP in S4 before generating `AIGeneratePage`. | User |

> All open questions are RESOLVED. No TBDs remain. S1 is unblocked for S2 (PRD).

## Open Questions (historical — superseded by Resolved Decisions above)

> Per `core.md` § 9 — TBDs must be resolved before advancing past S2 (PRD).

1. **Ephemeral preview state**: where does the generated preview live between Claude's response and the user's confirm?
   - (a) **Client-side only**: Claude response sent to frontend, user edits in-memory, on confirm the frontend sends the final items to a `POST /api/lists` endpoint. No new table. Simple. Risk: user can tamper with the preview payload.
   - (b) **Server-side with TTL**: store preview in a `generated_previews` table or Redis cache. Confirm endpoint reads from the stored preview, not from client payload. Tamper-resistant but more complex.
   - (c) **Hybrid**: server stores the preview, but the client sends the edits (removals, quantity changes) as a diff. Server applies the diff to the stored preview and creates the list.
   - Recommend (a) for V1: client-side preview is simpler, and the user is editing their own data anyway (tamper = editing their own shopping list, which they can do anyway after creation). Security risk is nil.

2. **Rate limit: shared or separate?**: the HU says 5 generations/day. The existing `AiUsageTracker` has a shared daily quota (currently 20/day for all AI ops). Options:
   - (a) **Shared**: generation counts against the same 20/day pool. A user who does 15 autocompletes can only generate 5 lists. Simple.
   - (b) **Separate per-operation cap**: add a `generation_per_day` limit alongside the existing shared cap. More granular. `AiUsageTracker::canUse` would need to check both shared and per-operation limits.
   - Recommend (b): the HU explicitly says "5 generaciones por día", which implies a separate cap. The autocomplete cap is implicit (shared pool).

3. **Maximum prompt length**: `PromptSanitizer` currently caps at 200 chars (`config('ai.prompt.max_user_input_chars', 200)`). Descriptions like "Cena de cumpleaños para 8 personas con menú mediterráneo, incluyendo entrantes, plato principal, guarniciones y postre" are ~120 chars, but richer descriptions could be longer. Options:
   - (a) Raise the global cap to 500 chars.
   - (b) Add a separate cap for generation prompts (`config('ai.generation.max_prompt_chars', 500)`) and pass it to `PromptSanitizer::clean` as a parameter.
   - Recommend (b): keep autocomplete tight (200), give generation more room (500).

4. **HU-602 "regenerate without re-describing"**: how does the system remember the original prompt?
   - (a) **Client-side**: the frontend stores the prompt in React state and sends it again on re-generate. No server persistence needed.
   - (b) **Server-side**: store the prompt in the preview table/cache.
   - If we chose Q1(a) (client-side preview), then Q4(a) is consistent. The frontend holds the prompt and the preview in state, and the "adjust" action sends the same prompt with a new people count.

5. **"Add to existing list" UX**: the HU says "los ítems se añaden a una lista nueva o a una existente". How does the user pick?
   - (a) After preview, two buttons: "Crear lista nueva" + "Añadir a lista existente" (with a dropdown/modal of active lists, like `SelectListModal` from ReplenishmentBanner).
   - (b) Always create a new list; user can merge later.
   - Recommend (a) for full HU compliance.

6. **Freemium 3-list limit**: checked when?
   - (a) At confirm time (after preview) — consistent with `ShoppingListService::create`.
   - (b) Before the Claude call (prevent wasting a generation on a user who can't create a list).
   - Recommend (a): the user might want to add to an existing list (no new list created), so checking before the call would incorrectly block.

7. **Commercial rounding (HU-602)**: "cantidades redondeadas a unidades comerciales lógicas". Define "commercial units":
   - (a) Round to 0.5 increments for kg/L (e.g., 1.3 kg → 1.5 kg).
   - (b) Round to nearest integer for ud/pack.
   - (c) Let Claude handle the rounding in the prompt ("round quantities to commercial units").
   - Recommend (c): let Claude infer. Hardcoding rounding rules adds complexity and may not cover all product types.

8. **Navigation to /app/generar**: where is the entry point?
   - (a) Button on DashboardPage ("Generar lista con IA") alongside "Nueva lista".
   - (b) Option inside CreateListModal ("Crear manualmente" vs "Generar con IA").
   - (c) Both.
   - Recommend (a) for V1 — a separate button on the dashboard, not inside the modal.

9. **Retry on invalid JSON**: the HU says "reintentar una vez". Is this:
   - (a) Automatic silent retry (user waits longer, sees nothing).
   - (b) Show "first attempt failed, retrying..." message.
   - Recommend (a): silent retry. If both attempts fail, show error message.

10. **Response size cap**: Claude could return 50+ items for "compra semanal para 4 personas". Should we cap at a maximum (e.g., 30 items)?
    - Recommend: cap at 25 items in the system prompt, similar to the 8-item cap in weekly summary.

11. **Edit quantities in preview**: HU-601 AC-5 says "editar cantidades". Does this mean:
    - (a) Inline editable quantity fields in the preview list.
    - (b) Click to edit (modal or inline expansion).
    - Recommend (a): inline editable inputs, simplest UX.

12. **Stitch screen "Generar Lista con IA"**: confirmed in Stitch project. Must be fetched via MCP in S4 (AC equivalent to Epic 5C's AC-19).

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

**Required next step**: STEP 2 — PRD. The 12 open questions must be resolved before the PRD is complete.

## Transition

- Gate: S1 PENDING (awaiting user approval)
- Next Step: STEP 2 — PRD Writing
- Required Artifacts for Next Step: `01-scope.md`
