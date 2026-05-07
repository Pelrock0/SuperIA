# Technical Docs — AI List Generation

**Keywords:** generate, AI, description, preview, confirm, retry, GPT, Claude

## Overview

`ListGenerationService` takes a free-text description and generates a structured shopping list via Claude. Preview is client-side only (ephemeral); user confirms to persist.

## Endpoint

```
POST /api/generate-list { description: string(max 500), people: int(default 2) }
→ { products: [{name, quantity, unit, category}], meta: { people, description_used } }
```

## Generation Flow

```
1. BudgetCap::canSpend(estimated_cost)       ← global monthly cap
2. AiUsageTracker::canUse(user, shared_20)   ← shared daily quota
3. AiUsageTracker::canUse(user, per_op_5)    ← generation-specific 5/day
4. CircuitBreaker::allow()
5. PromptSanitizer::clean(description, 500)
6. try: ClaudeClient::generateList(cleaned, people)
   catch: silent retry once
   → throw if second failure also fails
7. Parse JSON: [{name, quantity, unit, category}], max 25 items
8. AiUsageTracker::record(user, tokens, cost)
```

## Preview Phase

The generated list is held in **React state only** — no server round-trip. User can:
- Edit any field inline (name, quantity, unit, category)
- Reorder (if implemented)
- Remove items

No server state until confirm.

## Confirm Flows

```
Confirm as new list:
  POST /api/generate-list/confirm-new { name, items[] }
  → ShoppingListService::create() ← freemium check here
  → INSERT list_items for each item (parse enums, skip invalid, position counter)
  → syncCounters(list)
  → Return new list

Confirm add to existing:
  POST /api/generate-list/confirm-existing { list_id, items[] }
  → Authorize user owns list_id
  → INSERT list_items (append)
  → syncCounters(list)
```

Note: Freemium check is at **confirm** time, not before generation — add-to-existing doesn't create a list.

## Prompt Security

- Input: 500-char limit enforced by `PromptSanitizer`
- Sanitizer applies 8 regex patterns to strip injection attempts
- System prompt is a hardcoded constant (not user-influenced)
- Claude response: parsed strictly as JSON — invalid response triggers circuit breaker

## Model

Default: `claude-haiku-4-5-20251001` (fast, ~2-4s). Silent retry: second attempt uses same model. Total max time: ~8s before error shown.
