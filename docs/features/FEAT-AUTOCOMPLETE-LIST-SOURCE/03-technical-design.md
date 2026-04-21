# Technical Design: FEAT-AUTOCOMPLETE-LIST-SOURCE

## Overview

The autocomplete suggestion pipeline in `ProductSuggestionService::suggest()` currently merges two local layers (purchase history + catalog) before optionally falling back to AI. This design inserts a third local layer between them: a prefix search over `list_items.name` scoped to the authenticated user's own shopping lists.

No new service class is introduced. A private `searchListItems(User $user, string $query, int $limit)` method is added to `ProductSuggestionService`, matching the existing `searchCatalog()` pattern. The merge order is updated from `[layer1, layer2]` to `[layer1, layerList, layer2]`, which the existing `dedup()` logic already handles correctly.

A database migration adds a composite index `(shopping_list_id, name)` to `list_items` to support the prefix-search join without a full table scan.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes |
|-------|-----------------|-------------|
| Services | Orchestrate suggestion layers, dedup, AI fallback | `ProductSuggestionService` |
| Infrastructure | DB query for list items (prefix search + user scope) | `ProductSuggestionService::searchListItems()` (private) |
| Infrastructure | DB query for catalog | `ProductSuggestionService::searchCatalog()` (private, unchanged) |
| Infrastructure | DB query for history | `ProductHistoryWeightingService::search()` (unchanged) |
| Controllers/API | Receive request, delegate to service, return response | Existing suggestion controller (unchanged) |
| Frontend | Render suggestions (unchanged — no source-specific rendering) | Unchanged |

### Data Flow

```
GET /api/suggest?q={query}&ai={bool}
  → Controller::suggest(Request)
  → ProductSuggestionService::suggest(User, query, includeAi)
      → Layer 1: ProductHistoryWeightingService::search(user, query, 5)
                 → SELECT FROM producto_historial WHERE user_id=? AND nombre LIKE ?%
      → Layer NEW: ProductSuggestionService::searchListItems(user, query, 5)
                   → SELECT DISTINCT list_items.name FROM list_items
                     JOIN shopping_lists ON shopping_lists.id = list_items.shopping_list_id
                     WHERE shopping_lists.user_id = ?
                     AND list_items.name LIKE ?%
                     LIMIT 5
      → Layer 2: ProductSuggestionService::searchCatalog(query, 5)
                 → SELECT FROM producto_catalogo WHERE nombre LIKE ?%
      → dedup([layer1, layerList, layer2], 5)
      → IF includeAi AND count < 3: tryAiFallback(user, query)
  → Response: { suggestions: [...], ai_fallback_used: bool, ai_limit_reached: bool }
```

### Transaction Boundaries

No writes occur in the suggestion flow. No transactions required. All queries are read-only.

## Data Model

### Modified Tables

| Table | Change | Reason |
|-------|--------|--------|
| `list_items` | Add index `(shopping_list_id, name)` | Support prefix-search JOIN without full scan |

No new tables. No column changes. No data migrations.

### Migrations

1. **`add_name_index_to_list_items_table`**: Adds composite index `['shopping_list_id', 'name']` on `list_items`. Reversible (`dropIndex`).

The index is composite because the query always filters by `shopping_list_id` (via JOIN) before applying the `name LIKE ?%` prefix filter. MySQL/MariaDB can use a composite index for this pattern.

### API Changes

No endpoint changes. The `source` field in suggestion objects gains a new possible value: `'list'`. The field already accepts `'history'`, `'catalog'`, `'ai'`. Frontend renders all sources identically.

## Performance

### Query Optimization

- The new query JOINs `shopping_lists` on `id` (PK, indexed) and filters by `user_id`. `shopping_lists.user_id` should already be indexed (FK). The composite index `(shopping_list_id, name)` on `list_items` covers the join key + prefix filter in one index scan.
- `DISTINCT` on `name` prevents returning the same product name from multiple lists.
- `LIMIT 5` mirrors the existing layer limit (`LOCAL_LIMIT = 5`). The final `dedup()` enforces the global cap.
- No N+1: the query is a single JOIN with no per-row subqueries.

### Caching Strategy

Not applicable. Suggestion queries are user-specific, query-specific, and expected to be fast (< 50ms) with the index in place. No cache layer added.

## Security Considerations

- **User scoping (critical)**: The query MUST include `shopping_lists.user_id = $user->id` in the WHERE clause. Without it, a user could see items from other users' lists. This is enforced at the query level, not the controller level.
- **Input sanitization**: The `name LIKE ?%` value is parameterized (no raw interpolation). Special LIKE characters (`%`, `_`, `\`) must be escaped before use, consistent with `searchCatalog()` and `ProductHistoryWeightingService::search()`.
- **No authentication changes**: The existing authentication middleware on the suggestion endpoint remains unchanged.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Add `searchListItems()` as private method in `ProductSuggestionService` | Consistent with `searchCatalog()` pattern; no new class; minimal surface area | Service grows slightly | **Selected** |
| New `ListItemSuggestionService` class | Better SRP; isolated | Over-engineering for a single query method; adds DI complexity | Rejected — not justified for one method |
| Add to `ProductHistoryWeightingService` | Keeps list/history "personal" sources together | Violates SRP — historial weighting ≠ list item search | Rejected |
| Search list_items in the AI fallback only | Simpler | Defeats the purpose (AI fallback is throttled; list items should always be local) | Rejected |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Missing `user_id` scope exposes other users' items | High | Low | Enforced in query; covered by AC-4 negative test |
| Missing LIKE escape causes wildcard injection | Medium | Low | Apply same escape function as `searchCatalog()` |
| `(shopping_list_id, name)` index not used by query planner | Medium | Low | Verify with `EXPLAIN` in migration review; index matches JOIN+prefix pattern exactly |
| Users with very large list history return stale suggestions | Low | Low | LIMIT 5 caps results; no ordering by recency needed for this layer |

## Open Questions

None.

## Implementation Notes

1. Add migration first: `php artisan make:migration add_name_index_to_list_items_table`
2. Add `searchListItems(User $user, string $query, int $limit): array` to `ProductSuggestionService` — returns `Suggestion[]` with `source: 'list'`
3. Update `suggest()` merge: `$this->dedup([...$layer1, ...$layerList, ...$layer2], self::LOCAL_LIMIT)`
4. `Suggestion` DTO already accepts `source` as a string — no DTO changes needed
5. Tests: use `DatabaseTransactions`; create lists via `ShoppingList::factory()` + `list_items()->create()`; no new factories needed

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 – Implementation
- Required Artifacts: 02-prd.md, 03-technical-design.md
