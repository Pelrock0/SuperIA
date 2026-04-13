# Technical Design: FEAT-EPIC3-ITEMS

## Overview

Two new tables: `list_items` (items within a shopping list) and `producto_historial` (purchase history, append-only, feeds Epics 5-8). One main service (`ListItemService`) handles CRUD + counter sync + historial recording. Items belong to a list, which belongs to a user — ownership validated through the list chain. Counters on `shopping_lists` are updated atomically via DB COUNT after each operation.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | ListItem model, ProductoHistorial model, ProductCategory enum, ItemUnit enum | `App\Models\ListItem`, `App\Models\ProductoHistorial`, `App\Enums\ProductCategory`, `App\Enums\ItemUnit` |
| Services | Item CRUD, counter sync, historial recording, clear completed | `App\Services\ListItemService` |
| Controllers/API | HTTP interface, ownership validation, request validation | `App\Http\Controllers\ListItemController` |
| Frontend | List detail page, item row, add input, edit panel, undo snackbar, progress bar | `ListDetailPage`, `ItemRow`, `AddItemInput`, `EditItemPanel`, `UndoSnackbar` |

### Data Flow

#### Add Item
```
1. POST /api/lists/{listId}/items { name, quantity?, unit?, category?, estimated_price? }
2. Controller: validate ownership (list.user_id === auth user), validate via CreateItemRequest
3. Service: DB::transaction {
     a. Create ListItem
     b. Sync counters: UPDATE shopping_lists SET items_total = (SELECT COUNT...), items_completed = (SELECT COUNT... WHERE is_purchased) WHERE id = listId
   }
4. Return 201 with item + updated list counters
```

#### Toggle Purchased
```
1. PATCH /api/lists/{listId}/items/{itemId}/toggle
2. Controller: validate ownership
3. Service: DB::transaction {
     a. Toggle item.is_purchased
     b. If now purchased: create ProductoHistorial record
     c. Sync counters
   }
4. Return item + updated counters
```

#### Delete Item
```
1. DELETE /api/lists/{listId}/items/{itemId}
2. Controller: validate ownership
3. Service: DB::transaction {
     a. Delete item (hard delete)
     b. Sync counters
   }
4. Return updated counters
```

#### Clear Completed
```
1. DELETE /api/lists/{listId}/items/completed
2. Controller: validate ownership
3. Service: DB::transaction {
     a. Delete all items WHERE list_id = X AND is_purchased = true
     b. Sync counters (items_completed becomes 0)
   }
4. Return updated counters
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| Add item | Create item + sync counters | Atomic counter update |
| Toggle purchased | Update item + create historial (if purchased) + sync counters | Historial must be tied to toggle |
| Update item | Update item fields | Single operation |
| Delete item | Delete item + sync counters | Atomic counter update |
| Clear completed | Delete all completed + sync counters | Batch + counter |

### Counter Sync Strategy

Instead of increment/decrement (fragile, can desync), use COUNT as source of truth:

```php
private function syncCounters(ShoppingList $list): void
{
    $list->update([
        'items_total' => $list->items()->count(),
        'items_completed' => $list->items()->where('is_purchased', true)->count(),
    ]);
}
```

This runs inside the same transaction as the item operation.

## Data Model

### New Table: `list_items`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | |
| `shopping_list_id` | foreignId | FK shopping_lists.id, ON DELETE CASCADE, index | Parent list |
| `name` | string(80) | NOT NULL | Product name |
| `quantity` | decimal(8,2) | NULLABLE | Amount |
| `unit` | enum | NULLABLE: kg, g, L, ml, ud, pack | Unit of measure |
| `category` | enum | NULLABLE: 10 product categories | Product category |
| `estimated_price` | decimal(8,2) | NULLABLE | Estimated price in EUR |
| `is_purchased` | boolean | NOT NULL, default false | Checked as bought |
| `position` | unsignedInteger | NOT NULL, default 0 | Sort order within category |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

Indexes: `shopping_list_id` (implicit FK), composite `(shopping_list_id, is_purchased)` for grouping query.

### New Table: `producto_historial`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | |
| `user_id` | foreignId | FK users.id, ON DELETE CASCADE, index | Owner |
| `producto_nombre` | string(80) | NOT NULL | Product name at time of purchase |
| `categoria` | enum | NULLABLE: 10 product categories | Category at time of purchase |
| `cantidad` | decimal(8,2) | NULLABLE | Quantity purchased |
| `unidad` | enum | NULLABLE: kg, g, L, ml, ud, pack | Unit |
| `precio_real` | decimal(8,2) | NULLABLE | Real price (filled later via HU-702) |
| `fecha_compra` | timestamp | NOT NULL | When marked as purchased |
| `lista_id` | foreignId | FK shopping_lists.id, ON DELETE SET NULL, nullable | Source list (null if list deleted) |

Indexes: `user_id`, `(user_id, producto_nombre)` for frequency queries (Epic 5), `(user_id, fecha_compra)` for recency.

**Note**: `lista_id` uses SET NULL on delete because historial survives list deletion (it's the user's consumption history, independent of lists per HU-502).

### Enums

```php
// App\Enums\ItemUnit
enum ItemUnit: string {
    case Kg = 'kg';
    case G = 'g';
    case L = 'L';
    case Ml = 'ml';
    case Ud = 'ud';
    case Pack = 'pack';
}

// App\Enums\ProductCategory
enum ProductCategory: string {
    case FrutasVerduras = 'frutas_verduras';
    case CarnesPescados = 'carnes_pescados';
    case LacteosHuevos = 'lacteos_huevos';
    case Panaderia = 'panaderia';
    case Bebidas = 'bebidas';
    case Congelados = 'congelados';
    case Limpieza = 'limpieza';
    case HigienePersonal = 'higiene_personal';
    case Conservas = 'conservas';
    case Otros = 'otros';
}
```

### API Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/lists/{listId}/items` | GET | JWT | Get all items for a list (grouped by category) |
| `/api/lists/{listId}/items` | POST | JWT | Add item to list |
| `/api/lists/{listId}/items/{itemId}` | PUT | JWT | Update item |
| `/api/lists/{listId}/items/{itemId}/toggle` | PATCH | JWT | Toggle purchased state |
| `/api/lists/{listId}/items/{itemId}` | DELETE | JWT | Delete item |
| `/api/lists/{listId}/items/completed` | DELETE | JWT | Clear all completed items |

### API Response Format

```json
// GET /api/lists/{listId}/items
{
  "data": {
    "items": {
      "frutas_verduras": [
        { "id": 1, "name": "Manzanas", "quantity": 1, "unit": "kg", "category": "frutas_verduras", "estimated_price": 2.50, "is_purchased": false }
      ],
      "lacteos_huevos": [...],
      "otros": [...]
    },
    "counters": { "items_total": 5, "items_completed": 2 }
  }
}

// POST /api/lists/{listId}/items (201)
{
  "data": {
    "item": { "id": 1, "name": "Leche", ... },
    "counters": { "items_total": 6, "items_completed": 2 }
  }
}

// PATCH toggle / DELETE
{
  "data": {
    "item": { ... },  // or null for delete
    "counters": { "items_total": 5, "items_completed": 3 }
  }
}
```

## Integration with Existing Code

### ShoppingList Model

Add relationship:
```php
public function items(): HasMany
{
    return $this->hasMany(ListItem::class, 'shopping_list_id');
}
```

### User Model

Add relationship:
```php
public function productoHistorial(): HasMany
{
    return $this->hasMany(ProductoHistorial::class);
}
```

### AccountDeletionService

`producto_historial` has `ON DELETE CASCADE` on user_id — auto-cleaned on hard-delete. For soft-delete phase, historial is kept (user might recover within 30 days).

## Security

- **Ownership chain**: Item → List → User. Controller validates `list.user_id === auth('api')->id()` before any item operation.
- **No direct item access**: Items always accessed through list context (`/lists/{listId}/items/...`).
- **Validation**: FormRequests validate name, quantity (numeric positive), unit (enum), category (enum), price (numeric positive).

## Performance

- **Items query**: Single query `WHERE shopping_list_id = ? ORDER BY is_purchased, category, position`. Covered by index.
- **Counter sync**: 2 COUNT queries inside transaction. Fast on indexed column.
- **Grouping**: Done in service layer on fetched collection (single query, group in PHP). Alternative: multiple queries per category — worse.
- **No N+1**: Items fetched in one query. No nested relations needed.

## Frontend Architecture

### Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `ListDetailPage` | `pages/ListDetailPage.jsx` | Full page at `/app/listas/:id` |
| `AddItemInput` | `components/items/AddItemInput.jsx` | Always-visible input to add items quickly |
| `ItemRow` | `components/items/ItemRow.jsx` | Single item row with checkbox, name, delete button |
| `EditItemPanel` | `components/items/EditItemPanel.jsx` | Slide panel to edit item details |
| `UndoSnackbar` | `components/items/UndoSnackbar.jsx` | Floating snackbar with undo action + 5s timer |

### State Management

Local state in `ListDetailPage`:
- `items`: grouped object from API
- `counters`: { items_total, items_completed }
- `editingItem`: item being edited (or null)
- `deletedItem`: recently deleted item for undo (or null)
- `undoTimeout`: timer reference

On every mutation (add, toggle, edit, delete, clear), refetch items + counters from API to keep in sync.

### Undo Flow

```
1. User clicks delete → frontend stores item data in state
2. Calls DELETE /api/.../{itemId} immediately
3. Shows UndoSnackbar with 5s timer
4. If user clicks "Deshacer": POST /api/lists/{listId}/items with stored data
5. If timer expires: clear stored data, snackbar disappears
```

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Counter via COUNT | Always accurate | 2 extra queries per operation | **Selected**: correctness > micro-optimization |
| Counter via increment/decrement | Faster | Can desync on edge cases (concurrent ops, bugs) | Rejected |
| Server-side grouping in SQL | One query per group | Multiple queries, complex SQL | Rejected |
| PHP collection grouping | Single query, simple | Memory if 1000+ items | **Selected**: realistic list size < 100 |
| Backend undo (soft-delete) | Reliable | Needs cleanup job, complex | Rejected (stakeholder decision) |
| Frontend undo | Simple, no backend complexity | Lost if user navigates away | **Selected** |
| producto_historial SET NULL on list delete | Preserves history | Orphaned lista_id | **Selected**: history is independent per HU-502 |

## File Structure (new files)

```
app/
├── Enums/
│   ├── ItemUnit.php
│   └── ProductCategory.php
├── Models/
│   ├── ListItem.php
│   └── ProductoHistorial.php
├── Services/
│   └── ListItemService.php
├── Http/
│   ├── Controllers/
│   │   └── ListItemController.php
│   └── Requests/
│       ├── CreateItemRequest.php
│       └── UpdateItemRequest.php

database/migrations/
├── xxxx_create_list_items_table.php
└── xxxx_create_producto_historial_table.php

resources/js/
├── pages/
│   └── ListDetailPage.jsx
└── components/items/
    ├── AddItemInput.jsx
    ├── ItemRow.jsx
    ├── EditItemPanel.jsx
    └── UndoSnackbar.jsx

tests/
├── Feature/
│   └── ListItemControllerTest.php
└── Unit/Services/
    └── ListItemServiceTest.php
```

## Open Questions

None.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation
