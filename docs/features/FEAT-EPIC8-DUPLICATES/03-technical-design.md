# Technical Design: FEAT-EPIC8-DUPLICATES

## Overview

Feature split en dos superficies independientes: (1) **detección de duplicados 100% client-side** (JS `similarText()` helper comparando el nombre del nuevo item contra los items ya en React state, threshold 80%, warning inline antes de submit — zero HTTP calls para la detección) y (2) **auto-categorización backend** via lookup de `producto_catalogo.categoria` por nombre exacto (LOWER match), integrada inline en la creación de items existente. Complemento: un nuevo endpoint `PATCH increment-quantity` para el botón "Incrementar cantidad" del warning.

Zero migraciones. Zero tablas nuevas. Zero Claude. Zero APIs externas. La feature más lean del proyecto.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | `ProductCategory` enum (existing) | — |
| Services | Category lookup from catalog | `App\Services\CategoryInferenceService` (NEW, 1 method) |
| Controllers/API | Thin: increment quantity + modified item store | `ListItemController` (modified), `IncrementQuantityRequest` (NEW) |
| Frontend | Duplicate detection + warning UI + auto-cat request | `similarText()` helper (NEW), `DuplicateWarning.jsx` (NEW), `AddItemInput.jsx` (modified) |

### Data Flow

#### Duplicate detection (100% client-side)

```
1. User types item name in AddItemInput
2. User clicks "Añadir" (or presses Enter)
3. BEFORE any HTTP call, JS runs:
   a. Flatten all items from React state (already loaded in ListDetailPage)
   b. For each existing item: compute similarText(newName, existingName)
   c. If any score > 0.80 → set matchedItem in state → show DuplicateWarning
   d. If no match > 0.80 → proceed with normal item creation
4. DuplicateWarning shows:
   - "Ya tienes {match.name} en la lista"
   - Button "Añadir de todas formas" → calls existing POST /api/lists/{id}/items
   - Button "Incrementar cantidad" → calls PATCH /api/lists/{id}/items/{match.id}/increment-quantity
5. Either action dismisses the warning and refreshes the list
```

#### Auto-categorization (backend, inline)

```
1. POST /api/lists/{list}/items receives {name, quantity, unit} WITHOUT category
2. ListItemController::store checks if category is null/missing
3. If null → calls CategoryInferenceService::infer($name)
4. Service: SELECT categoria FROM producto_catalogo WHERE LOWER(nombre) = LOWER($name) LIMIT 1
5. If found → set category on the item before create
6. If not found → leave category null (user can set manually)
7. Item created with auto-inferred category (or null)
```

#### Increment quantity (new endpoint)

```
1. PATCH /api/lists/{list}/items/{item}/increment-quantity
2. Validates: {quantity: required|numeric|min:0.01}
3. Ownership check: item.shopping_list_id === list.id AND list.user_id === auth.id
4. item.quantity += request.quantity (or set to request.quantity if item.quantity was null)
5. Return updated item
```

### Transaction Boundaries

No explicit transactions needed. All operations are single-row UPDATEs/INSERTs.

## Data Model

### New Tables
None.

### Migrations
None.

### API Changes

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/lists/{list}/items/{item}/increment-quantity` | PATCH | `auth:api` + ownership | Increment item quantity by given amount |

Existing `POST /api/lists/{list}/items` modified to auto-infer category when not provided.

### Config
No new config. Threshold (80%) is a JS constant, not server config.

## Performance

- **Duplicate detection**: O(N) string comparisons where N = items in list (max ~25). Each `similarText` is O(m*n) where m,n are string lengths (~20 chars). Total: <1ms. Well under the 1-second target.
- **Auto-categorization**: 1 indexed query on `producto_catalogo.nombre` (~250 rows). <5ms.
- **Increment**: 1 UPDATE query. <5ms.

## JS similarText Helper

```javascript
// Ratcliff/Obershelp algorithm — same concept as PHP's similar_text()
function similarText(a, b) {
    const s1 = a.toLowerCase().trim();
    const s2 = b.toLowerCase().trim();
    if (s1 === s2) return 1.0;
    if (s1.length === 0 || s2.length === 0) return 0.0;

    let matching = 0;
    // Find longest common substring iteratively
    function lcs(str1, str2) {
        let longest = 0, start1 = 0, start2 = 0;
        for (let i = 0; i < str1.length; i++) {
            for (let j = 0; j < str2.length; j++) {
                let k = 0;
                while (i + k < str1.length && j + k < str2.length && str1[i + k] === str2[j + k]) k++;
                if (k > longest) { longest = k; start1 = i; start2 = j; }
            }
        }
        return { longest, start1, start2 };
    }

    function recurse(str1, str2) {
        const { longest, start1, start2 } = lcs(str1, str2);
        if (longest === 0) return 0;
        let count = longest;
        if (start1 > 0 && start2 > 0) count += recurse(str1.substring(0, start1), str2.substring(0, start2));
        const end1 = start1 + longest, end2 = start2 + longest;
        if (end1 < str1.length && end2 < str2.length) count += recurse(str1.substring(end1), str2.substring(end2));
        return count;
    }

    matching = recurse(s1, s2);
    return (2 * matching) / (s1.length + s2.length);
}
```

Returns a float 0.0–1.0. Threshold: `> 0.80`.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| **Client-side duplicate detection** | Instant, no HTTP, no latency | Can't do semantic comparison | **Selected** — V1 is textual only |
| Server-side detection | Could use Claude for semantics | Adds latency, HTTP call before every item add | Rejected for V1 |
| **Catalog-only categorization** | Instant, free, covers 250 products | Misses items not in catalog | **Selected** — user sets manually for unknown items |
| **80% threshold** | Catches "Tomates" / "tomates" (100%), "Leche" / "Leche entera" (~70% = miss) | May miss some true duplicates | **Selected** per user decision |
| **Inline warning (not modal)** | Non-blocking, doesn't interrupt flow | Less prominent than modal | **Selected** — consistent with inline patterns |

## Implementation Notes

### S4 execution order
1. `CategoryInferenceService` (1 method, 1 query)
2. Modify `ListItemController::store` to auto-categorize
3. Create `IncrementQuantityRequest` + add increment action to `ListItemController`
4. Add route
5. Backend tests
6. Run backend suite
7. `similarText()` JS helper + unit test
8. `DuplicateWarning.jsx` component + test
9. Modify `AddItemInput.jsx` to integrate detection + warning
10. Frontend tests
11. Run frontend suite

### Frontend work identified
YES — S4-BOTH. `has_ui_changes = YES`.

## Transition
- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation (S4-BOTH)
