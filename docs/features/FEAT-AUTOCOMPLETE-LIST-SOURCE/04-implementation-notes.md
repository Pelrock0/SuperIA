# Backend Implementation Notes: FEAT-AUTOCOMPLETE-LIST-SOURCE

## Summary

Added `list_items` as a new layer between purchase history (Layer 1) and the global catalog (Layer 2) in the autocomplete suggestion pipeline. Items added to any of the user's shopping lists — regardless of purchase status — now surface as suggestions with `source: 'list'`.

## Files Changed

| File | Type | Description |
|------|------|-------------|
| `app/Services/ProductSuggestionService.php` | Modified | Added `searchListItems()` private method; updated `suggest()` merge order |
| `database/migrations/2026_04_21_133606_add_name_index_to_list_items_table.php` | Created | Composite index `(shopping_list_id, name)` on `list_items` |
| `tests/Unit/Services/ProductSuggestionServiceTest.php` | Modified | 8 new tests covering all 7 ACs + DISTINCT edge case |

## Migrations

| Migration | Description | Reversible |
|-----------|-------------|------------|
| `2026_04_21_133606_add_name_index_to_list_items_table` | Adds `(shopping_list_id, name)` index on `list_items` | Yes (`dropIndex`) |

## API Endpoints

No changes. The `source` field gains a new value: `'list'`.

## Tests Added

| Test | AC |
|------|----|
| `test_layer_list_returns_item_added_to_list_without_purchase` | AC-1 |
| `test_layer_list_loses_to_history_in_dedup` | AC-2 |
| `test_layer_list_beats_catalog_in_dedup` | AC-3 |
| `test_layer_list_never_returns_other_users_items` | AC-4 (security negative test) |
| `test_layer_list_prefix_only_no_mid_word_match` | AC-5 |
| `test_layer_list_empty_query_returns_nothing` | AC-6 |
| `test_layer_list_deduplicates_with_catalog` | AC-7 |
| `test_layer_list_distinct_across_multiple_lists` | Edge case |

## Test Coverage Report

| Component | Coverage |
|-----------|----------|
| `ProductSuggestionService` | 100% |
| **Total** | **100%** |

Result: 23 passed (47 assertions)

## Notes for Reviewers

- `searchListItems()` scopes to `shopping_lists.user_id = $user->id` — user isolation enforced at query level, verified by AC-4 negative test
- LIKE escape follows identical pattern to `searchCatalog()` and `ProductHistoryWeightingService::search()`
- `DISTINCT` on `list_items.name` handles same product appearing in multiple lists

## Deviations from Design

None.

## Scope Changes

None.

## Known Issues / Technical Debt

None.

## Transition

- Gate Status: S4 PASSED
- Next Step: STEP 5 – Review
