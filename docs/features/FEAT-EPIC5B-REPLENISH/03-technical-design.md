# Technical Design: FEAT-EPIC5B-REPLENISH

## Overview

Two features (replenishment alerts + complementary items) built on the Epic 5A Claude foundation. Pure SQL is the primary engine for both; Claude is a conditional fallback used only when local data is insufficient. The replenishment detection uses a single aggregated query over `producto_historial` that computes purchase frequency, recency, and urgency ratio per product, then filters against the user's active-list items, silenced products, and dismissed suggestions. The complementary detection is a two-step query: first list the user's completed list IDs (via the existing `shopping_lists.items_total/items_completed` counters from Epic 3), then find products that co-occur with the input product in those lists above a 60% ratio threshold.

Two new tables (`user_silenced_products`, `ai_dismissed_suggestions`) hold the user's dismiss state. Both are user-scoped, cascade on user delete, and have no cross-user visibility. One minor refactor to `AiUsageTracker` makes the daily AI quota shared across all operations (previously per-operation) — this is a 5-line change with one Epic 5A test update. The new `suggestComplements` method on `ClaudeClientInterface` follows the same pattern as `suggest` and `generateCatalog`.

Frontend gets two new components and one modal, plus small additions to `DashboardPage` and `ListDetailPage`. Neither path blocks existing flows: the replenishment banner is a new section at the top of the dashboard; the complement chip is async and best-effort after item creation.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | Enums documenting actions and sources (no new entities beyond the two tables) | `App\Enums\ReplenishmentAction` (`accepted`, `ignored`, `silenced`), `App\Enums\ComplementSource` (`history`, `ai`) |
| Services | Replenishment detection + state mutations, complementary detection with Claude fallback, shared history stats helper | `App\Services\ReplenishmentSuggestionService`, `App\Services\ComplementarySuggestionService`, `App\Services\ProductHistoryStatsService` |
| AI Support (extended from 5A) | `ClaudeClient::suggestComplements`, `FakeClaudeClient::cannedComplements`, `AiUsageTracker::usedTodayAcrossAllOperations` | `app/Support/Ai/*` |
| Infrastructure | New tables, new config key | migrations, `config/ai.php` |
| Controllers/API | Thin delegation, FormRequest validation, ownership checks inherent via `auth('api')->user()` | `App\Http\Controllers\ReplenishmentController`, `App\Http\Controllers\ComplementController` |
| Frontend | Banner with 3 cards, select-list modal, complement chip, dashboard/list-detail integration, API clients | `components/dashboard/ReplenishmentBanner.jsx`, `components/dashboard/SelectListModal.jsx`, `components/items/ComplementaryChip.jsx`, `lib/replenishmentApi.js`, `lib/complementsApi.js`, updates to `pages/DashboardPage.jsx` and `pages/ListDetailPage.jsx` |

### Data Flow

#### Dashboard replenishment load
```
1. GET /api/dashboard/replenishment
2. ReplenishmentController::index -> authorized via auth middleware
3. ReplenishmentSuggestionService::forUser($user):
     a. Check: does user have at least one active list with >=3 items? If not, return []
     b. Cache::remember("replenishment:user:{id}", 300, function () use ($user) {
          return $this->computeCandidates($user);
        })
4. computeCandidates($user):
     a. Single aggregated query over producto_historial (see SQL below)
     b. If result count < 3 AND user has >= 10 distinct products in history -> optional Claude fallback (best-effort, counts toward quota)
     c. Return array of {producto_nombre, purchase_count, last_purchased_at, avg_days_between, days_since_last, urgency_ratio, source}
5. Response: {suggestions: [...]}
```

#### Replenishment accept
```
1. POST /api/replenishment/accept {producto_nombre, list_id}
2. AcceptReplenishmentRequest validates producto_nombre (string, 1-80) and list_id (integer, exists)
3. ReplenishmentController::accept:
     a. Authorize list ownership
     b. Delegate to ListItemService::create($list, ['name' => $producto_nombre]) — reuses Epic 3 code
     c. ReplenishmentSuggestionService::invalidateCache($user)
4. Response: 201 with created item + updated list counters
```

#### Replenishment ignore
```
1. POST /api/replenishment/ignore {producto_nombre}
2. IgnoreReplenishmentRequest validates producto_nombre
3. ReplenishmentSuggestionService::ignore($user, $producto_nombre):
     AiDismissedSuggestion::create([
         'user_id' => $user->id,
         'producto_nombre' => $producto_nombre,
         'dismissed_until' => now()->addHours(24),
     ])
4. invalidateCache($user)
5. Response: 204
```

#### Replenishment silence
```
1. POST /api/replenishment/silence {producto_nombre}
2. Validate
3. UserSilencedProduct::firstOrCreate(['user_id' => $user->id, 'producto_nombre' => $producto_nombre])
4. invalidateCache($user)
5. Response: 204
```

#### Complement query
```
1. GET /api/suggestions/complements?product=X&list_id=Y
2. ComplementQueryRequest validates: product (1-80), list_id (integer, exists, owned by user)
3. ComplementController::index -> ComplementarySuggestionService::suggest($user, $X, $listId):
     a. completedListsCount = stats->completedListsCount($user)
     b. if completedListsCount < config('ai.thresholds.min_completed_lists'):
          return $this->tryAiFallback($user, $X, $listId)
     c. $local = $this->localCooccurrence($user, $X, $listId)
     d. return $local (or fallback to AI if empty and quota allows)
4. Response: {suggestions: [...], source: history | ai | none}
```

#### Complement Claude fallback
```
1. BudgetCap::canSpend() check (inherited from 5A)
2. AiUsageTracker::canUse($user, AiOperation::Complement) — shared quota check
3. CircuitBreaker::allow() — reuses the 'claude' breaker
4. PromptSanitizer::clean($productName)
5. ClaudeClient::suggestComplements($cleanX) -> returns array of {nombre, unidad_tipica, categoria}
6. Filter out any product already in the current list
7. AiUsageTracker::record($user, Complement, Success, $cost)
8. Return up to 2 suggestions with source='ai'
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| Accept replenishment | Item creation + cache invalidation | Reuses `ListItemService::create` which already wraps its own transaction |
| Ignore/silence | Single insert, cache invalidation outside transaction | No cross-row consistency needed |
| Complement local query | Read-only, no transaction | |
| Complement Claude call | Logging + cache operations separate | Failures are isolated |

### Replenishment Detection SQL

Single query executed in `ReplenishmentSuggestionService::computeCandidates`:

```sql
SELECT
    producto_nombre,
    COUNT(*)                                   AS purchase_count,
    MAX(fecha_compra)                          AS last_purchased_at,
    DATEDIFF(NOW(), MAX(fecha_compra))         AS days_since_last,
    CASE
        WHEN COUNT(*) > 1
            THEN DATEDIFF(MAX(fecha_compra), MIN(fecha_compra)) / (COUNT(*) - 1)
        ELSE NULL
    END                                        AS avg_days_between
FROM producto_historial
WHERE user_id = :user_id
GROUP BY producto_nombre
HAVING purchase_count >= :min_occurrences
   AND avg_days_between IS NOT NULL
   AND days_since_last > avg_days_between * :factor
```

Then in PHP, filter the result set against three user-scoped exclusion sets:
1. Product names currently in any active list (query `list_items` joined to `shopping_lists` WHERE status='active' AND user_id=?).
2. Product names in `user_silenced_products` (query WHERE user_id=?).
3. Product names in `ai_dismissed_suggestions` WHERE user_id=? AND dismissed_until > NOW().

Filtering in PHP instead of in a single mega-query keeps the SQL readable and the exclusion sets explicit. Each exclusion query is a simple indexed lookup (O(log n)).

Then: sort by `days_since_last / avg_days_between DESC`, take top 3, map to DTO array.

**Why not all in one SQL:** the aggregate with `NOT IN (subquery)` blows up the query plan on MySQL and makes indexing ambiguous. Three small queries + PHP filter is both faster and maintainable.

### Co-occurrence SQL (Complementary Detection)

Two queries executed in `ComplementarySuggestionService::localCooccurrence`:

**Step 1 — get completed list IDs for the user:**

```sql
SELECT id
FROM shopping_lists
WHERE user_id = :user_id
  AND items_total > 0
  AND items_total = items_completed
```

Uses `shopping_lists.items_total/items_completed` counters maintained by `ListItemService::syncCounters` (Epic 3). No join to `list_items`, no `COUNT(*)` aggregation. Fast.

**Step 2 — find products that co-occur with X in those completed lists:**

```sql
SELECT
    ph.producto_nombre,
    COUNT(DISTINCT ph.lista_id) AS co_count
FROM producto_historial ph
WHERE ph.user_id = :user_id
  AND ph.lista_id IN (:completed_list_ids)
  AND ph.lista_id IN (
      SELECT DISTINCT ph2.lista_id
      FROM producto_historial ph2
      WHERE ph2.user_id = :user_id
        AND LOWER(ph2.producto_nombre) = LOWER(:input_product)
        AND ph2.lista_id IN (:completed_list_ids)
  )
  AND LOWER(ph.producto_nombre) <> LOWER(:input_product)
GROUP BY ph.producto_nombre
```

Then in PHP:
1. Compute `lists_with_x = COUNT(DISTINCT lista_id)` from the inner subquery (or fetch in a separate count query).
2. For each returned row, `co_ratio = co_count / lists_with_x`.
3. Filter `co_ratio >= config('ai.thresholds.co_occurrence_ratio')`.
4. Exclude products already present in the current list (fetch `LIST` names via `ListItemService`).
5. Sort by `co_ratio DESC`, take top 2.

Again: keeping the ratio filter in PHP instead of SQL simplifies the query and makes testing easier.

**Indexing**: `producto_historial` already has `(user_id, producto_nombre)` and `(user_id, fecha_compra)` from Epic 3. The `(user_id, lista_id)` pair is *not* indexed. For the co-occurrence query to be fast, we add an index:

```sql
CREATE INDEX historial_user_lista_idx ON producto_historial (user_id, lista_id);
```

Added in a standalone migration. Reversible.

## Data Model

### New Table: `user_silenced_products`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | |
| `user_id` | foreignId | FK users.id, cascadeOnDelete | Owner |
| `producto_nombre` | string(80) | NOT NULL | Silenced product name |
| `silenced_at` | timestamp | NOT NULL | When the user clicked silence |

Indexes: `UNIQUE(user_id, producto_nombre)` with short name `silenced_user_product_unique`.

### New Table: `ai_dismissed_suggestions`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | |
| `user_id` | foreignId | FK users.id, cascadeOnDelete | Owner |
| `producto_nombre` | string(80) | NOT NULL | Dismissed product name |
| `dismissed_until` | timestamp | NOT NULL | TTL expiry |
| `created_at` | timestamp | nullable | For audit |

Indexes: `(user_id, dismissed_until)` with short name `dismissed_user_until_idx`. No unique — user can dismiss the same product again after expiry; only the latest row matters because the exclusion query is `WHERE dismissed_until > NOW()`.

### New Index on Existing Table

`producto_historial`: add `(user_id, lista_id)` index. Short name `historial_user_lista_idx`.

### Config Addition

```php
// config/ai.php (existing file)
'thresholds' => [
    'min_occurrences' => (int) env('AI_MIN_OCCURRENCES', 3),
    'min_completed_lists' => (int) env('AI_MIN_COMPLETED_LISTS', 5),
    'co_occurrence_ratio' => (float) env('AI_CO_OCCURRENCE_RATIO', 0.60),
    'replenishment_factor' => (float) env('AI_REPLENISHMENT_FACTOR', 0.80),  // NEW
],
```

### API Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/dashboard/replenishment` | GET | JWT | Up to 3 replenishment suggestions (cached 5min) |
| `/api/replenishment/accept` | POST | JWT | `{producto_nombre, list_id}` — add to list, invalidate cache |
| `/api/replenishment/ignore` | POST | JWT | `{producto_nombre}` — insert dismissed 24h, invalidate cache |
| `/api/replenishment/silence` | POST | JWT | `{producto_nombre}` — insert silenced permanent, invalidate cache |
| `/api/suggestions/complements` | GET | JWT | `?product=X&list_id=Y` — up to 2 suggestions |

All JWT-protected. No new throttle on the query endpoints (GETs are cached). The state-mutating endpoints use the global throttle `60,1` already in place from the existing API group.

### API Response Format

```json
// GET /api/dashboard/replenishment
{
  "data": {
    "suggestions": [
      {
        "producto_nombre": "Leche entera",
        "purchase_count": 12,
        "last_purchased_at": "2026-04-01T00:00:00Z",
        "days_since_last": 10,
        "avg_days_between": 7.0,
        "urgency_ratio": 1.43,
        "frequency_label": "Sueles comprar leche entera cada 7 dias",
        "source": "history"
      }
    ],
    "has_active_list_with_items": true
  }
}

// POST /api/replenishment/accept -> 201 with the created ListItem shape (reuses Epic 3 response)
// POST /api/replenishment/ignore -> 204 empty
// POST /api/replenishment/silence -> 204 empty

// GET /api/suggestions/complements?product=pasta&list_id=5
{
  "data": {
    "suggestions": [
      {"nombre": "Tomate frito", "unidad_tipica": "ud", "categoria": "conservas", "source": "history", "co_ratio": 0.80},
      {"nombre": "Queso rallado", "unidad_tipica": "g", "categoria": "lacteos_huevos", "source": "history", "co_ratio": 0.65}
    ],
    "ai_fallback_used": false,
    "ai_limit_reached": false
  }
}
```

## Service design

### `ProductHistoryStatsService`

Shared helper. Exposes:

- `completedListsCount(User $user): int` — single count query over `shopping_lists` using the `items_total == items_completed` predicate.
- `completedListIds(User $user): array<int>` — same query, returns IDs.

Used by both `ReplenishmentSuggestionService` (for the "has active list with items" check) and `ComplementarySuggestionService`.

### `ReplenishmentSuggestionService`

```php
public function forUser(User $user): array
{
    if (! $this->userHasActiveListWithItems($user)) {
        return [];
    }

    return Cache::remember(
        $this->cacheKey($user),
        now()->addMinutes(5),
        fn () => $this->computeCandidates($user),
    );
}

public function ignore(User $user, string $productName): void { ... }
public function silence(User $user, string $productName): void { ... }
public function invalidateCache(User $user): void { Cache::forget($this->cacheKey($user)); }

private function computeCandidates(User $user): array { /* see SQL above + PHP filter */ }
private function userHasActiveListWithItems(User $user): bool { /* query */ }
private function cacheKey(User $user): string { return "replenishment:user:{$user->id}"; }
```

### `ComplementarySuggestionService`

```php
public function __construct(
    private ProductHistoryStatsService $stats,
    private PromptSanitizer $sanitizer,
    private BudgetCap $budgetCap,
    private AiUsageTracker $usageTracker,
    private CircuitBreaker $circuitBreaker,
    private ClaudeClientInterface $claude,
) {}

public function suggest(User $user, string $productName, int $listId): array
{
    $completedCount = $this->stats->completedListsCount($user);

    if ($completedCount < config('ai.thresholds.min_completed_lists')) {
        return $this->tryAiFallback($user, $productName, $listId);
    }

    $local = $this->localCooccurrence($user, $productName, $listId);

    return [
        'suggestions' => $local,
        'ai_fallback_used' => false,
        'ai_limit_reached' => false,
    ];
}

private function localCooccurrence(User $user, string $productName, int $listId): array { ... }
private function tryAiFallback(...): array { /* mirrors Epic 5A's tryAiFallback pattern */ }
private function currentListProductNames(int $listId): array { ... }
```

### `AiUsageTracker` refactor

Change `canUse` to count across all operations:

```php
public function canUse(User $user, AiOperation $operation): bool
{
    $plan = $this->planFor($user);
    $quota = $plan->dailySuggestionQuota();
    if ($quota === null) return true;
    return $this->usedTodayAcrossAllOperations($user) < $quota;
}

public function usedTodayForOperation(User $user, AiOperation $operation): int
{
    return AiUsageLog::query()
        ->where('user_id', $user->id)
        ->where('operation', $operation->value)
        ->where('status', AiUsageStatus::Success->value)
        ->whereDate('date', $this->madridToday())
        ->count();
}

public function usedTodayAcrossAllOperations(User $user): int
{
    return AiUsageLog::query()
        ->where('user_id', $user->id)
        ->where('status', AiUsageStatus::Success->value)
        ->whereDate('date', $this->madridToday())
        ->count();
}
```

The existing `usedToday` method is renamed to `usedTodayForOperation` to keep the per-operation breakdown available for admin analytics. The single Epic 5A test that asserted per-operation independence (`AiUsageTrackerTest::test_different_operations_have_independent_counters`) is rewritten to assert the new shared behavior: "used today across all operations sums them up". Non-functional test update.

### `ClaudeClient::suggestComplements`

```php
public function suggestComplements(string $productName): array
{
    $payload = [
        'model' => config('ai.model'),
        'max_tokens' => 256,
        'system' => self::COMPLEMENTS_SYSTEM_PROMPT,
        'messages' => [[
            'role' => 'user',
            'content' => "Producto: {$productName}",
        ]],
    ];

    // ... HTTP call identical to suggest / generateCatalog ...

    return [
        'products' => $this->parseComplementEntries($body),
        'estimated_cost_usd' => $this->estimateCost($body),
    ];
}
```

`COMPLEMENTS_SYSTEM_PROMPT` (constant):

> "Eres un asistente que sugiere productos complementarios para listas de compra espanolas. Devuelve un array JSON estricto de hasta 2 objetos con claves: nombre (nombre generico en espanol), unidad_tipica (kg/g/L/ml/ud/pack), categoria (enum fija). Responde SOLO con el array JSON. Sin prosa."

`parseComplementEntries` shares structure with `parseCatalogEntries` but caps at 2 and uses the same field names.

## Controllers

### `ReplenishmentController`

```php
public function __construct(
    private ReplenishmentSuggestionService $service,
    private ListItemService $listItems,
) {}

public function index(): JsonResponse
{
    $user = auth('api')->user();
    $suggestions = $this->service->forUser($user);
    return response()->json(['data' => ['suggestions' => $suggestions]]);
}

public function accept(AcceptReplenishmentRequest $request): JsonResponse
{
    $user = auth('api')->user();
    $list = ShoppingList::findOrFail($request->validated('list_id'));
    if ($list->user_id !== $user->id) abort(403);

    $result = $this->listItems->create($list, ['name' => $request->validated('producto_nombre')]);
    $this->service->invalidateCache($user);

    return response()->json(['data' => $result], 201);
}

public function ignore(IgnoreReplenishmentRequest $request): JsonResponse { ... }
public function silence(SilenceReplenishmentRequest $request): JsonResponse { ... }
```

### `ComplementController`

```php
public function __construct(private ComplementarySuggestionService $service) {}

public function index(ComplementQueryRequest $request): JsonResponse
{
    $user = auth('api')->user();
    $list = ShoppingList::findOrFail($request->validated('list_id'));
    if ($list->user_id !== $user->id) abort(403);

    $result = $this->service->suggest(
        $user,
        (string) $request->validated('product'),
        (int) $request->validated('list_id'),
    );

    return response()->json(['data' => $result]);
}
```

## Frontend architecture

### `ReplenishmentBanner.jsx`

- Fetches `GET /api/dashboard/replenishment` on mount
- Renders `null` if no suggestions
- Renders up to 3 cards with product name + frequency label + "Hace N dias" + 3 buttons
- Accept:
  - If user has >1 active list (fetched from existing dashboard data): open `SelectListModal` → on select, POST accept with list_id
  - If 1 active list: POST accept directly with that list_id
- Ignore: POST `/api/replenishment/ignore`, remove card optimistically
- Silence: POST `/api/replenishment/silence`, remove card optimistically
- All actions trigger a dashboard-level refetch to reflect updated data elsewhere
- `useState` for suggestions, loading, error
- No polling — fetched once on mount

### `SelectListModal.jsx`

- Props: `lists` (array from existing dashboard data), `onSelect(listId)`, `onCancel`
- Renders a modal with a simple list of active lists (name + emoji)
- Click to select, callback fires, modal closes
- Reuses the overlay pattern from `CreateListModal` / `ShareListModal` / `ConfirmClearHistoryModal`

### `ComplementaryChip.jsx`

- Props: `productName`, `listId`, `onAccept(suggestion)`, `onDismiss()`
- On mount: fetches `GET /api/suggestions/complements?product={productName}&list_id={listId}`
- Renders `null` if no suggestions or if dismissed
- Renders up to 2 pills: "Quieres añadir tambien: [name]" + accept button + dismiss x
- Auto-hide after 30 seconds via `setTimeout`
- Accept fires the chip's `onAccept` which triggers `ListItemService::create` via parent component

### `DashboardPage.jsx` integration

Add at the top of the dashboard main content:
```jsx
<ReplenishmentBanner
    activeLists={lists.filter(l => l.status === 'active')}
    onAction={() => loadDashboard()}
/>
```

### `ListDetailPage.jsx` integration

Track the most recently created item. When a new item is created successfully via `handleAdd`, render a `ComplementaryChip` below that item for 30 seconds or until dismissed.

### `lib/replenishmentApi.js`

```js
export async function fetchReplenishmentSuggestions() { ... }
export async function acceptReplenishment(productoNombre, listId) { ... }
export async function ignoreReplenishment(productoNombre) { ... }
export async function silenceReplenishment(productoNombre) { ... }
```

### `lib/complementsApi.js`

```js
export async function fetchComplements(product, listId) { ... }
```

## Testing strategy

### Unit tests (backend)

- `ProductHistoryStatsServiceTest`: completed-lists count (zero, one, many), excludes non-completed, excludes other users, empty lists don't count
- `ReplenishmentSuggestionServiceTest`:
  - No active list → empty
  - Active list with <3 items → empty
  - Product below min_occurrences → excluded
  - Product with insufficient data for average → excluded
  - Ignores silenced products
  - Ignores dismissed products (within TTL)
  - Dismissed products past TTL reappear
  - Excludes products currently in any active list
  - Caps at 3
  - Sorts by urgency ratio
  - Factor of 0.8 gates correctly
  - Cache respects TTL
  - Cache invalidated on action
- `ComplementarySuggestionServiceTest`:
  - <5 completed lists → Claude fallback
  - Local co-occurrence above 60% → returned
  - Local co-occurrence below 60% → filtered out
  - Excludes products already in current list
  - Sorts by co_ratio desc, caps at 2
  - Claude error → empty result (no crash)
  - Budget cap → `ai_limit_reached: true`
  - Quota exhausted → `ai_limit_reached: true`
  - PII never leaves in Claude prompt payload (inspection test)
- `AiUsageTrackerTest` (updated):
  - `usedTodayForOperation` filters by operation
  - `usedTodayAcrossAllOperations` sums across
  - `canUse` uses the across-all version (regression test for Epic 5A)
- `ClaudeClientTest` (extended):
  - `suggestComplements` parses valid response
  - Missing API key throws
  - HTTP failure throws
  - Invalid JSON throws

### Feature tests (backend)

- `ReplenishmentControllerTest`:
  - `index` happy path with 1 suggestion
  - `index` empty when no active list with items
  - `index` respects auth
  - `accept` creates item and returns 201 (tests `ListItemService::create` integration)
  - `accept` 403 on other user's list
  - `accept` 422 on missing/invalid input
  - `ignore` creates dismiss row with 24h TTL
  - `silence` creates silenced row
  - Cross-user isolation on ignore/silence
- `ComplementControllerTest`:
  - Happy path with local data
  - Claude fallback path with <5 completed lists
  - Excludes already-present items
  - 403 on foreign list_id
  - 422 on missing params
  - Auth required
- Adjust existing `ProductSuggestionServiceTest::test_different_operations_have_independent_counters` to assert shared-quota behavior instead

### Frontend tests (vitest)

- `ReplenishmentBanner.test.jsx`: renders 0/1/3 suggestions, hides when empty, accept single list direct, accept multi-list opens modal, ignore removes card, silence removes card, error state
- `SelectListModal.test.jsx`: renders lists, selects, cancels, empty state (shouldn't render if no active lists)
- `ComplementaryChip.test.jsx`: renders after creation, hides on dismiss, auto-hides after 30s, accept fires callback, empty on no suggestions
- Update `DashboardPage.test.jsx` to mock `replenishmentApi` (following the Epic 5A pattern for `profileHistoryApi`)
- Update `ListDetailPage.test.jsx` to mock `complementsApi`

### 100% coverage target

All new code + the `AiUsageTracker` refactor. Path coverage: happy, failure, edge, security.

## Security

| Threat | Mitigation |
|--------|------------|
| Cross-user dismiss/silence spoofing | Every endpoint uses `auth('api')->user()`, no user_id accepted from input. Tests assert. |
| Cross-user replenishment leak | Every service method scopes by user. Tests assert. |
| Prompt injection via product name | `PromptSanitizer::clean` runs on every Claude input. Same protection as Epic 5A. |
| PII leaking to Claude in complement prompt | Prompt contains only the sanitized product string. No user_id, email, or list_id in payload. Anti-leak test inspects captured payload. |
| Quota bypass via a new operation | The shared quota check is at the `AiUsageTracker::canUse` level, which the services call before every Claude request. Refactor ensures all AI operations count toward the same daily limit. Test covers. |
| Abuse of accept endpoint to inflate items in others' lists | Ownership check on `list_id` via `ShoppingList::find + user_id` comparison. 403 on mismatch. Test covers. |
| Silenced list unbounded growth | User-scoped, cascade on user delete. ~40 bytes per row. Acceptable. |
| Dismissed list unbounded growth | Same as silenced plus natural expiry on read queries. Rows are not deleted eagerly — they just fail the `dismissed_until > NOW()` filter. A follow-up cleanup command can be added later (not in 5B). |
| Claude fallback hallucinating category/unit | Same defense as Epic 5A: the values are rendered as strings, and if the user accepts a suggestion the backend `CreateItemRequest` validates against enums at write time. |
| SQL injection | Eloquent/DB::table parameterized bindings throughout. No user input interpolated into SQL strings. |

## Performance

### Query optimization

- **Replenishment**: single aggregated query on `producto_historial` filtered by `user_id`, indexed. Three small exclusion queries in PHP. Total: 4-5 fast indexed reads.
- **Complement step 1**: single indexed query on `shopping_lists (user_id)`.
- **Complement step 2**: indexed on `(user_id, lista_id)` (new index). Inner `WHERE LOWER(nombre) = LOWER(?)` is a range scan over the user's rows — acceptable size.
- **`list_items` exclusion for complement**: single query `shopping_list_id=?` using existing index.

### Caching

| Cache | Key | TTL | Invalidation |
|-------|-----|-----|--------------|
| Replenishment suggestions | `replenishment:user:{id}` | 5 min | Explicit `invalidateCache` on accept/ignore/silence |

No caching on complement endpoint — each call is specific to a (product, list) pair and cached responses would likely miss.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Single-mega-SQL for replenishment | One round trip | Poor query plan, hard to test, hard to maintain | **Rejected** |
| Aggregate + PHP filter (chosen) | Each query is small and indexed, easy to test in isolation | 4-5 queries per call | **Selected** — cached 5min, cost negligible |
| Full CTE query for co-occurrence | Single round trip | Depends on MySQL 8 CTE; hard to read | **Rejected** |
| Two-step PHP query for co-occurrence (chosen) | Readable, testable, uses Epic 3 counters | 2 queries per call | **Selected** |
| Per-operation AI quota | Fine-grained control | Users don't understand why one feature is blocked while another works; complex to document | **Rejected** |
| Shared AI quota (chosen) | Simple, consistent user experience | One Epic 5A test needs update | **Selected** |
| Job-based pre-compute for replenishment | Fast dashboard load | New cron, new staleness concerns | **Rejected for 5B** — reserved for Epic 5C |
| Sync on-demand + 5 min cache (chosen) | Simple, reasonable latency, easy to invalidate | First hit per 5min is slower | **Selected** |
| Dismiss via sessionStorage | No DB writes | Lost across tabs/browsers | **Rejected** |
| Dismiss via DB with 24h TTL (chosen) | Persistent across devices | Table grows over time | **Selected** |
| Silence via DB permanent (chosen) | Matches the user intent ("never again") | Table grows slowly | **Selected** |
| Accept auto-opens `SelectListModal` even with 1 list | Consistent UX | One extra click for the common case | **Rejected** — single-list direct is better |
| Co-occurrence threshold 80% | Matches HU-504 example | Too strict for new users | **Rejected** — 60% set in Epic 5A config |
| Add `GET complements` response in the `POST items` response | Fewer HTTP calls | Couples AI to the add path, blocks on failures | **Rejected** — separate endpoint wins on reliability |
| Cache co-occurrence matrix per user | Fastest second call | Invalidation complex when user adds new items | **Rejected for 5B** |
| Include `source` field in complement response (chosen) | Frontend can distinguish history vs ai, show badges | One more field | **Selected** |
| Render complement chip inside `ItemRow` | Closest visual placement | Tight coupling with Epic 3 component | **Rejected** — render as sibling sibling below the freshly added item |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| `AiUsageTracker` refactor breaks Epic 5A in subtle ways | Medium | Low | One test needs update (documented). `canUse` signature unchanged. All Epic 5A tests run to confirm green. |
| Replenishment SQL slow on 10k+ history rows | Medium | Low | Indexed on `user_id`. Filtered + grouped. Cache 5 min. Monitor p99 in prod. |
| Co-occurrence query slow | Medium | Medium | New index `(user_id, lista_id)`. Step 1 uses counter shortcut, no join. Log latency. |
| Dismissed/silenced tables unbounded | Low | Low | ~40 bytes per row. Cascade on user delete. Optional cleanup command in future. |
| Claude fallback for complement fires on every item added by a new user | Medium | Medium | Shared 20/day quota hard-caps it. After hitting the cap, local-only for the rest of the day. Budget cap is the backstop. |
| User tries to accept a suggestion after the product was deleted from their active list between fetch and click | Low | Low | Add is still valid (product name is a string, not a reference). Works normally. |
| Frontend fires multiple complement calls rapidly (one per item added) | Low | Medium | Rate-limited by backend `throttle:60,1`. 60/min/user is generous but hard-capped. |
| `SelectListModal` shows an empty list because all active lists are filtered out | Low | Low | The modal only opens when >1 active lists exist; the filter is applied at fetch time in `ReplenishmentBanner`. |
| AI fallback for replenishment is called when it shouldn't be | Low | Low | Conditional: only when SQL returns <3 AND user has >=10 distinct products. Otherwise the banner shows fewer cards rather than fire a wasteful call. |
| Accept endpoint races with complement endpoint | Low | Low | Both are idempotent at the DB level. Accept creates an item; complement reads co-occurrence. No shared state. |

## Implementation Notes

### Suggested execution order for S4

1. Config key + migrations (`user_silenced_products`, `ai_dismissed_suggestions`, `historial_user_lista_idx`)
2. Models + factories
3. `ProductHistoryStatsService` with unit tests
4. `ReplenishmentSuggestionService` with unit tests (stub Cache, test every AC)
5. `AiUsageTracker` refactor + update the one breaking Epic 5A test
6. `ClaudeClientInterface::suggestComplements` + `ClaudeClient` impl + `FakeClaudeClient` + tests
7. `ComplementarySuggestionService` with unit tests (fake Claude)
8. FormRequests
9. Controllers + routes
10. Feature tests
11. Frontend: API clients, components, integration into `DashboardPage` and `ListDetailPage`, tests
12. Run full backend + frontend suites

### Critical invariants to assert

- Silenced product never appears in any subsequent replenishment fetch
- Dismissed product reappears after 24h
- Cache is invalidated before the next dashboard load after any action
- Shared quota: sum across operations never exceeds 20/day Free
- PII never appears in any Claude payload for either new call site
- Cross-user isolation on every new endpoint
- Accept path reuses `ListItemService::create` exactly (no parallel item creation code)

### Frontend work identified

Significant: new `ReplenishmentBanner`, `SelectListModal`, `ComplementaryChip`, API clients, two page integrations. **S5-UX review required**.

## Open Questions

None. All resolved at S1.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation (S4-BOTH)
- Required Artifacts: 01-scope.md, 02-prd.md, 03-technical-design.md
