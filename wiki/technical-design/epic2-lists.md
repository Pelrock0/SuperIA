# Technical Design — FEAT-EPIC2-LISTS

## Architecture

Standard Laravel resource controller + service layer. `ShoppingListService` owns the freemium gate logic.

## Data Flow

```
Create list:
  POST /api/lists { name, emoji, category }
  → ShoppingListService::create()
    → DB::transaction {
        SELECT COUNT(*) FROM shopping_lists
          WHERE user_id = ? AND status = 'active'
          FOR UPDATE              ← pessimistic lock
        IF count >= 3 → throw OverflowException
        INSERT shopping_lists { user_id, name, emoji, category, status='active' }
      }

Archive:
  PATCH /api/lists/{id}/archive
  → ShoppingListService::archive()
    → UPDATE status = 'archived'   (no lock needed — only removing from limit)

Restore:
  PATCH /api/lists/{id}/restore
  → ShoppingListService::restore()
    → Same freemium re-check as create (lock + count)

Delete:
  DELETE /api/lists/{id}
  → Hard delete (permanent) — cascades items via DB constraint

Dashboard:
  GET /api/lists
  → Single query (no N+1), sorted active-first then updated_at DESC
  → PHP groups into { active: [], archived: [], collaborated: [] }
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| `SELECT ... FOR UPDATE` | Prevents race condition where two concurrent creates both read count=2 and both succeed |
| Hard delete (not soft) | PRD specifies "immediate permanent deletion" |
| `is_shared` placeholder | Avoids migration in Epic 4 for existing lists |
| Composite index `(user_id, status)` | Freemium check and dashboard query both use it |

## Gotchas

- `items_total` and `items_completed` are 0 for all lists until Epic 3 writes them
- `is_shared` is always false until Epic 4 — frontend shows share icon based on this flag
- Category labels are hard-coded on frontend; backend enum is the source of truth for validation
