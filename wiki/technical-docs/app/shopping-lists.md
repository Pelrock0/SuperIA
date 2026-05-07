# Technical Docs — Shopping Lists

**Keywords:** lists, archive, freemium, create, delete, emoji, category, dashboard

## Overview

`ShoppingListService` manages list lifecycle. Freemium limit: 3 active lists per user, enforced atomically.

## Freemium Enforcement

```php
DB::transaction(function() use ($user, $data) {
    $count = ShoppingList::where('user_id', $user->id)
        ->where('status', 'active')
        ->lockForUpdate()  // pessimistic lock prevents race condition
        ->count();
    if ($count >= 3) throw new OverflowException('Límite de listas activas alcanzado');
    return ShoppingList::create([...]);
});
```

The `lockForUpdate()` is critical — two concurrent creates would both see count=2 without it.

## List Categories

5 types: `Supermercado`, `Mercado`, `Online`, `Farmacia`, `Otro`

## List States

| Status | Counts toward limit | Visible in dashboard |
|--------|---------------------|---------------------|
| `active` | Yes | Yes (top section) |
| `archived` | No | Yes (collapsed section) |

## Dashboard Response Structure

```json
{
  "active": [ { "id", "name", "emoji", "category", "status", "is_shared",
                "items_total", "items_completed", "updated_at" } ],
  "archived": [ ... ],
  "collaborated": [ { ..., "owner_name", "mode": "read|write" } ]
}
```

## Share Tokens

Each list can have up to 2 active tokens (one read, one write). Tokens use HMAC-SHA256 signing — see [collaboration.md](collaboration.md).

## Collaborator Lists

Lists in `collaborated` section are fetched via `ListCollaboratorService::collaboratedListsForUser()`. User accesses these without a token URL (after explicitly saving via `POST /api/shared/{token}/save`).

## Key Behaviors

- Hard delete is permanent (no soft-delete for lists)
- Archive/restore both re-check freemium limit (restore can fail if 3 active already)
- `is_shared` set to true when first share token created; false when last token revoked
- `items_total` / `items_completed` are denormalized counters, kept in sync by `ListItemService::syncCounters()`
