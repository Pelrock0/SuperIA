# PRD: FEAT-AUTOCOMPLETE-LIST-SOURCE - Autocomplete from Shopping List History

## Business Objective

Users who add the same product to their shopping lists repeatedly but never check it off (never purchase it) never build a purchase history, so the product never appears in autocomplete suggestions. This forces users to type the full name every time instead of selecting from suggestions.

Adding `list_items` as a suggestion source makes the autocomplete immediately useful from the first day of use, not just after enough purchases have been recorded.

## Problem Statement

Any user who adds items to lists is affected. The autocomplete pipeline currently reads from:
- `producto_historial` (Layer 1): only populated on item check-off
- `producto_catalogo` (Layer 2): global catalog, not personalized

A user who has added "Chocolate negro 70%" to a list 20 times but never checked it off will never see it suggested. The catalog may not contain that specific variant either.

## Scope

### In Scope

- New suggestion layer that searches `list_items.name` with prefix match, scoped to the authenticated user's own lists
- Layer sits between Layer 1 (history) and Layer 2 (catalog) in priority order: history → **list items** → catalog → AI fallback
- Deduplication across all layers (existing logic handles this)
- Database migration adding an index to support prefix-search performance on `list_items.name`
- Source label `'list'` on suggestions returned from this layer

### Out of Scope

- Changes to the AI fallback layer (Layer 3)
- Changes to `producto_historial` population logic
- Storing frequency counts or weighting from list additions
- Any UI changes — the suggestion API response format remains identical
- Cross-user data (a user's list items are never visible to other users)

## Acceptance Criteria

### AC-1: Suggestions from list items appear when no purchase history exists
- **Given**: A user has added "Chocolate negro 70%" to a list but never purchased it (no `producto_historial` row)
- **When**: The user types "choc" in the item autocomplete field
- **Then**: "Chocolate negro 70%" appears in suggestions with `source: 'list'`

### AC-2: List layer is lower priority than purchase history
- **Given**: A user has "Leche entera" in both `producto_historial` and `list_items`
- **When**: The user types "lech"
- **Then**: The suggestion appears with `source: 'history'` (Layer 1 wins)

### AC-3: List layer is higher priority than catalog
- **Given**: A user has "Tortitas de arroz" in `list_items`; the catalog also has a matching entry
- **When**: The user types "tort"
- **Then**: The suggestion appears with `source: 'list'` (list layer wins over catalog)

### AC-4: Items from other users' lists are never returned
- **Given**: User A has "Producto secreto XYZ" in a list; User B has no such item
- **When**: User B types "prod"
- **Then**: "Producto secreto XYZ" does NOT appear in User B's suggestions

### AC-5: Prefix match only — partial middle-word does not match
- **Given**: A user has "Pan integral" in a list
- **When**: The user types "integral" (not a prefix)
- **Then**: "Pan integral" does NOT appear (prefix-only matching, consistent with other layers)

### AC-6: Empty query returns no list-layer results
- **Given**: Any user
- **When**: The autocomplete query is empty or whitespace-only
- **Then**: The list layer returns zero results (consistent with other layers)

### AC-7: Deduplication — item appearing in both list and catalog shows once
- **Given**: "Arroz" exists in both the user's `list_items` and `producto_catalogo`
- **When**: The user types "arr"
- **Then**: "Arroz" appears exactly once in suggestions

## UX Decision

- **UX Designer Required**: No
- **UX Artifacts**: N/A
- **Basic UX Notes**: The suggestion UI is unchanged. The `source` field in the API response changes from `'catalog'` to `'list'` for affected results, but the frontend renders all sources identically today.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Query scoping bug exposes other users' items | Security | JOIN through `shopping_lists.user_id` enforced at query level; covered by AC-4 and a negative test |
| Prefix search on `list_items.name` is slow without index | Performance | Migration adds `index(['shopping_list_id', 'name'])` — matches the JOIN pattern |
| Duplicate suggestions across layers | Data | Existing `dedup()` in `ProductSuggestionService` handles this; covered by AC-7 |

## Assumptions

- The frontend treats all suggestion sources identically in the UI (no source-specific rendering)
- Prefix-only matching is acceptable for list items (consistent with Layers 1 and 2)
- No minimum list-addition frequency threshold is needed (any single addition qualifies)

## Open Questions

None.

## Approval

- [ ] PRD approved on 2026-04-21

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 – Technical Design
- Required Artifacts for Next Step: 02-prd.md
