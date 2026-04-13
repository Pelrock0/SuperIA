# Technical Design: FEAT-EPIC6-GENERATION

## Overview

Feature de generación de listas de compra completas a partir de una descripción en lenguaje natural. Arquitectura en dos capas: (1) un `ListGenerationService` que orquesta sanitización, verificación de quotas (dual: shared pool + per-operation cap de 5/day), llamada a Claude con retry silencioso, y confirmación de la lista; (2) un `AIGeneratePage` frontend con flujo de dos pasos (prompt → preview editable → confirm). El preview vive íntegramente en React state (client-side, decision #1) — no hay tabla nueva ni caché server-side para el preview.

La integración Claude reutiliza toda la infra existente: `PromptSanitizer` (extendido con parámetro `$maxChars` opcional, default 200, generation pasa 500), `BudgetCap`, `CircuitBreaker`, `AiUsageTracker` (extendido para soportar per-operation cap). El método `ClaudeClientInterface::generateListFromContext` es la 4a extensión del interface (después de `suggest`, `generateCatalog`, `suggestComplements`, `generateWeeklySummary`).

HU-602 (ajustar por personas) se resuelve client-side: el frontend guarda el prompt en state y envía una nueva petición con el mismo `description` y un `people` distinto. Cada re-generación cuenta como una nueva llamada (contra el rate limit).

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | Validación de items (enum-safe), FreemiumLimit (heredado de ShoppingListService) | Existing: `ProductCategory`, `ItemUnit`, `ListStatus` enums; `ShoppingList`, `ListItem` models |
| Services | Toda la lógica: sanitización, quota check (dual), Claude call + retry, confirmación new/existing | `App\Services\ListGenerationService` (NEW) |
| Infrastructure | Claude client real + fake, PromptSanitizer (modified), AiUsageTracker (modified), config | `ClaudeClient::generateListFromContext` (NEW), `FakeClaudeClient` (extended), `PromptSanitizer::clean` (modified signature), `AiUsageTracker::canUseOperation` (NEW method) |
| Controllers/API | Thin: validate FormRequest, delegate to service, return JSON | `App\Http\Controllers\ListGenerationController` (NEW, 3 actions) |
| Frontend | Two-step page (prompt → preview → confirm), dashboard button | `AIGeneratePage.jsx` (NEW), `listGenerationApi.js` (NEW), mods to `DashboardPage.jsx` + `app.jsx` |

### Data Flow

#### Generate list (POST /api/generate-list)

```
1. User submits {description, people} from AIGeneratePage
2. ListGenerationController::generate validates via GenerateListRequest
3. Controller calls ListGenerationService::generate($user, $description, $people)
4. Service checks:
   a. BudgetCap::canSpend() → false → return 429 BUDGET_CAPPED
   b. AiUsageTracker::canUse($user, AiOperation::Generation) → false → return 429 AI_LIMIT (shared pool)
   c. AiUsageTracker::canUseOperation($user, AiOperation::Generation, config('ai.generation.generation_per_day')) → false → return 429 GENERATION_LIMIT (per-operation)
   d. CircuitBreaker::allow() → false → return 429 CIRCUIT_OPEN
5. Service sanitizes: PromptSanitizer::clean($description, $maxChars=500)
6. Service builds context: {description: sanitized, people: int}
7. Service calls ClaudeClient::generateListFromContext($context)
   a. If ClaudeException → retry ONCE silently
   b. If 2nd attempt also fails → CircuitBreaker::recordFailure → throw
   c. On success → CircuitBreaker::recordSuccess
8. Service parses response: max 25 items, validated keys
9. Service records: AiUsageTracker::record($user, Generation, Success, $cost)
10. Controller returns 200 {data: {products: [...], meta: {people, description_used}}}
```

#### Confirm as new list (POST /api/generate-list/confirm-new)

```
1. User clicks "Crear lista nueva" with edited items + name from preview
2. ListGenerationController::confirmNew validates via ConfirmNewListRequest
3. Controller calls ListGenerationService::confirmAsNewList($user, $items, $name)
4. Service calls ShoppingListService::create($user, {name, emoji: '🤖'})
   → OverflowException if freemium 3-list cap hit
5. Service iterates $items → ListItem::create per item (validated enum values via tryFrom)
6. Returns the created ShoppingList
7. Controller returns 201 {data: list}
```

#### Confirm add to existing (POST /api/generate-list/confirm-existing)

```
1. User selects list from SelectListModal
2. ListGenerationController::confirmExisting validates via ConfirmExistingListRequest
3. Controller verifies $list->user_id === auth()->id() → 404 if not
4. Controller calls ListGenerationService::confirmAddToExisting($user, $list, $items)
5. Service iterates $items → ListItem::create per item
6. Returns the updated ShoppingList
7. Controller returns 200 {data: list}
```

#### Frontend flow (AIGeneratePage)

```
1. User navigates to /app/generar (from dashboard button)
2. Step 1: textarea for description + number input for people (default 2) + "Generar" button
3. On submit: call POST /api/generate-list → loading state
4. On success: transition to Step 2 (preview)
5. Step 2: product list with:
   - Each item: nombre (read-only), cantidad (editable input), unidad (read-only), delete button
   - People adjuster: number input + "Regenerar cantidades" button
   - Footer: "Crear lista nueva" (primary) + "Añadir a existente" (secondary)
6. "Regenerar cantidades": same description + new people → POST /api/generate-list (counts as new generation)
7. "Crear lista nueva": prompt for name → POST /api/generate-list/confirm-new → redirect to list
8. "Añadir a existente": open SelectListModal → POST /api/generate-list/confirm-existing → redirect to list
9. Error states: rate limit (429), generation failed (500), freemium (403)
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| `generate` | No transaction (read-only: quota checks + external API call) | The only writes are `AiUsageTracker::record` (single-row append). No multi-row consistency needed. |
| `confirmAsNewList` | Reuses `ShoppingListService::create` transaction (wraps list creation + freemium check). Item inserts are outside that transaction (same pattern as Epic 5C `convertToList`). | Consistent with existing pattern. Max 25 items, validated data. |
| `confirmAddToExisting` | No explicit transaction. Item inserts are individual (same pattern). | Existing list row is not modified, only new items appended. |

## Data Model

### New Tables
None. Preview is client-side (decision #1). Generated items flow to existing `shopping_lists` + `list_items` tables on confirm.

### Modified Tables
None.

### Migrations
None.

### Config Changes

`config/ai.php` — add new section:

```php
'generation' => [
    'model' => env('AI_GENERATION_MODEL', 'claude-sonnet-4-6'),
    'max_tokens' => (int) env('AI_GENERATION_MAX_TOKENS', 3000),
    'max_prompt_chars' => (int) env('AI_GENERATION_MAX_PROMPT_CHARS', 500),
    'max_items' => (int) env('AI_GENERATION_MAX_ITEMS', 25),
    'generation_per_day' => (int) env('AI_GENERATION_PER_DAY', 5),
    'default_people' => (int) env('AI_GENERATION_DEFAULT_PEOPLE', 2),
],
```

`.env.example` — add:

```
AI_GENERATION_MODEL=claude-sonnet-4-6
AI_GENERATION_PER_DAY=5
```

### API Changes

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/generate-list` | POST | `auth:api` | Generate product list from description + people count |
| `/api/generate-list/confirm-new` | POST | `auth:api` | Create new ShoppingList from confirmed preview items |
| `/api/generate-list/confirm-existing` | POST | `auth:api` | Append confirmed preview items to an existing list |

### AiUsageTracker Modification

Add new method `canUseOperation(User $user, AiOperation $operation, int $limit): bool` that checks the per-operation daily count (not the shared pool — that's the existing `canUse`). The `generate` flow calls **both**:

```php
// Shared pool check (existing)
if (! $this->usageTracker->canUse($user, AiOperation::Generation)) { ... }

// Per-operation check (new)
$perDayLimit = (int) config('ai.generation.generation_per_day', 5);
if (! $this->usageTracker->canUseOperation($user, AiOperation::Generation, $perDayLimit)) { ... }
```

Implementation of `canUseOperation`:

```php
public function canUseOperation(User $user, AiOperation $operation, int $limit): bool
{
    return $this->usedTodayForOperation($user, $operation) < $limit;
}
```

This reuses the existing `usedTodayForOperation` method — zero new queries, zero schema changes.

### PromptSanitizer Modification

Change `clean(string $input): string` signature to `clean(string $input, ?int $maxChars = null): string`. If `$maxChars` is null, uses `config('ai.prompt.max_user_input_chars', 200)` (existing behavior). If provided, uses the override. Backwards compatible.

### ClaudeClientInterface Extension

```php
/**
 * Generate a full shopping list from a natural language description.
 *
 * @param  array{description: string, people: int}  $context
 * @return array{
 *     products: array<int, array{nombre: string, cantidad_tipica: ?float, unidad_tipica: ?string, categoria: ?string, reason: ?string}>,
 *     estimated_cost_usd: float,
 * }
 *
 * @throws \App\Support\Ai\Exceptions\ClaudeException on any failure
 */
public function generateListFromContext(array $context): array;
```

System prompt:

```
Eres un asistente que genera listas de compra completas para hogares en España.
Recibes una descripción en lenguaje natural de lo que el usuario necesita y el número de personas.
Devuelve un array JSON estricto de hasta 25 objetos con claves:
  nombre (nombre genérico en español, sin marca),
  cantidad_tipica (numérico, ajustado al número de personas indicado),
  unidad_tipica (kg, g, L, ml, ud, pack),
  categoria (frutas_verduras, carnes_pescados, lacteos_huevos, panaderia, bebidas, congelados, limpieza, higiene_personal, conservas, otros),
  reason (frase corta explicando por qué se incluye).
Reglas:
- Sin marcas comerciales.
- Cantidades ajustadas al número de personas indicado.
- Redondea todas las cantidades a unidades comerciales disponibles en supermercados españoles.
- Máximo 25 productos.
- Contexto geográfico: España.
Responde SOLO con el array JSON. Sin prosa, sin markdown, sin comentarios.
```

## Performance

### Query optimization
- No DB queries in the `generate` path except `AiUsageTracker` quota checks (2 indexed queries on `ai_usage_log`).
- `confirmAsNewList` reuses `ShoppingListService::create` (1 transaction for list creation + N item inserts, max 25).
- `confirmAddToExisting` does N item inserts (max 25) + 1 ownership check.

### Latency target
- Claude Sonnet API call: ~2-4s typical for 25-item list generation.
- JSON parsing: <10ms.
- Silent retry worst case: 2x call = 4-8s.
- Total target: <10s. Achievable with Sonnet. Haiku fallback available via config if needed.

### Caching
None. Each generation is unique (different prompt).

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| **Client-side preview (decision #1)** | No new table, no TTL management, simple | User can tamper with items before confirm | **Selected** — tamper = editing their own list, which they can already do. |
| Server-side preview with TTL | Tamper-resistant | New table or cache, cleanup job, complexity | Rejected per user decision. |
| **Separate per-operation rate limit (decision #2)** | HU-compliant (5/day explicit) | AiUsageTracker needs new method | **Selected** — 1 new method, reuses existing query. |
| Shared pool only | Simpler | HU says 5/day, shared pool is 20 — mismatch | Rejected per user decision. |
| **PromptSanitizer with optional $maxChars (decision #3)** | Backwards compatible, generation gets 500 chars | Slightly wider attack surface | **Selected** — 500 is still bounded, PromptSanitizer regex defense applies regardless of length. |
| Separate sanitizer class for generation | Full isolation | Code duplication of the same 8 regex patterns | Rejected — DRY violation. |
| **Claude Sonnet for generation (not Haiku)** | Better list quality for complex descriptions | ~10x cost per call | **Selected** — generation is user-initiated (max 5/day), quality matters. BudgetCap is the safety net. |
| Haiku for generation | Cheaper | Lower quality for nuanced descriptions | Available as config fallback. |
| **Silent retry (decision #9)** | User doesn't see "retrying..." noise | Doubles worst-case latency | **Selected** — 2x3s = 6s, under 10s target. |
| Visible retry | User informed | Extra UX state, user anxiety during wait | Rejected per user decision. |
| **25-item cap (decision #10)** | Bounded response, predictable cost | May frustrate users wanting 50-item lists | **Selected** — 25 covers most meal/event scenarios. |
| Unlimited | Flexible | Unbounded cost, response parsing risk | Rejected. |
| **Inline editable quantities (decision #11)** | Direct, fast editing | More complex frontend state | **Selected** — standard React controlled inputs. |
| Click-to-edit modal | Simpler state | More clicks per edit | Rejected per user decision. |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Free-text prompt enables richer prompt injection than product names | Medium | Medium | `PromptSanitizer` with 8 regex patterns + 500-char cap. System prompt is hardcoded const. Response parsed strictly. Items rendered in React JSX (auto-escaped). Items validated at write time (enum tryFrom). No code execution from Claude output. |
| Claude generates low-quality list for vague prompts | Low | Medium | System prompt includes structure examples. User can edit/remove items in preview. No quality gate in V1 — user judgment. |
| 5/day rate limit feels restrictive | Low | Medium | UI shows remaining count. Manual quantity edits don't count (client-side). Only "Generar" / "Regenerar" count. |
| Silent retry makes the UX feel slow (6-8s) | Low | Low | Under 10s target. Loading indicator with message "Generando tu lista..." |
| Cost: Sonnet at ~$0.02/call × 5/day × N users | Medium | Low | BudgetCap ($50/month default). At 100 users: worst case $10/day = $300/month. Exceeds default cap → blocks automatically. Adjust cap or switch to Haiku via config. |
| User navigates away during generation, loses preview | Low | Medium | Client-side state lost on navigation (decision #1). Acceptable — generation takes <10s, user waits for the result. |
| `PromptSanitizer::clean` signature change breaks existing callers | None | None | Optional parameter with null default → existing callers pass nothing → behavior unchanged. |

## Open Questions

None. All 12 questions resolved in S1.

## Implementation Notes

### Suggested execution order for S4

1. **Config**: add `generation` section to `config/ai.php` + `.env.example`
2. **PromptSanitizer**: add optional `$maxChars` parameter, update existing tests
3. **AiUsageTracker**: add `canUseOperation` method, add unit test
4. **ClaudeClientInterface**: add `generateListFromContext` method signature
5. **ClaudeClient**: add system prompt + implementation + parser (match existing pattern)
6. **FakeClaudeClient**: add canned property + call tracking + implementation
7. **ListGenerationService**: implement `generate`, `confirmAsNewList`, `confirmAddToExisting`
8. **FormRequests**: GenerateListRequest, ConfirmNewListRequest, ConfirmExistingListRequest
9. **ListGenerationController**: 3 thin actions
10. **Routes**: 3 new routes in `routes/api.php`
11. **Backend tests**: service tests + controller tests + modified sanitizer/tracker tests
12. **Run backend suite**: must pass 523+ (all existing + new)
13. **Frontend**: fetch Stitch screen via MCP, then AIGeneratePage, listGenerationApi, dashboard button, route
14. **Frontend tests**: vitest for AIGeneratePage
15. **Run frontend suite**: must pass 226+ (all existing + new)

### Frontend work identified
YES — `AIGeneratePage.jsx` (new two-step page), `listGenerationApi.js` (new API client), mods to `DashboardPage.jsx` (button) + `app.jsx` (route). S4 should be `S4-BOTH`. `has_ui_changes = YES` so S5-UX runs.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation (S4-BOTH)
- Required Artifacts: `01-scope.md`, `02-prd.md`, `03-technical-design.md`
