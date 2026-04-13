# Implementation Notes: FEAT-EPIC3-ITEMS

## Summary

Items CRUD within shopping lists. Two new tables: `list_items` (products in a list) and `producto_historial` (purchase history, append-only, feeds Epics 5-8). Counter sync via atomic COUNT in transactions. Undo is frontend-only (5s window to re-create deleted item).

## Scope Changes

None.

## Files Changed

| File | Type | Description |
|------|------|-------------|
| app/Enums/ItemUnit.php | Created | Enum: kg, g, L, ml, ud, pack |
| app/Enums/ProductCategory.php | Created | Enum: 10 product categories |
| app/Models/ListItem.php | Created | Item model with ShoppingList relation |
| app/Models/ProductoHistorial.php | Created | Purchase history model (append-only) |
| app/Models/ShoppingList.php | Modified | Added items() HasMany relationship |
| app/Models/User.php | Modified | Added productoHistorial() HasMany relationship |
| app/Services/ListItemService.php | Created | CRUD + counter sync + historial recording |
| app/Http/Controllers/ListItemController.php | Created | 6 endpoints, ownership chain validation |
| app/Http/Requests/CreateItemRequest.php | Created | Validation: name required max 80, quantity, unit, category, price |
| app/Http/Requests/UpdateItemRequest.php | Created | Same fields with sometimes modifier |
| database/migrations/create_list_items_table.php | Created | list_items with FK cascade, composite index |
| database/migrations/create_producto_historial_table.php | Created | producto_historial with FK cascade user, SET NULL lista |
| database/factories/ListItemFactory.php | Created | Factory with purchased() state |
| routes/api.php | Modified | 6 new item endpoints nested under /lists/{list}/items |
| resources/js/pages/ListDetailPage.jsx | Created | Full page: items by category, progress, add, edit, undo, clear |
| resources/js/components/items/AddItemInput.jsx | Created | Quick-add input with Enter submit |
| resources/js/components/items/ItemRow.jsx | Created | Checkbox, name, quantity/price, edit, delete |
| resources/js/components/items/EditItemPanel.jsx | Created | Slide panel: name, quantity, unit, category, price |
| resources/js/components/items/UndoSnackbar.jsx | Created | 5s floating undo bar |
| resources/js/app.jsx | Modified | ListDetailPage replaces placeholder route |

## Migrations

| Migration | Description | Reversible |
|-----------|-------------|------------|
| create_list_items_table | Items with FK to shopping_lists (CASCADE), composite index (shopping_list_id, is_purchased) | Yes |
| create_producto_historial_table | Purchase history with FK user (CASCADE), FK lista (SET NULL), indexes on (user_id, producto_nombre) and (user_id, fecha_compra) | Yes |

## API Contract (Backend -> Frontend)

### Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | /api/lists/{listId}/items | JWT | Items grouped by category + counters |
| POST | /api/lists/{listId}/items | JWT | Add item, returns item + counters |
| PUT | /api/lists/{listId}/items/{itemId} | JWT | Update item fields |
| PATCH | /api/lists/{listId}/items/{itemId}/toggle | JWT | Toggle purchased, creates historial if purchased |
| DELETE | /api/lists/{listId}/items/{itemId} | JWT | Delete item, returns updated counters |
| DELETE | /api/lists/{listId}/items/completed | JWT | Clear all completed items, returns counters |

### Response Examples

```json
// GET /api/lists/1/items
{
  "data": {
    "items": {
      "bebidas": [{ "id": 1, "name": "Agua", "quantity": "6.00", "unit": "L", "category": "bebidas", "estimated_price": "3.00", "is_purchased": false }],
      "panaderia": [{ "id": 2, "name": "Pan", ... }]
    },
    "counters": { "items_total": 2, "items_completed": 0 }
  }
}

// POST /api/lists/1/items (201)
{ "data": { "item": { ... }, "counters": { "items_total": 3, "items_completed": 0 } } }

// PATCH toggle
{ "data": { "item": { "is_purchased": true, ... }, "counters": { "items_total": 3, "items_completed": 1 } } }

// DELETE item or DELETE completed
{ "data": { "counters": { "items_total": 2, "items_completed": 0 } } }
```

## Implementation Decisions

1. **Counter sync via COUNT**: Instead of increment/decrement, always COUNT from DB inside transaction. Prevents desync.
2. **Undo frontend-only**: Backend deletes immediately. Frontend stores item data 5s for re-creation via POST. Simpler than backend soft-delete + cleanup job.
3. **Route order**: `/lists/{list}/items/completed` registered before `/lists/{list}/items/{item}` to prevent "completed" matching as item ID.
4. **producto_historial.lista_id SET NULL**: History survives list deletion (independent consumption data per HU-502).
5. **Items without category**: Grouped under "otros" key in service response.

## Tests

| Test File | Type | Tests |
|-----------|------|-------|
| tests/Feature/ListItemControllerTest.php | Feature | 25 tests (CRUD, ownership, validation, toggle+historial, clear completed) |
| tests/Unit/Services/ListItemServiceTest.php | Unit | 8 tests (service methods, counter sync, historial) |
| resources/js/components/items/UndoSnackbar.test.jsx | Component | 3 tests |
| resources/js/components/items/AddItemInput.test.jsx | Component | 5 tests |
| resources/js/components/items/ItemRow.test.jsx | Component | 7 tests |
| resources/js/components/items/EditItemPanel.test.jsx | Component | 4 tests |
| resources/js/pages/ListDetailPage.test.jsx | Page | 7 tests |

## Known Issues / Technical Debt

1. **Pre-existing**: WaitlistServiceTest::test_register_assigns_sequential_position fails with DatabaseTransactions (expects empty DB). Not introduced by this feature.
2. **Non-blocking**: ItemRow delete button uses opacity-0 hover pattern — not keyboard-visible. Consider adding focus-within:opacity-100.

## Deviations from Design

None.
