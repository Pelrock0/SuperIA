# Technical Design: FEAT-EPIC2-LISTS

## Overview

Standard CRUD feature for shopping lists. One new table (`shopping_lists`), one service (`ShoppingListService`), one controller, and a React dashboard page with list card components. The freemium limit (max 3 active lists) is enforced atomically in a DB transaction. Lists belong to a user via `user_id` foreign key. Archive/restore toggles a status enum. All endpoints are behind existing JWT auth + JwtVersionCheck middleware.

This feature integrates with Epic 1's `AccountDeletionService` to cascade-delete lists on user account deletion.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | ShoppingList model, status enum, user relationship | `App\Models\ShoppingList`, `App\Enums\ListStatus`, `App\Enums\ListCategory` |
| Services | CRUD orchestration, freemium limit check, archive/restore | `App\Services\ShoppingListService` |
| Controllers/API | HTTP interface, request validation, JSON responses | `App\Http\Controllers\ShoppingListController` |
| Frontend | Dashboard page, list cards, create modal, empty state | `DashboardPage`, `ListCard`, `CreateListModal`, `EmptyState` |

### Data Flow

#### Create List
```
1. POST /api/lists { name, emoji?, category? }
2. Controller: validate via CreateListRequest
3. Service: DB::transaction {
     a. Count active lists for user (SELECT COUNT with lock)
     b. If count >= 3: throw FreemiumLimitException
     c. Create ShoppingList
   }
4. Return 201 with list data
```

#### Dashboard Load
```
1. GET /api/lists
2. Controller: delegate to service
3. Service: Query user's lists ordered by status (active first) then updated_at desc
4. Return lists grouped: { active: [...], archived: [...] }
```

#### Archive/Restore
```
1. PATCH /api/lists/{id}/archive  OR  /api/lists/{id}/restore
2. Controller: validate ownership
3. Service: update status (active <-> archived)
   - Restore: check freemium limit before restoring
4. Return updated list
```

### Transaction Boundaries

| Operation | Transaction Scope | Reason |
|-----------|-------------------|--------|
| Create list | Count active + create | Prevent race condition on freemium limit |
| Restore list | Count active + update status | Prevent exceeding limit via concurrent restores |
| Delete list | Delete record | Single operation, no transaction needed |
| Archive | Update status | Single operation, no transaction needed |

## Data Model

### New Table: `shopping_lists`

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | bigIncrements | PK | |
| `user_id` | foreignId | FK users.id, ON DELETE CASCADE, index | Owner |
| `name` | string(60) | NOT NULL | List name |
| `emoji` | string(10) | NULLABLE | Unicode emoji |
| `category` | enum | NULLABLE: supermercado, mercado, online, farmacia, otro | List category |
| `status` | enum | NOT NULL, default 'active': active, archived | List state |
| `is_shared` | boolean | NOT NULL, default false | Placeholder for Epic 4 |
| `items_total` | unsignedInteger | NOT NULL, default 0 | Placeholder for Epic 3 |
| `items_completed` | unsignedInteger | NOT NULL, default 0 | Placeholder for Epic 3 |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

Indexes: `user_id` (implicit via foreignId), composite `(user_id, status)` for dashboard query.

### Enums

```php
// App\Enums\ListStatus
enum ListStatus: string {
    case Active = 'active';
    case Archived = 'archived';
}

// App\Enums\ListCategory
enum ListCategory: string {
    case Supermercado = 'supermercado';
    case Mercado = 'mercado';
    case Online = 'online';
    case Farmacia = 'farmacia';
    case Otro = 'otro';
}
```

### API Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/lists` | GET | JWT | Get all user's lists (active + archived) |
| `/api/lists` | POST | JWT | Create new list |
| `/api/lists/{id}` | GET | JWT | Get single list detail |
| `/api/lists/{id}` | PUT | JWT | Update list (name, emoji, category) |
| `/api/lists/{id}/archive` | PATCH | JWT | Archive list |
| `/api/lists/{id}/restore` | PATCH | JWT | Restore list |
| `/api/lists/{id}` | DELETE | JWT | Delete list permanently |

### API Response Format

```json
// GET /api/lists
{
  "data": {
    "active": [
      { "id": 1, "name": "Compra semanal", "emoji": "🛒", "category": "supermercado", "status": "active", "is_shared": false, "items_total": 0, "items_completed": 0, "updated_at": "2026-04-10T..." }
    ],
    "archived": []
  }
}

// POST /api/lists (201)
{ "data": { "id": 1, "name": "...", ... } }

// Error: freemium limit
{ "error": { "code": "FREEMIUM_LIMIT", "message": "Has alcanzado el limite de 3 listas activas..." } }
```

## Integration with Epic 1

### AccountDeletionService Update

Add list cleanup to `AccountDeletionService::initiateDelete()`:

```php
// Inside the DB::transaction, before soft-deleting user:
$user->shoppingLists()->delete();
```

Since `shopping_lists.user_id` has `ON DELETE CASCADE`, lists will auto-delete on user hard-delete. For soft-delete phase, explicitly delete lists in the transaction.

### User Model

Add relationship:

```php
public function shoppingLists(): HasMany
{
    return $this->hasMany(ShoppingList::class);
}
```

## Security

- **Ownership check**: Every endpoint verifies `$list->user_id === auth('api')->user()->id`. Use route model binding scoped to user.
- **No IDOR**: Lists fetched via `auth('api')->user()->shoppingLists()`, never by raw ID.
- **Validation**: FormRequests for create/update. Category validated against enum values.

## Performance

- **Dashboard query**: Single query with `where('user_id', $userId)->orderByRaw("FIELD(status, 'active', 'archived')")->orderBy('updated_at', 'desc')`. No N+1.
- **Freemium count**: `SELECT COUNT(*) FROM shopping_lists WHERE user_id = ? AND status = 'active' FOR UPDATE` — index on `(user_id, status)`.
- **No caching needed** at current scale.

## Frontend Architecture

### New Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `DashboardPage` | `resources/js/pages/DashboardPage.jsx` | Main page at `/app`, fetches and displays lists |
| `ListCard` | `resources/js/components/lists/ListCard.jsx` | Individual list card with options menu |
| `CreateListModal` | `resources/js/components/lists/CreateListModal.jsx` | Modal/form to create new list |
| `EmptyState` | `resources/js/components/lists/EmptyState.jsx` | Shown when user has no lists |

### Route Changes

Update `app.jsx`:
```jsx
<Route path="/app" element={<DashboardPage />} />
<Route path="/app/listas/:id" element={<div>List detail (Epic 3)</div>} />
```

### State Management

Local state in `DashboardPage` via `useState` + `useEffect`. No global state needed — lists are fetched on mount and after mutations. Each mutation (create, archive, restore, delete) refetches the list.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Soft delete for lists | Recoverable, consistent with user soft-delete | More complex queries, need to filter deleted | **Rejected**: PRD says deletion is permanent and immediate |
| Hard delete for lists | Simple, matches PRD ("permanente e inmediata") | No recovery | **Selected** |
| Enum for status | Type-safe, validated by DB | Migration needed to add values later | **Selected**: only 2 states (active/archived), extensible via migration |
| String for status | Flexible | No DB-level validation | **Rejected** |
| Separate archived table | Clean queries | More complex, duplication | **Rejected**: enum status is simpler |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Freemium limit race condition | Low | Low | Atomic transaction with SELECT FOR UPDATE |
| Orphaned lists after user hard-delete | Medium | Low | ON DELETE CASCADE on FK + explicit delete in soft-delete transaction |
| Emoji rendering issues across browsers | Low | Low | Unicode standard emojis only, no custom icon set |

## File Structure (new files)

```
app/
├── Enums/
│   ├── ListStatus.php
│   └── ListCategory.php
├── Models/
│   └── ShoppingList.php
├── Services/
│   └── ShoppingListService.php
├── Http/
│   ├── Controllers/
│   │   └── ShoppingListController.php
│   └── Requests/
│       ├── CreateListRequest.php
│       └── UpdateListRequest.php

database/migrations/
└── xxxx_create_shopping_lists_table.php

resources/js/
├── pages/
│   └── DashboardPage.jsx (replace placeholder)
└── components/lists/
    ├── ListCard.jsx
    ├── CreateListModal.jsx
    └── EmptyState.jsx

tests/
├── Feature/
│   └── ShoppingListControllerTest.php
└── Unit/Services/
    └── ShoppingListServiceTest.php
```

## Open Questions

None.

## Implementation Notes

1. Use `php artisan make:model ShoppingList -m` for model + migration.
2. Enums as PHP 8.1+ backed enums — cast in model via `$casts`.
3. Route model binding: scope `ShoppingList` to authenticated user to prevent IDOR.
4. AccountDeletionService: add `$user->shoppingLists()->delete()` inside existing transaction.
5. Frontend: pull Stitch "Dashboard" design via MCP for DashboardPage.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation
- Required Artifacts: 01-scope.md, 02-prd.md, 03-technical-design.md
