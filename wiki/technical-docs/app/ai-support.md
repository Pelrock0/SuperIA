# Technical Docs — AI Support Layer

**Keywords:** BudgetCap, CircuitBreaker, PromptSanitizer, ClaudeClient, AiUsageTracker, HistoryAnonymizer, guardrails

All classes in `app/Support/Ai/`. Established in Epic 5A; reused by all subsequent AI features.

## ClaudeClient

HTTP client (no Anthropic SDK dependency). Returns typed responses with token counts.

```php
interface ClaudeClientInterface {
    public function suggest(string $query, array $anonHistory): ClaudeResponse;
    public function generateList(string $description, int $people): ClaudeResponse;
    public function generateWeeklySummary(array $context): ClaudeResponse;
    public function estimatePrice(string $productName): ClaudeResponse;
}
```

`ClaudeResponse` contains: `content: string`, `input_tokens: int`, `output_tokens: int`, `model: string`

**FakeClaudeClient** captures the prompt payload — unit tests assert no PII reaches the client.

Default model: `claude-haiku-4-5-20251001` (set in `config/ai.php` and `.env AI_GENERATION_MODEL`).

## BudgetCap

DB-backed global monthly spend cap.

```php
BudgetCap::canSpend(float $estimatedCost): bool
BudgetCap::record(float $actualCost): void
```

- Cap: `config('ai.monthly_budget_cap')` (default $50 USD)
- Minor race condition: parallel calls can both pass before either records → accepts pennies overshoot

## AiUsageTracker

Per-user daily quota. Shared pool across ALL AI operations.

```php
AiUsageTracker::canUse(User $user, int $dailyQuota): bool
AiUsageTracker::record(User $user, string $operation, int $inputTokens, int $outputTokens, float $cost): void
```

- Quota: `user.ai_daily_limit_override ?? config('ai.daily_quota')` (default 20 free)
- Per-operation limits (e.g., generation: 5/day) tracked separately alongside shared quota
- Reset daily via `php artisan ai:reset-daily-usage`

## CircuitBreaker

Cache-backed (Redis/file driver). Prevents cascade failures to Claude API.

```php
CircuitBreaker::allow(): bool      // false if circuit open
CircuitBreaker::recordFailure(): void
CircuitBreaker::recordSuccess(): void
```

- Threshold: 3 failures → circuit opens
- Cooldown: 60s
- State stored in cache, not DB (cleared on cache flush)

## PromptSanitizer

Pattern-based (not semantic) injection prevention.

```php
PromptSanitizer::clean(string $input, int $maxChars = 200): string
```

- 13 regex patterns strip common injection attempts
- Enforces character limit (200 for autocomplete, 500 for generation)
- Strips control characters, excessive whitespace
- Does NOT catch novel/semantic injection (accepted limitation)

## HistoryAnonymizer

Extracts product names only — strips all user PII before Claude prompt.

```php
HistoryAnonymizer::topProducts(User $user, int $limit = 20): string[]
// Returns ['Leche entera', 'Pan de molde', ...] — no user_id, no prices, no dates
```

Unit test asserts that serialized output contains no user identifiers.

## Prompt Security Chain

```
User input → PromptSanitizer → ClaudeClient::send()
User history → HistoryAnonymizer → (included in prompt as string[] only)
Claude response → strict JSON parsing → enum validation at write time
```

React JSX auto-escapes Claude output → no stored XSS risk in frontend.
