# Implementation Notes: FEAT-EPIC2-LISTS

## Summary

Shopping list CRUD with freemium limit (max 3 active), archive/restore, and permanent deletion. One new table `shopping_lists` with enums for status and category. Dashboard page with list cards, create modal, and empty state.

## Scope Changes

None.

## Files Changed

| File | Type | Description |
|------|------|-------------|
| app/Enums/ListStatus.php | Created | Enum: active, archived |
| app/Enums/ListCategory.php | Created | Enum: supermercado, mercado, online, farmacia, otro |
| app/Models/ShoppingList.php | Created | Model with User relation, enum casts, isActive/isArchived helpers |
| app/Models/User.php | Modified | Added shoppingLists() HasMany relationship |
| app/Services/ShoppingListService.php | Created | CRUD + freemium limit (atomic) + archive/restore |
| app/Services/AccountDeletionService.php | Modified | Added $user->shoppingLists()->delete() in transaction |
| app/Http/Controllers/ShoppingListController.php | Created | 7 endpoints, ownership validation |
| app/Http/Requests/CreateListRequest.php | Created | name required max 60, emoji, category enum |
| app/Http/Requests/UpdateListRequest.php | Created | Same with sometimes modifier |
| database/migrations/create_shopping_lists_table.php | Created | FK cascade user, composite index (user_id, status) |
| database/factories/ShoppingListFactory.php | Created | Factory with archived() state |
| routes/api.php | Modified | 7 list endpoints under auth middleware |
| resources/js/pages/DashboardPage.jsx | Created | Active/archived sections, header, create modal |
| resources/js/components/lists/ListCard.jsx | Created | Card with options menu, delete confirm |
| resources/js/components/lists/CreateListModal.jsx | Created | Name, emoji picker, category selector |
| resources/js/components/lists/EmptyState.jsx | Created | Welcome + CTA |
| resources/js/app.jsx | Modified | DashboardPage + list detail placeholder |

## API Contract (Backend -> Frontend)

### Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | /api/lists | JWT | User's lists grouped: { active: [...], archived: [...] } |
| POST | /api/lists | JWT | Create list (freemium check) |
| GET | /api/lists/{id} | JWT | Single list detail |
| PUT | /api/lists/{id} | JWT | Update name, emoji, category |
| PATCH | /api/lists/{id}/archive | JWT | Archive list |
| PATCH | /api/lists/{id}/restore | JWT | Restore list (freemium check) |
| DELETE | /api/lists/{id} | JWT | Permanent delete |

### Error Codes

| Code | Meaning | Frontend Action |
|------|---------|-----------------|
| 403 | FREEMIUM_LIMIT | Show limit message in modal |
| 403 | Ownership denied | Show access denied |
| 422 | Validation error | Show field errors |

## Implementation Decisions

1. **Freemium limit**: SELECT COUNT with lockForUpdate() in transaction prevents race condition.
2. **Hard delete**: Lists are permanently deleted per PRD (no soft delete for lists).
3. **Emoji as unicode**: Stored directly in VARCHAR(10), MySQL utf8mb4 supports it.
4. **is_shared placeholder**: Boolean field, always false. No functionality until Epic 4.
5. **items_total/items_completed placeholders**: Default 0, updated by Epic 3.

## Tests

| Test File | Type | Tests |
|-----------|------|-------|
| tests/Feature/ShoppingListControllerTest.php | Feature | 25 tests |
| tests/Unit/Services/ShoppingListServiceTest.php | Unit | 10 tests |
| resources/js/components/lists/EmptyState.test.jsx | Component | 2 tests |
| resources/js/components/lists/ListCard.test.jsx | Component | 10 tests |
| resources/js/components/lists/CreateListModal.test.jsx | Component | 6 tests |
| resources/js/pages/DashboardPage.test.jsx | Page | 8 tests |

## Known Issues / Technical Debt

None.

## Deviations from Design

None.
