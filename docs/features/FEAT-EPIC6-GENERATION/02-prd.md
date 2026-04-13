# PRD: FEAT-EPIC6-GENERATION - Generación de lista de compra por contexto (HU-601 + HU-602)

## Business Objective

Abrir el segundo eje de interacción IA del producto: de reactivo (autocompletado, sugerencias) a **generativo** (el usuario describe lo que necesita y la IA produce una lista completa). Esto convierte a Superia en un planificador de compras, no solo un gestor de listas. Es la feature que más diferencia a Superia de apps de listas genéricas: "escribe qué quieres cocinar y nosotros te decimos qué comprar".

El valor está en la **reducción de fricción**: un usuario que sabe "voy a hacer una cena para 8" pero no sabe exactamente qué comprar, hoy tiene que abrir recetas, hacer cálculos y transcribir items uno a uno. Con HU-601, escribe una frase y obtiene una lista pre-generada editable. Con HU-602, ajusta las cantidades al número de comensales sin volver a describir.

## Problem Statement

- **Crear una lista desde cero es lento**: el usuario abre una lista vacía y añade items uno a uno. El autocompletado (Epic 5A) acelera el tipeo pero no elimina la planificación mental ("¿qué necesito para una paella?").
- **Las cantidades son difíciles de calcular**: "¿cuántos kg de arroz para 8 personas?" requiere experiencia culinaria. La mayoría de usuarios compran de más o de menos.
- **No hay flujo "de idea a lista"**: hoy la app solo responde a inputs atómicos (un producto a la vez). No hay un canal que acepte una intención de alto nivel y la resuelva completa.
- **La IA está infrautilizada en el flujo de creación**: las 3 integraciones Claude existentes (autocomplete, complements, weekly summary) son todas post-creación. Ninguna participa en el momento de "voy a hacer una lista".

## Scope

### In Scope

#### Backend
- **ClaudeClientInterface extension**: nuevo método `generateListFromContext(string $description, int $people): array`. Returns `{products: [{nombre, cantidad_tipica, unidad_tipica, categoria, reason}], estimated_cost_usd: float}`. System prompt instruye: JSON estricto, max 25 items, cantidades redondeadas a unidades comerciales, contexto geográfico España.
- **ClaudeClient + FakeClaudeClient**: implementación real + fake con canned + call tracking.
- **Service**: `App\Services\ListGenerationService` con métodos:
  - `generate(User $user, string $description, int $people = 2): array` — sanitiza, verifica quotas (shared + per-operation), llama a Claude, retry silencioso una vez si JSON inválido. Returns array de productos.
  - `confirmAsNewList(User $user, array $items, string $name): ShoppingList` — crea lista via `ShoppingListService::create` + items. Respeta freemium. Lanza `OverflowException` si 3 activas.
  - `confirmAddToExisting(User $user, ShoppingList $list, array $items): ShoppingList` — añade items a lista existente. Verifica ownership.
- **Config**: `config/ai.php` nueva sección `generation`:
  ```php
  'generation' => [
      'model' => env('AI_GENERATION_MODEL', 'claude-sonnet-4-6'),
      'max_tokens' => 3000,
      'max_prompt_chars' => 500,
      'max_items' => 25,
      'generation_per_day' => 5,
      'default_people' => 2,
  ],
  ```
- **AiUsageTracker modification**: `canUse` checks **both** shared daily quota AND per-operation cap (`generation_per_day`). `AiOperation::Generation` already exists.
- **PromptSanitizer**: add optional `$maxChars` parameter to `clean()` method (default stays 200, generation passes 500).
- **Endpoints**:
  - `POST /api/generate-list` (auth) — body: `{description: string, people: int}` → returns generated products array + generation metadata. Counts against rate limit.
  - `POST /api/generate-list/confirm-new` (auth) — body: `{items: [...], name: string}` → creates new list via `confirmAsNewList`. Returns list.
  - `POST /api/generate-list/confirm-existing` (auth) — body: `{items: [...], list_id: int}` → adds to existing list via `confirmAddToExisting`. Returns list.
- **FormRequests**: `GenerateListRequest` (description required, string, max:500; people optional, integer, min:1, max:50), `ConfirmNewListRequest` (items required, array; name required, string, max:60), `ConfirmExistingListRequest` (items required, array; list_id required, exists).
- **Routes**: 3 new routes under `auth:api`.

#### Frontend
- **Page**: `resources/js/pages/AIGeneratePage.jsx` en ruta `/app/generar`:
  - Step 1: prompt input (textarea) + people count selector + "Generar" button
  - Step 2: preview list with inline editable quantity inputs + remove buttons + people adjuster + "Crear lista nueva" / "Añadir a existente" buttons
  - Error states: rate limit reached, Claude error (after silent retry), freemium limit
  - Loading state during Claude call (<10s target)
- **DashboardPage**: nuevo botón "Generar lista con IA" (visible, separado del "Nueva lista")
- **SelectListModal** reutilizado para "Añadir a lista existente"
- **API client**: `resources/js/lib/listGenerationApi.js`
- **Stitch screen**: "Generar Lista con IA" (screen `942e92206ffe48c1b0462ac132e924a6`) fetched via MCP en S4
- **Tests**: vitest para AIGeneratePage (prompt form, preview, edit, adjust, confirm new, confirm existing, error states)

### Out of Scope

- **Server-side preview persistence** — preview lives in client React state only (decision #1). No `generated_previews` table.
- **Streaming Claude response** — synchronous call + JSON parse. Streaming adds complexity for marginal UX improvement given the <10s target.
- **Natural language editing of the generated list** ("quita el pescado y añade más verdura") — user edits inline, no NLP on edits.
- **Recipe integration / meal planning** — the prompt is free-text but the output is a shopping list, not a recipe. No step-by-step cooking instructions.
- **Ingredient substitution** — if the user is allergic to X, they remove it manually. No AI-driven substitution.
- **Cost estimation of the generated list** — that's Epic 7 (HU-701), explicitly out of scope.
- **Duplicate detection in the generated list** — that's Epic 8 (HU-801), out of scope. Claude may generate overlapping items; user removes duplicates manually in preview.
- **Saving generation history** — no table to store "past generations". The generation is ephemeral per decision #1.
- **Sharing a generated preview** — the preview is local to the user's session.
- **Image/photo-based generation** ("take a photo of your fridge") — text only.
- **Multi-language prompts** — Spanish only for V1 (consistent with the rest of the app).
- **Custom system prompts** — the system prompt is hardcoded in `ClaudeClient`.
- **A/B testing of different prompt strategies** — single prompt template for V1.

## Acceptance Criteria

### AC-1: Generate endpoint accepts description and people count
- **Given**: an authenticated user with available generation quota
- **When**: the user calls `POST /api/generate-list` with `{description: "Cena de cumpleaños para 8", people: 8}`
- **Then**: Claude is called with a structured prompt containing the sanitized description + people count; the response contains an array of up to 25 products with nombre, cantidad_tipica, unidad_tipica, categoria, reason; HTTP 200 with the products array.

### AC-2: Generation respects per-operation rate limit (5/day)
- **Given**: a Free user who has already made 5 successful generations today
- **When**: the user calls `POST /api/generate-list`
- **Then**: HTTP 429 with `{error: {code: "GENERATION_LIMIT", message: "..."}}`; no Claude call made; `AiUsageTracker` records `UserCapped`.

### AC-3: Generation respects shared daily quota
- **Given**: a Free user who has used 19 of their 20 shared daily AI operations (autocomplete + complements + summaries + generations combined)
- **When**: the user calls `POST /api/generate-list`
- **Then**: HTTP 429 with `{error: {code: "AI_LIMIT", message: "..."}}`; no Claude call made.

### AC-4: Silent retry on invalid JSON
- **Given**: Claude returns non-JSON on the first attempt
- **When**: the system retries automatically (silent)
- **Then**: if the second attempt returns valid JSON, the user sees the generated list without knowing a retry happened; if the second attempt also fails, HTTP 500 with `{error: {code: "GENERATION_FAILED", message: "..."}}`; circuit breaker records the failure.

### AC-5: Preview shows editable items in frontend
- **Given**: the user submitted a description and received a generated list
- **When**: the preview renders on `AIGeneratePage`
- **Then**: each item shows nombre, cantidad (editable inline input), unidad, categoria; the user can remove individual items with a delete button; the user can edit the quantity field inline.

### AC-6: People adjuster regenerates quantities
- **Given**: the user is viewing a preview with `people: 2`
- **When**: the user changes the people count to 4 and clicks "Regenerar cantidades"
- **Then**: a new Claude call is made with the same description but `people: 4`; the preview updates with new quantities; this counts as a new generation against the rate limit.

### AC-7: Confirm as new list creates a ShoppingList with items
- **Given**: the user is viewing a preview with 5 items and has <3 active lists
- **When**: the user clicks "Crear lista nueva" and provides a list name
- **Then**: a new `ShoppingList` is created via `ShoppingListService::create` with the name; all 5 items are added as `ListItem`; the user is redirected to the new list detail page.

### AC-8: Confirm as new list respects freemium limit
- **Given**: the user has 3 active lists and clicks "Crear lista nueva"
- **When**: the confirm endpoint is called
- **Then**: HTTP 403 with `{error: {code: "FREEMIUM_LIMIT", message: "..."}}`.

### AC-9: Confirm add to existing list appends items
- **Given**: the user clicks "Añadir a lista existente" and selects a list from the SelectListModal
- **When**: the confirm endpoint is called
- **Then**: the selected items are appended as `ListItem` to the existing list; the user is redirected to the list detail page; existing items in the list are NOT modified.

### AC-10: Add to existing list verifies ownership
- **Given**: a user tries to add items to a list owned by another user
- **When**: the confirm endpoint receives `list_id` for a list not owned by the authenticated user
- **Then**: HTTP 404 (not 403, to prevent existence leak).

### AC-11: Prompt sanitized before Claude call
- **Given**: the user submits a description containing prompt injection patterns
- **When**: the service processes the request
- **Then**: `PromptSanitizer::clean` strips injection patterns and truncates to 500 chars; the sanitized description is what reaches Claude.

### AC-12: Frontend shows loading state during generation
- **Given**: the user clicks "Generar"
- **When**: the Claude call is in progress
- **Then**: the UI shows a loading indicator; the "Generar" button is disabled; the target is <10 seconds.

### AC-13: Rate limit error shown in frontend
- **Given**: the user has exhausted their 5/day generation limit
- **When**: the user tries to generate
- **Then**: the frontend shows a clear message: "Has alcanzado tu límite de 5 generaciones diarias."

### AC-14: Navigation from dashboard
- **Given**: the user is on the DashboardPage
- **When**: they look at the action buttons
- **Then**: there is a visible "Generar lista con IA" button that navigates to `/app/generar`.

### AC-15: All endpoints require authentication
- **Given**: an unauthenticated request
- **When**: any of the 3 new endpoints is called
- **Then**: HTTP 401.

### AC-16: Claude prompt includes geography and rounding instruction
- **Given**: the system prompt for list generation
- **When**: Claude processes the request
- **Then**: the prompt includes "contexto geográfico: España" and "redondea cantidades a unidades comerciales disponibles en supermercados españoles".

### AC-17: 100% backend test coverage on new code
- **Given**: the backend test suite
- **When**: `php artisan test` runs
- **Then**: all new service methods, the Claude client extension, the command paths, and the 3 endpoints have tests covering happy path + failure path + edge cases. Suite passes at 100%.

### AC-18: Frontend tests for AIGeneratePage
- **Given**: the vitest suite
- **When**: `npm test` runs
- **Then**: tests cover: prompt form submission, preview rendering, inline quantity editing, item removal, people adjustment, confirm new list, confirm add to existing, rate limit error, generation error, loading state. Suite passes at 100%.

### AC-19: Stitch screen consumed via MCP before implementing the page
- **Given**: the Stitch project "Superia" contains the screen `generar_lista_con_ia` (ID `942e92206ffe48c1b0462ac132e924a6`)
- **When**: S4 frontend work begins
- **Then**: the screen is fetched via `mcp__stitch__get_screen` and referenced in `04-implementation-notes.md`.

### AC-20: BudgetCap and CircuitBreaker respected
- **Given**: the global monthly budget is exceeded OR the circuit breaker is open
- **When**: the user calls `POST /api/generate-list`
- **Then**: HTTP 429 with appropriate error code; no Claude call made.

### AC-21: Default people count is 2
- **Given**: the user submits a generation request without specifying people count
- **When**: the endpoint processes the request
- **Then**: `people` defaults to 2 (from `config('ai.generation.default_people', 2)`).

## UX Decision

- **UX Designer Required**: **NO** (with condition — see note)
- **UX Artifacts**: Stitch project "Superia" → screen "Generar Lista con IA" (ID `942e92206ffe48c1b0462ac132e924a6`). Fetched via MCP in S4 (AC-19).
- **Basic UX Notes**:
  - Two-step flow: (1) prompt + people input, (2) preview + edit + confirm
  - Inline editable quantity inputs (number input, not dropdown)
  - Delete button per item (icon, not text)
  - "Crear lista nueva" primary CTA, "Añadir a existente" secondary
  - Loading state: pulsing skeleton or spinner during generation
  - Error messages consistent with existing patterns (red alert banner)
- **S5-UX will run**: new page + new dashboard button = UI changes.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Claude generates irrelevant items for vague prompts ("compra cosas") | Quality | System prompt includes examples of good descriptions. If the result is unsatisfying, the user edits in preview. No quality gate in V1 — user judgment. |
| Free-text prompt enables more sophisticated prompt injection than product names | Security | `PromptSanitizer::clean` with 500-char cap. System prompt is hardcoded const. Response parsed strictly as JSON array. Items flow to preview (user sees them) then to ListItem creation (validated at write time via enums). No downstream code execution from Claude output. |
| Claude response exceeds 25 items | Quality | System prompt caps at 25. `parseListGenerationEntries` also hard-caps in code. Belt-and-suspenders. |
| Silent retry doubles latency on failure (2x Claude call) | Performance | Worst case: 2x3s = 6s, still under 10s target. If both fail, error shown immediately. User can retry manually. |
| Rate limit (5/day) feels restrictive | UX | The limit is explicit in the HU. UI shows remaining count. User can edit quantities without counting against the limit (edits are client-side). Only "Generar" and "Regenerar cantidades" count. |
| User submits confirm with tampered item data | Security | Preview is client-side (decision #1). User sends final items to confirm endpoint. Since the user can already create arbitrary items via the existing `POST /api/lists/{id}/items` endpoint, tampering with AI-generated items grants no new capability. Items are validated at write time (enums, types). |
| Large generated list (25 items) creates many DB inserts | Performance | Same pattern as `convertToList` in Epic 5C — sequential inserts. Max 25, validated data. Acceptable for V1. |
| "Añadir a existente" could add duplicates to an existing list | Quality | Out of scope (Epic 8 handles duplicates). User can see the preview before confirming and remove duplicates manually. |
| HU-602 "regenerar cantidades" counts as a new generation | UX | Documented in the HU notes. Each Claude call = 1 generation. The user can adjust quantities manually in the preview without a Claude call (inline edits). Only "Regenerar" with a new people count triggers a new call. |
| Stitch MCP server unresponsive during S4 | Operational | Fallback: build components following existing app patterns (indigo palette, Tailwind). Same approach as Epic 5C. |
| `PromptSanitizer::clean` signature change (new param) breaks existing callers | Technical | The parameter is optional with a default. Existing callers are unaffected. |
| Sonnet model for generation is more expensive than Haiku (weekly summary) | Cost | Generation is user-initiated (max 5/day/user). Cost: ~$0.02/call × 5 × N_users. For 1000 users: worst case $100/day, $3000/month. Well within the global `BudgetCap` of $50/month at current scale. If user count grows, tighten the per-day limit or switch to Haiku. |

## Assumptions

- **Claude Sonnet returns better list quality than Haiku** for complex descriptions. The generation config defaults to `claude-sonnet-4-6` (not Haiku) because list quality matters more here than in weekly summary.
- **`AiOperation::Generation` already exists** in the enum (verified during FEAT-EPIC5C-SUMMARY S3).
- **The `SelectListModal` component** from ReplenishmentBanner is reusable as-is for the "add to existing list" flow.
- **Inline editable quantity inputs** do not require a new component — standard `<input type="number">` with React state suffices.
- **The client-side preview state** does not persist across page refreshes — if the user navigates away, the preview is lost. Acceptable for V1.

## Open Questions

None. The 12 open questions from S1 were resolved by the user; decisions documented in `01-scope.md` § "Resolved Decisions (S1, 2026-04-12)".

## Approval

- [ ] PRD approved by [user] on [date]

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
- Required Artifacts for Next Step: `01-scope.md`, `02-prd.md`
