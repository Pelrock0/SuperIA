# Technical Design: FEAT-EPIC5A-AUTOCOMPLETE

## Overview

A three-layer autocomplete pipeline orchestrated by a single service, backed by a reusable Claude API foundation. Layer 1 queries `producto_historial` via a new FULLTEXT index, weighted by recency × frequency. Layer 2 queries a new `producto_catalogo` table seeded from a Claude-generated JSON of ~2500 Spanish products. Layer 3 is a deferred background call to Claude guarded by two budget caps (per-user daily + project monthly), a circuit breaker, and a sanitizer. The pipeline is synchronous for layers 1+2 (<50ms) and opt-in for layer 3 (`?include_ai=1` sent by the frontend after a 2-second debounce). A parallel surface adds "view and clear history" to the profile page, reusing the same weighted ranking so users see the exact same ordering the suggestions use.

The feature is also the **first Claude API integration in the codebase**, so the design spends disproportionate attention on contracts (`app/Support/Ai/*`) that Epic 5B, 5C, and 6 will consume without modification. Every safety net — budget cap, rate limiter, sanitizer, circuit breaker, anonymization boundary — is factored into its own class so future AI features plug into them via constructor injection.

Profile-side work is modest: three new endpoints plus a React section, sharing the same `ProductHistoryWeightingService` used by the suggestion pipeline. Consistency is free.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | Enums for AI operation and plan, DTOs for suggestion result and rank | `App\Enums\AiOperation`, `App\Enums\AiPlan`, `App\Support\Ai\Dto\Suggestion`, `App\Support\Ai\Dto\RankedProduct` |
| Services | Three-layer orchestration, history ranking with recency weighting, history cleanup, profile history listing | `App\Services\ProductSuggestionService`, `App\Services\ProductHistoryWeightingService`, `App\Services\ProductHistoryCleanupService` |
| AI Support (new namespace) | Claude SDK wrapper, circuit breaker, budget cap check, prompt sanitization, usage tracking, anonymization | `App\Support\Ai\ClaudeClient`, `App\Support\Ai\CircuitBreaker`, `App\Support\Ai\BudgetCap`, `App\Support\Ai\PromptSanitizer`, `App\Support\Ai\AiUsageTracker`, `App\Support\Ai\HistoryAnonymizer` |
| Infrastructure | Catalog seeding, FULLTEXT index, scheduled command, alert mailable | `Database\Seeders\ProductoCatalogoSeeder`, new migrations, `App\Console\Commands\ResetAiDailyUsage`, `App\Mail\BudgetCapExceededAlert` |
| Controllers/API | Thin HTTP layer for suggestions + profile history | `App\Http\Controllers\ProductSuggestionController`, `App\Http\Controllers\Auth\ProfileController` (extended) |
| Frontend | Autocomplete dropdown, profile history section, API clients | `components/items/ItemAutocomplete.jsx`, `components/items/AddItemInput.jsx` (refactored), `pages/ProfilePage.jsx` (new section), `components/profile/HistoryList.jsx`, `components/profile/ConfirmClearHistoryModal.jsx`, `lib/suggestionsApi.js`, `lib/profileHistoryApi.js` |

### Data Flow

#### Suggestion request (Layer 1 + Layer 2)
```
1. GET /api/suggestions?q=le
2. SuggestionQueryRequest validates q (min 2 chars, max 60) and include_ai (optional bool)
3. ProductSuggestionController::index delegates to ProductSuggestionService::suggest(user, q, includeAi=false)
4. ProductSuggestionService {
     $layer1 = ProductHistoryWeightingService::search(user, q, limit=5)  // FULLTEXT
     $layer2 = ProductoCatalogo::fullText(q)->limit(5)->get()              // catalog
     $merged = dedupByNameCaseInsensitive([...$layer1, ...$layer2], maxTotal=5)
     return new SuggestionResponse($merged, aiFallbackUsed=false, aiLimitReached=false)
   }
5. Controller returns 200 with JSON {suggestions, ai_fallback_used, ai_limit_reached}
```

#### Suggestion request with AI fallback (Layer 3)
```
1. GET /api/suggestions?q=xyz&include_ai=1   (fired 2s after typing pause if <3 local results)
2. Controller → service with includeAi=true
3. Service runs layers 1+2 first (always). If combined count ≥3, skip Claude.
4. If <3, enter AI fallback:
   a. BudgetCap::canSpend() → if false, return early with ai_limit_reached=true
   b. AiUsageTracker::canUse(user, AiOperation::Suggestion) → if false (20/day), same
   c. CircuitBreaker::allow('claude') → if open, same
   d. PromptSanitizer::clean(q)  → strip injection, truncate
   e. HistoryAnonymizer::topProducts(user, limit=20) → just product name strings, no IDs
   f. ClaudeClient::suggest($cleanQ, $anonymizedContext) → returns array of Suggestion DTOs
   g. On success: AiUsageTracker::record(user, Suggestion, status=success, estimatedCostUsd)
   h. On error: CircuitBreaker::fail(), AiUsageTracker::record(..., status=error), return local-only
5. Merge layer 3 results into layers 1+2, dedup by name, cap at 5 total, return with ai_fallback_used=true
```

#### Profile: view history
```
1. GET /api/profile/history?page=1
2. ProfileController::history() → ProductHistoryWeightingService::rankedList(user, paginated)
3. Service returns pairs (product_name, total_count, last_purchased_at, weighted_score), ordered by score desc
4. Controller returns JSON
```

#### Profile: clear all history
```
1. DELETE /api/profile/history
2. ProfileController::clearHistory() → ProductHistoryCleanupService::clearAll(user)
3. Service deletes all producto_historial rows where user_id = auth id
4. Controller returns 204
```

#### Profile: forget one product
```
1. DELETE /api/profile/history/{producto}
2. Controller validates {producto} is not empty, URL-decoded
3. ProductHistoryCleanupService::forget(user, producto)
4. Deletes rows WHERE user_id = ? AND producto_nombre = ?
5. Controller returns 204
```

#### Daily usage reset
```
1. Scheduled at 00:00 Europe/Madrid via app/Console/Kernel.php → schedule('ai:reset-daily-usage')
2. Command only needs to announce the boundary; the tracker queries "today in Madrid" dynamically (no stored state to reset).
   Equivalent command verifies consistency and optionally soft-deletes rows older than 90 days from ai_usage_log.
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| Layer 3 call + usage record | Single DB transaction wrapping ClaudeClient call + AiUsageTracker::record | Guarantees usage counted even if response not consumed |
| Clear all history | Single delete statement (no explicit transaction needed) | Single query, atomic by default |
| Forget one product | Same — atomic single delete | |
| Catalog seeding | Single transaction wrapping 2500 inserts | Seed is idempotent, rollback on failure |

### Three-layer orchestration decision

Layer 1 and Layer 2 always run on every `/api/suggestions` request. Layer 3 runs only when the frontend passes `?include_ai=1` **and** layers 1+2 returned <3 results. This keeps the fast path truly fast — no extra logic to bypass layer 3, no reads to the usage log when layer 3 isn't needed.

## Data Model

### New Table: `producto_catalogo`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | |
| `nombre` | string(80) | NOT NULL | Product name (Spanish) |
| `categoria` | enum | NULLABLE: 10 product categories (same as ListItem) | Typical category |
| `unidad_tipica` | enum | NULLABLE: kg, g, L, ml, ud, pack | Most common unit for this product |
| `cantidad_tipica` | decimal(8,2) | NULLABLE | Most common quantity |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

Indexes:
- `FULLTEXT(nombre)` — primary search index
- `(categoria)` — secondary filter

### New Table: `ai_usage_log`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | |
| `user_id` | foreignId | FK users.id, CASCADE, nullable | Null for system-level events (budget alerts) |
| `operation` | enum | `suggestion`, `generation`, `summary`, `complement`, `replenishment` | Which AI feature |
| `status` | enum | `success`, `error`, `budget_capped`, `user_capped`, `circuit_open` | Outcome |
| `date` | date | NOT NULL | Madrid date (for daily reset math) |
| `estimated_cost_usd` | decimal(8,4) | NOT NULL default 0 | Tracked for budget cap. Computed from token count |
| `created_at` | timestamp | NOT NULL | Exact moment of call |

Indexes:
- `(user_id, date, operation)` — per-user daily counter queries
- `(date)` — monthly aggregate for budget cap
- `(operation, status)` — analytics

### Migration: `ALTER TABLE producto_historial ADD FULLTEXT(producto_nombre)`

Required for Layer 1 <20ms SLA. Single ALTER in a standalone migration so the change is reversible.

```php
Schema::table('producto_historial', function (Blueprint $table) {
    $table->fullText('producto_nombre', 'producto_historial_nombre_fulltext');
});
```

Down: drop the index.

### Catalog JSON file

`storage/app/seeds/catalogo-productos.json` — generated **once** in this sprint by running Claude with a carefully written prompt (tracked in `docs/features/FEAT-EPIC5A-AUTOCOMPLETE/catalog-prompt.md`). Manual review strips brand names and obvious errors before committing. Structure:

```json
[
  {"nombre": "Leche entera", "categoria": "lacteos_huevos", "unidad_tipica": "L", "cantidad_tipica": 1},
  {"nombre": "Pan de barra", "categoria": "panaderia", "unidad_tipica": "ud", "cantidad_tipica": 1},
  ...
]
```

Seeder reads the file and upserts to `producto_catalogo`. Idempotent via `upsert` on `nombre`.

### Enums

```php
// App\Enums\AiOperation
enum AiOperation: string {
    case Suggestion = 'suggestion';
    case Generation = 'generation';
    case Summary = 'summary';
    case Complement = 'complement';
    case Replenishment = 'replenishment';
}

// App\Enums\AiPlan
enum AiPlan: string {
    case Free = 'free';
    case Premium = 'premium';

    public function dailySuggestionQuota(): ?int
    {
        return match ($this) {
            self::Free => config('ai.rate_limits.free.suggestions_per_day'),
            self::Premium => null, // unlimited
        };
    }
}
```

### Config: `config/ai.php`

```php
return [
    'provider' => env('AI_PROVIDER', 'claude'),
    'api_key' => env('CLAUDE_API_KEY'),
    'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
    'timeout_seconds' => env('AI_TIMEOUT', 30),
    'budget_cap_monthly_usd' => env('AI_BUDGET_CAP_MONTHLY_USD', 50),
    'admin_alert_email' => env('AI_ADMIN_ALERT_EMAIL'),
    'rate_limits' => [
        'free' => [
            'suggestions_per_day' => 20,
        ],
    ],
    'thresholds' => [
        'min_occurrences' => 3,          // HU-503 (Epic 5B)
        'min_completed_lists' => 5,      // HU-504 (Epic 5B)
        'co_occurrence_ratio' => 0.60,   // HU-504 (Epic 5B)
    ],
    'circuit_breaker' => [
        'failure_threshold' => 3,
        'cool_down_seconds' => 60,
    ],
    'prompt' => [
        'max_user_input_chars' => 200,
        'max_history_items_in_context' => 20,
    ],
    'timezone' => 'Europe/Madrid',
];
```

### API Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/suggestions` | GET | JWT | Three-layer suggestion. Query params: `q` (required), `include_ai` (optional, default 0) |
| `/api/profile/history` | GET | JWT | Ranked list of user's history |
| `/api/profile/history` | DELETE | JWT | Clear all history for authed user |
| `/api/profile/history/{producto}` | DELETE | JWT | Forget one product by name |

All JWT-protected. Suggestion endpoint has `throttle:60,1` (60 req/min per user) on top of the AI-specific daily cap.

### API Response format

```json
// GET /api/suggestions?q=le
{
  "data": {
    "suggestions": [
      {
        "source": "history",
        "name": "Leche entera",
        "quantity": 1,
        "unit": "L",
        "category": "lacteos_huevos"
      },
      {
        "source": "catalog",
        "name": "Lentejas",
        "quantity": 500,
        "unit": "g",
        "category": "conservas"
      }
    ],
    "ai_fallback_used": false,
    "ai_limit_reached": false
  }
}

// GET /api/suggestions?q=xyz&include_ai=1 when quota reached
{
  "data": {
    "suggestions": [/* layer 1+2 only */],
    "ai_fallback_used": false,
    "ai_limit_reached": true
  }
}

// GET /api/profile/history
{
  "data": {
    "items": [
      {
        "producto_nombre": "Leche entera",
        "total_count": 12,
        "last_purchased_at": "2026-04-09T18:32:00+00:00",
        "weighted_score": 8.4
      }
    ],
    "pagination": {"page": 1, "per_page": 20, "total": 45}
  }
}
```

## Recency-weighted ranking algorithm

Single SQL query, used by both Layer 1 of the suggestions pipeline and the profile history list.

```sql
SELECT
    producto_nombre,
    COUNT(*) AS total_count,
    MAX(fecha_compra) AS last_purchased_at,
    MAX(categoria) AS typical_category,
    MAX(unidad) AS typical_unit,
    MAX(cantidad) AS typical_quantity,
    SUM(
        CASE
            WHEN fecha_compra >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 2.0
            WHEN fecha_compra >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1.0
            ELSE 0.3
        END
    ) AS weighted_score
FROM producto_historial
WHERE user_id = ?
  [AND MATCH(producto_nombre) AGAINST(? IN BOOLEAN MODE)]   -- only for layer 1
GROUP BY producto_nombre
ORDER BY weighted_score DESC, last_purchased_at DESC
LIMIT ?
```

The CASE expression gives recent purchases 2× weight, last-3-months 1×, older 0.3×. Simple, explainable, tunable. No ML.

**Why `MAX` for category/unit/quantity**: these are usually stable per product name. If they drift (user logs different quantities), we pick the most recent non-null via a correlated subquery only if needed. Simpler first, tune later.

## Claude client contract

```php
namespace App\Support\Ai;

interface ClaudeClientInterface
{
    /** @return Suggestion[] */
    public function suggest(string $userQuery, array $anonymizedContext): array;
}
```

Implementation `ClaudeClient` wraps the SDK (or raw HTTP if the SDK is unavailable):
- Reads `config('ai.api_key')`, `config('ai.model')`, `config('ai.timeout_seconds')`
- Injects a hardcoded system prompt: _"You suggest Spanish supermarket products. Return a strict JSON array of up to 5 objects with keys name, unit, category, quantity. No prose."_
- User turn contains: the sanitized query + a compact list of the user's top 20 anonymized product names.
- Parses JSON strictly. Invalid JSON → throw `ClaudeInvalidResponseException` → circuit breaker records failure.
- Estimates cost from token count (`input_tokens × price_in + output_tokens × price_out`) returned by the SDK; fallback estimate if SDK doesn't expose it.

For tests: `FakeClaudeClient` implements the interface and is bound via container in `Tests\TestCase::setUp`.

**SDK choice**: try `anthropic/anthropic-sdk-php` first. If not on Packagist at the time of implementation, fall back to `Illuminate\Http\Client\Factory` (Laravel HTTP client) calling the Anthropic Messages API directly. Either option satisfies the contract above. Decision finalized at S4 start, after a quick composer search.

## Budget cap

```php
class BudgetCap
{
    public function canSpend(): bool
    {
        $limit = (float) config('ai.budget_cap_monthly_usd');
        $spent = $this->currentMonthSpendUsd();
        return $spent < $limit;
    }

    public function currentMonthSpendUsd(): float
    {
        $start = now('Europe/Madrid')->startOfMonth();
        $end = now('Europe/Madrid')->endOfMonth();
        return (float) AiUsageLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'success')
            ->sum('estimated_cost_usd');
    }

    public function notifyIfExceeded(): void
    {
        if ($this->canSpend()) return;
        $dedupKey = 'ai:budget_alert:'.now('Europe/Madrid')->toDateString();
        Cache::remember($dedupKey, now()->endOfDay(), function () {
            Mail::to(config('ai.admin_alert_email'))->queue(new BudgetCapExceededAlert($this->currentMonthSpendUsd()));
            return true;
        });
    }
}
```

Simple, DB-backed, slow only on the first call of the day. Can be upgraded to a cached counter + ledger table later without breaking the interface.

## Circuit breaker

```php
class CircuitBreaker
{
    public function __construct(
        private string $service,
        private int $threshold,
        private int $cooldownSeconds,
    ) {}

    public function allow(): bool
    {
        $state = Cache::get($this->key('state'), 'closed');
        if ($state === 'closed') return true;
        $openedAt = Cache::get($this->key('opened_at'));
        if ($openedAt && now()->diffInSeconds($openedAt) >= $this->cooldownSeconds) {
            $this->reset();
            return true;
        }
        return false;
    }

    public function fail(): void
    {
        $count = (int) Cache::increment($this->key('failures'));
        if ($count >= $this->threshold) {
            Cache::put($this->key('state'), 'open', now()->addSeconds($this->cooldownSeconds));
            Cache::put($this->key('opened_at'), now(), now()->addSeconds($this->cooldownSeconds));
        }
    }

    private function reset(): void { /* clear keys */ }
    private function key(string $s): string { return "ai:cb:{$this->service}:{$s}"; }
}
```

Cache-backed (Redis or file driver in tests). Simple, serves the need.

## Prompt sanitization

```php
class PromptSanitizer
{
    private const INJECTION_PATTERNS = [
        '/ignore (all )?previous instructions/i',
        '/you are a (new|different|helpful) (assistant|model)/i',
        '/system prompt/i',
        '/<\|.*?\|>/',  // special tokens
        '/```(system|prompt)/i',
    ];

    public function clean(string $input): string
    {
        $cleaned = $input;
        foreach (self::INJECTION_PATTERNS as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }
        $cleaned = trim($cleaned);
        return mb_substr($cleaned, 0, (int) config('ai.prompt.max_user_input_chars'));
    }
}
```

Not bulletproof but raises the bar. Unit tests cover known patterns and edge cases (unicode, empty string, over-length).

## Anonymization

```php
class HistoryAnonymizer
{
    public function topProducts(User $user, int $limit): array
    {
        return ProductoHistorial::query()
            ->where('user_id', $user->id)
            ->groupBy('producto_nombre')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('producto_nombre')
            ->all();
    }
}
```

Returns only an array of strings. No IDs, no timestamps, no list references. A unit test asserts the returned array contains only strings.

## AI usage tracker

```php
class AiUsageTracker
{
    public function canUse(User $user, AiOperation $op): bool
    {
        $plan = AiPlan::from($user->plan ?? 'free');
        $quota = $plan->dailySuggestionQuota();
        if ($quota === null) return true;
        $used = $this->usedToday($user, $op);
        return $used < $quota;
    }

    public function usedToday(User $user, AiOperation $op): int
    {
        return AiUsageLog::query()
            ->where('user_id', $user->id)
            ->where('operation', $op->value)
            ->whereDate('date', now('Europe/Madrid')->toDateString())
            ->where('status', 'success')
            ->count();
    }

    public function record(User $user, AiOperation $op, string $status, float $costUsd = 0): void
    {
        AiUsageLog::create([
            'user_id' => $user->id,
            'operation' => $op->value,
            'status' => $status,
            'date' => now('Europe/Madrid')->toDateString(),
            'estimated_cost_usd' => $costUsd,
        ]);
    }
}
```

`User::$plan` column doesn't exist yet (all users are Free). The tracker defaults to `'free'` when null, so adding the column later is trivial.

## Frontend architecture

### `ItemAutocomplete` component

- Props: `value`, `onSelect(suggestion)`, `onChange(text)`
- State: `suggestions`, `loading`, `aiLimitReached`, `activeIndex`, `latestQueryId`
- Debounce: 150ms for fast path (layers 1+2), 2s for AI fallback (layers 3)
- Two separate effects:
  - On text change >=2 chars → debounce 150ms → fetch `/api/suggestions?q=...` (no include_ai)
  - On same change → set 2s timer. If it fires and the current result count <3, fetch again with `?include_ai=1` and merge
- Ignore responses whose query id is stale (`latestQueryId` check)
- Keyboard: ArrowDown/Up moves `activeIndex`, Enter calls `onSelect(suggestions[activeIndex])`, Escape clears `suggestions`
- ARIA: `role="combobox"` on input, `role="listbox"` on dropdown, `aria-activedescendant` for the highlighted item

### `AddItemInput` refactor

- Wraps `ItemAutocomplete` inside the existing form
- On `onSelect`, pre-fills the form state (name, quantity, unit, category) but does not auto-submit
- Existing submit button still required

### Profile page new section

- `components/profile/HistoryList.jsx`: paginated list of `{name, count, last_purchased_at, weighted_score}`. Per-row "Olvidar" button.
- `components/profile/ConfirmClearHistoryModal.jsx`: reuses modal overlay pattern from other features. One Spanish label: "Se eliminara tu historial completo. Esta accion no se puede deshacer."
- Added as a new section in `pages/ProfilePage.jsx` below the existing profile fields.

### API clients

- `lib/suggestionsApi.js`: `fetchSuggestions(q, includeAi)` → returns `{suggestions, ai_fallback_used, ai_limit_reached}`
- `lib/profileHistoryApi.js`: `fetchHistory(page)`, `clearHistory()`, `forgetProduct(name)`

## Testing strategy

### Unit tests

- `PromptSanitizerTest`: clean normal, clean injection patterns, clean unicode, truncation, empty
- `BudgetCapTest`: canSpend below/at/above limit, currentMonthSpendUsd sums only success rows, notifyIfExceeded dedup by day
- `CircuitBreakerTest`: closed → open after N failures, open blocks, cool-down opens again, reset on success
- `AiUsageTrackerTest`: canUse at 0/19/20 for Free, unlimited for Premium, record inserts row, usedToday respects Madrid date
- `HistoryAnonymizerTest`: returns strings only, respects limit, excludes other users' history, asserts no PII keys in payload
- `ProductHistoryWeightingServiceTest`: weight by recency (recent > old despite lower count), search filter works, returns DTOs
- `ProductSuggestionServiceTest`: layers 1+2 run always, layer 3 only with include_ai+scarce, dedup cross-layer, all caps respected
- `ProductHistoryCleanupServiceTest`: clearAll scopes to user, forget scopes to user + product name
- `FakeClaudeClientTest`: honors the interface, can return canned suggestions, can throw

### Feature tests

- `ProductSuggestionControllerTest`: happy path layer 1+2, happy path layer 3, validation (missing q, short q, long q), 401 unauth, rate limit 429 after 60/min, ai_limit_reached after 20 Claude calls, ai_limit_reached when BudgetCap exceeded, circuit breaker open hides AI, cross-user isolation (user A cannot see user B history via suggestion)
- `ProfileHistoryControllerTest`: list happy, list empty, clearAll happy + scopes to user, forget one happy + scopes, 401 unauth
- `AiResetDailyUsageCommandTest`: runs green, does not error on empty log, logs processed count
- `PromptPiiAntiLeakTest`: trigger a suggestion that calls the fake client, inspect the captured prompt payload, assert it does not contain email, user_id, list id, or any database identifier
- `BudgetCapMailTest`: when cap is exceeded, a mail is queued (`Mail::fake` + `assertQueued`), only once per day
- `FullTextIndexPerformanceTest`: seeds 10k rows, runs layer 1 query, asserts time under 50 ms (safety margin over the 20 ms target)
- `ProductoCatalogoSeederTest`: runs the seeder, asserts row count between 2000 and 3000, idempotency on re-run

### Frontend tests

- `ItemAutocomplete.test.jsx`: renders suggestions, debounces on typing, merges layer 3 results after 2s, hides on empty, keyboard navigation, out-of-order responses discarded, aria attributes, quota-reached footer hint
- `AddItemInput.test.jsx`: selects suggestion and pre-fills form, no auto-submit
- `HistoryList.test.jsx`: renders paginated list, "Olvidar" triggers confirm and API call
- `ConfirmClearHistoryModal.test.jsx`: cancel, confirm, escape-to-close gap (documented consistent with project pattern)
- `ProfilePage.test.jsx`: extended with new section loading, empty, populated, clear

### Coverage target

100% across new code. Existing Epic 3 tests for `ListItemService` and `AddItemInput` must stay green — verified by running the full suite at the end of S4.

## Security

| Threat | Mitigation |
|--------|------------|
| Runaway Claude bill | `BudgetCap` hard stop + daily alert email, deduped per day |
| Prompt injection via user input | `PromptSanitizer` strips known patterns, truncates to 200 chars. Hardcoded system prompt keeps user text strictly in user-role messages |
| PII leakage to Claude | `HistoryAnonymizer` returns only product name strings. Unit test asserts no PII keys in captured payload. System prompt never mentions the user |
| Abuse of Free quota | Per-user daily counter in `ai_usage_log`. Budget cap is the ultimate backstop |
| Circuit breaker masks outage | Every open event logged at warning level. Sustained open >5 min → operational alert (follow-up, not blocking for 5A) |
| Cross-user history leak | Every query in `ProductHistoryWeightingService`, cleanup service, and suggestion service scopes by `auth('api')->id()`. Unit tests assert user isolation |
| History cleanup spoofing | `DELETE /api/profile/history` takes no user_id param. Scoped by authed user. Tests assert |
| Catalog poisoning | Catalog is seeded from a committed JSON reviewed manually. No runtime insertion by users |
| Claude response injection back into app | JSON-only responses, parsed strictly. Any non-JSON triggers failure and circuit breaker. Results are treated as untrusted strings, not as authority for actions |
| Rate limit evasion (frontend debounce bypass) | Backend throttle `60,1` per user as defense in depth |
| `CLAUDE_API_KEY` leak | Only read in `ClaudeClient`, never passed to the frontend. Stack config explicitly states `never_expose_to_frontend: true` |

## Performance

### Query optimization

- **Layer 1**: FULLTEXT on `producto_historial.producto_nombre`, filtered by `user_id` (existing index). `(user_id, fecha_compra)` index already exists from Epic 3 — used by the recency case expression
- **Layer 2**: FULLTEXT on `producto_catalogo.nombre`, small table (~2500 rows), warmed in buffer pool after first query
- **Catalog cache**: since it's ~2500 rows and immutable, the entire table can optionally be wrapped in `Cache::rememberForever` per query prefix. Not done in V1 — DB is fast enough. Flag for follow-up if p99 regresses
- **Usage tracker `usedToday`**: composite index `(user_id, date, operation)` makes this O(1)
- **Budget cap `currentMonthSpendUsd`**: indexed on `date`, sums ~dozens to hundreds of rows at worst. Acceptable at initial scale. Follow-up optimization: denormalized `ai_budget_ledger` with one row per month

### Caching strategy

| Cache | Key | TTL | Invalidation |
|-------|-----|-----|--------------|
| BudgetCap daily alert dedup | `ai:budget_alert:{date}` | until end of day | Expires naturally |
| Circuit breaker state | `ai:cb:claude:{state,failures,opened_at}` | cool_down_seconds | Manual on success |

No query caching in V1. Catalog caching deferred until real measurements justify it.

### Frontend performance

- 150 ms debounce on typing (fast path) prevents keystroke-spam
- 2 s debounce for AI fallback (slow path)
- `latestQueryId` discards stale responses so the dropdown never flashes

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Three-layer orchestration in a single service (chosen) | One place for the full pipeline, easy to test | Service is slightly fat | **Selected**: centralization over distribution |
| Three separate services chained by controller | Smaller classes | Harder to reason about ordering and dedup | Rejected |
| Claude SDK `anthropic/anthropic-sdk-php` | Official, structured | May not exist on Packagist at time of writing | **Preferred**, with HTTP fallback |
| Direct HTTP client | No dependency risk | Manual SDK work | Fallback plan |
| FULLTEXT index for layer 1 (chosen) | Fast, native MySQL, simple | Requires MySQL 5.6+ with InnoDB FULLTEXT | **Selected**: matches project stack |
| Meilisearch / Typesense | Better ranking, typo tolerance | New infra dependency | Rejected — overkill for 2500 catalog + personal history |
| Redis token bucket for rate limit | Fast | Loses state across deploys without persistence setup | Rejected |
| DB-backed `ai_usage_log` for rate limit (chosen) | Persists deploy, auditable, analytics-friendly | Slightly slower than Redis | **Selected**: correctness + audit trail win |
| Real-time cost tracking from SDK token count (chosen) | Accurate budget cap | SDK-dependent | **Selected** with HTTP fallback estimation |
| Fixed $0.01 cost per call | Simple | Wrong as Claude pricing changes | Rejected |
| Seed catalog from JSON file (chosen) | Reproducible, reviewable, version-controlled | ~100 KB in repo | **Selected** |
| Seed catalog from API call on migrate | Fresh | Non-reproducible, depends on API availability during deploys | Rejected |
| Weighted ranking in SQL (chosen) | Single query, fast | Less flexible than app-layer | **Selected**: matches SLA |
| Weighted ranking in PHP | Flexible, easy to unit test | Requires fetching all rows | Rejected for SLA |
| PromptSanitizer in ClaudeClient | Less code | Coupling | Rejected |
| PromptSanitizer as standalone class (chosen) | Reusable across future AI features, testable in isolation | One more class | **Selected** |
| Madrid timezone hardcoded in config (chosen) | Explicit | Not ready for multi-region | **Selected** for MVP |
| Per-user timezone | Respects users abroad | Out of MVP scope | Rejected |
| Auto-submit on suggestion pick | Fewer clicks | Can't adjust quantity before saving | Rejected |
| Manual "Añadir" after pick (chosen) | User control | One extra click | **Selected**: consistent with Epic 3 |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| `anthropic/anthropic-sdk-php` not on Packagist | Medium | Medium | HTTP fallback via Laravel HTTP client. Decision finalized at S4 start |
| Catalog seed file causes a huge commit | Low | Low | ~100 KB compressed. Acceptable |
| FULLTEXT index adds write overhead | Low | Low | `producto_historial` writes are low frequency (per check-item) |
| Budget cap query becomes slow at scale | Medium | Low | Index on `date`. Denormalized ledger table as follow-up |
| Circuit breaker never closes | Medium | Low | Cool-down auto-reset. Logged at warning |
| Layer 3 merge logic incorrectly dedupes | Medium | Medium | Explicit test cases for cross-layer dedup, case-insensitive match |
| Madrid DST transition breaks reset | Medium | Low | Carbon handles DST. Test covers DST Sunday dates |
| History cleanup deletes too much | High | Low | Scoped by user. Confirm modal. No undo per design |
| Frontend race conditions on fast typing | Low | Medium | `latestQueryId` guard |
| Claude returns non-JSON sometimes | Medium | Medium | Strict JSON parsing, fail loudly to circuit breaker |
| Prompt injection bypasses sanitizer | Low | Medium | System prompt hardening + tests for known vectors. Not bulletproof — acceptable for Free-tier suggestions |
| Seeder fails mid-way leaving partial catalog | Medium | Low | Wrapped in transaction, `upsert` idempotent, safe re-run |

## Implementation Notes

### Suggested execution order for S4

1. Install `anthropic/anthropic-sdk-php` (or confirm HTTP fallback)
2. Add `config/ai.php`, `.env.example` entries
3. Create migrations: `producto_catalogo`, `ai_usage_log`, FULLTEXT on `producto_historial`
4. Create enums and DTOs
5. Build `app/Support/Ai/*` (sanitizer, circuit breaker, budget cap, anonymizer, tracker, claude client + fake client) with unit tests
6. Build `ProductHistoryWeightingService` with unit tests
7. Build `ProductHistoryCleanupService` with unit tests
8. Build `ProductSuggestionService` with unit tests
9. Generate catalog JSON using Claude with the prompt in `docs/features/FEAT-EPIC5A-AUTOCOMPLETE/catalog-prompt.md`, review manually, commit, build seeder + seeder test
10. Controllers + routes + FormRequests + feature tests (including PII anti-leak test)
11. `BudgetCapExceededAlert` mailable + test
12. `ResetAiDailyUsage` command + test + schedule in `routes/console.php`
13. Frontend `suggestionsApi` + `profileHistoryApi`
14. `ItemAutocomplete` component + tests
15. Refactor `AddItemInput` to use `ItemAutocomplete` + update existing Epic 3 tests
16. `HistoryList`, `ConfirmClearHistoryModal`, new `ProfilePage` section + tests
17. Full backend suite, full frontend suite, manual smoke via dev server (optional, out of Claude Code)
18. Write `04-implementation-notes.md`

### Critical invariants to assert in tests

- Anonymization: no PII leaves the system in any Claude call
- Budget cap blocks before Claude call, not after
- Daily cap is Madrid-scoped, not UTC
- Layer 1+2 never depend on Layer 3 availability
- Suggestion selection pre-fills form but never auto-submits
- History cleanup is always user-scoped

### Frontend work identified

Significant: new `ItemAutocomplete`, refactor of `AddItemInput`, new profile section, new modal. **S5-UX review required**, covering: autocomplete dropdown states (loading, results, ai fallback, empty, limit hint), keyboard navigation, profile history list, clear confirmation, forget one flow.

## Open Questions

None. All resolved at S1.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation (S4-BOTH)
- Required Artifacts for Next Step: 01-scope.md, 02-prd.md, 03-technical-design.md
