# Scope Analysis: FEAT-AUTOCOMPLETE-LIST-SOURCE

## Feature Request

Items added to shopping lists but never purchased (never checked off) do not appear as autocomplete suggestions. Currently the suggestion pipeline only draws from `producto_historial` (Layer 1, purchase history) and `producto_catalogo` (Layer 2, global catalog). Users who add the same product to lists repeatedly without checking it off never build up history, so it never surfaces in autocomplete.

**Goal**: Add `list_items` as a new suggestion layer so that any product the user has ever added to a list — regardless of purchase status — becomes a valid suggestion candidate.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | MEDIUM |
| Estimated Effort | 5 hours |
| Confidence | High |

## Justification

- Introduces new business logic (new layer in suggestion pipeline)
- Requires a database migration (add index on `list_items.name` for prefix-search performance — no index exists today)
- Modifies `ProductSuggestionService` and `ProductHistoryWeightingService` (or introduces a new service)
- Must correctly scope queries to the requesting user (security constraint)

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Medium | `list_items` has no `user_id` column — must JOIN through `shopping_lists`. Query: `list_items JOIN shopping_lists WHERE shopping_lists.user_id = ?` |
| Data | Low | No schema changes to existing data. Migration adds one index only. |
| Security | Medium | Query MUST be scoped to `shopping_lists.user_id = authenticated user`. A missing WHERE clause would expose other users' list items. |
| Performance | Medium | No index on `list_items.name` today. Without it, prefix search requires a full scan of items filtered by list_ids. Migration must add `index(['shopping_list_id', 'name'])` or a standalone `name` index. |
| Operational | Low | No deploy complexity. Migration is additive (index only, no data change). |

## Affected Areas

- `app/Services/ProductSuggestionService.php` — add new layer call, update `suggest()` merge logic
- `app/Services/ProductHistoryWeightingService.php` — possibly add `searchListItems()` here, or extract to a new `ListItemSuggestionService`
- `database/migrations/` — new migration adding index on `list_items(name)` (or composite `(shopping_list_id, name)`)
- `tests/Unit/Services/ProductSuggestionServiceTest.php` — new test cases for the new layer

## Open Questions

None. All requirements are clear from the conversation:
- Source: `list_items.name` prefix match, scoped to user's own lists
- Priority: this layer sits between Layer 1 (history) and Layer 2 (catalog) — items the user actively added to lists are more personal than catalog entries
- Dedup: existing `dedup()` logic handles duplicates across layers

## Recommendation

- [x] Require PRD (MEDIUM → STEP 2)

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)
