# Technical Design — FEAT-EPIC6-GENERATION

## Architecture

`ListGenerationService` orchestrates quota checks → Claude call → silent retry → parse. Preview is ephemeral client-side state. Confirm creates or appends.

## Data Flow

```
Generate (POST /api/generate-list { description, people }):
  → ListGenerationService::generate(user, description, people)
    1. BudgetCap::canSpend(estimated_cost)  ← global monthly cap
    2. AiUsageTracker::canUse(user, shared_daily_quota)  ← shared pool
    3. AiUsageTracker::canUse(user, per_operation_5_day)  ← generation-specific
    4. CircuitBreaker::allow()
    5. PromptSanitizer::clean(description, maxChars=500)
    6. try:
         ClaudeClient::generateList(cleaned, people)
         → parse JSON: [{name, quantity, unit, category}] (max 25 items)
       catch ClaudeException:
         silent retry once
         → re-throw if second failure
    7. AiUsageTracker::record(user, tokens, cost)
  → return { products: [...], meta: { people, description_used } }

Preview phase (client-side only):
  React state holds the generated product list
  User can inline-edit: name, quantity, unit, category
  No server round-trip

Confirm as new list (POST /api/generate-list/confirm-new):
  → ShoppingListService::create() ← freemium check here
  → INSERT list_items for each product (parse enums, skip invalid, position counter)
  → syncCounters(list)

Confirm add to existing (POST /api/generate-list/confirm-existing { list_id }):
  → Authorize user owns list_id
  → INSERT list_items (append to existing)
  → syncCounters(list)
```

## Quota Stack

| Layer | Limit | Scope |
|-------|-------|-------|
| Global budget cap | $50/month | All users |
| Shared daily AI quota | 20 req/day (free) | Per user |
| Per-operation daily limit | 5/day (free) | Per user, generation only |
| Circuit breaker | 3 failures → 60s | Global |

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| Client-side preview | No server persistence = no tamper surface, simpler undo |
| Freemium check at confirm | Add-to-existing doesn't create a list (wouldn't hit limit) |
| Silent retry | User sees seamless UX; Claude transient failures are common |
| Enum validation at insert | Invalid AI output is skipped (not crash) |

## Gotchas

- Widest prompt injection surface in the project (500-char free text from user)
- React JSX auto-escapes Claude output → no stored XSS risk
- `catch-order` bug fixed during S4: `catch(ClaudeException)` must come before generic `catch`
- `people` field: Claude infers commercial quantities (e.g., "500g not 0.5kg")
