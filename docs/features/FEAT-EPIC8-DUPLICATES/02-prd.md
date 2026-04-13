# PRD: FEAT-EPIC8-DUPLICATES - Detección de duplicados + auto-categorización (V1: sin Claude)

## Business Objective

Eliminar el error humano más común en listas de compra: añadir el mismo producto dos veces. Un usuario que escribe "Tomates" cuando ya tiene "tomates" en la lista debería ver un aviso antes de crear un duplicado. En paralelo, automatizar la categorización de items nuevos usando el catálogo existente (250+ productos con categoría pre-asignada) para que la agrupación visual en la lista funcione sin input manual del usuario.

V1 es deliberadamente simple: detección por similitud textual (client-side, instant, sin API) + categorización por lookup de catálogo (backend, sin Claude). Claude para duplicados semánticos y categorización avanzada diferidos a V2.

## Problem Statement

- **Duplicados accidentales**: un usuario que compra frecuentemente los mismos productos puede añadir "Leche" cuando ya tiene "leche" o "Leche entera" en la lista. Hoy no hay ningún aviso.
- **Items sin categoría**: cuando el usuario escribe un nombre manualmente (no desde autocompletado), el item se crea sin categoría. Esto rompe la agrupación visual y obliga al usuario a editar cada item para asignar categoría.
- **La agrupación visual ya funciona** pero depende de que los items tengan categoría. `ListDetailPage` ya agrupa por `CATEGORY_LABELS` — solo falta que los items lleguen con categoría auto-asignada.

## Scope

### In Scope

#### Frontend (principal)
- **Duplicate detection in `AddItemInput`**: antes de llamar al endpoint de crear item, comparar el nombre del nuevo item contra todos los items existentes en la lista usando `similar_text` con threshold >80%.
  - Si match encontrado → mostrar `DuplicateWarning` inline (debajo del input).
  - Warning shows: "Ya tienes {matchedItem.name} en la lista. ¿Quieres añadir otro o incrementar la cantidad?"
  - Dos botones: "Añadir de todas formas" (crea el item normalmente) y "Incrementar cantidad" (PATCH el item existente con +quantity).
  - El item NO se crea hasta que el usuario decide.
- **`DuplicateWarning.jsx`** (NEW): componente inline con nombre del match, dos action buttons, dismissable.
- **`similar_text` en JS**: función helper que implementa la comparación (case-insensitive, trimmed, accents normalized).
- Tests vitest para DuplicateWarning + duplicate detection logic.

#### Backend
- **Auto-categorization endpoint**: `POST /api/categorize-item` (auth) — body `{name: string}` → returns `{category: string|null}`. Lookup from `producto_catalogo` por nombre (LOWER match). Si no encuentra, returns null.
- **Modify `POST /api/lists/{list}/items`**: si el request no incluye `category`, llamar al categorization service inline y asignar la categoría al item antes de persistir.
- **`CategoryInferenceService`** (NEW): single method `infer(string $name): ?ProductCategory` — lookup from `producto_catalogo`.
- **Modify `PATCH /api/lists/{list}/items/{item}/toggle`**: no changes needed (toggle doesn't affect category).
- **Increment endpoint**: `PATCH /api/lists/{list}/items/{item}/increment-quantity` (auth + ownership) — body `{quantity: float}` → increments the item's quantity. Returns updated item. For the "Incrementar cantidad" button.
- Tests para CategoryInferenceService + increment endpoint.

#### No migration needed
- `ListItem.category` already exists (nullable, ProductCategory enum).
- `producto_catalogo.categoria` already populated.

### Out of Scope
- **Claude Layer 2 for semantic duplicate detection** — deferred to V2 per decision #3.
- **Claude for category inference** — deferred to V2 per decision #2.
- **Category reorder** — deferred to V2 per decision #4.
- **Cross-list duplicate detection** (duplicate across different lists) — only within the same list.
- **Duplicate detection on item edit** — only on item add.
- **Merge duplicates retroactively** — the feature only prevents new duplicates, doesn't clean up existing ones.
- **Fuzzy matching with accents/diacritics normalization** — V1 uses simple lowercased comparison. "Tomate" vs "tomaté" may not match. Acceptable.

## Acceptance Criteria

### AC-1: Duplicate warning appears on high similarity
- **Given**: a list contains an item "Tomates"
- **When**: the user types "tomates" in the add-item input and attempts to add
- **Then**: a `DuplicateWarning` appears inline below the input showing "Ya tienes Tomates en la lista" with two buttons. The item is NOT created yet.

### AC-2: "Añadir de todas formas" creates the item
- **Given**: the duplicate warning is showing for "tomates" matching "Tomates"
- **When**: the user clicks "Añadir de todas formas"
- **Then**: the item "tomates" is added to the list normally (via the existing create endpoint). The warning dismisses.

### AC-3: "Incrementar cantidad" updates existing item
- **Given**: the duplicate warning is showing, existing item "Tomates" has quantity 2
- **When**: the user clicks "Incrementar cantidad" (new item had quantity 1)
- **Then**: "Tomates" quantity becomes 3 (2 + 1). The new item is NOT created. The warning dismisses. The list refreshes.

### AC-4: No warning below 80% similarity
- **Given**: a list contains "Leche entera"
- **When**: the user adds "Mantequilla"
- **Then**: no warning appears. The item is created normally.

### AC-5: Similarity check is case-insensitive
- **Given**: a list contains "ARROZ"
- **When**: the user types "arroz"
- **Then**: the warning appears (case-insensitive comparison).

### AC-6: Auto-categorization on item add
- **Given**: `producto_catalogo` has "Leche entera" with category "lacteos_huevos"
- **When**: the user adds "Leche entera" without specifying a category
- **Then**: the item is created with `category = 'lacteos_huevos'` (auto-inferred from catalog).

### AC-7: Auto-categorization returns null for unknown items
- **Given**: "Salsa especial casera" is not in `producto_catalogo`
- **When**: the user adds it without category
- **Then**: the item is created with `category = null` (no forced inference).

### AC-8: Increment endpoint works
- **Given**: item "Tomates" with quantity 2 in user's list
- **When**: `PATCH /api/lists/{list}/items/{item}/increment-quantity` with `{quantity: 3}`
- **Then**: item quantity becomes 5. HTTP 200 with updated item.

### AC-9: Increment endpoint requires ownership
- **Given**: item in another user's list
- **When**: intruder calls increment
- **Then**: HTTP 404.

### AC-10: All endpoints require auth
- **Given**: unauthenticated request
- **When**: any new endpoint called
- **Then**: HTTP 401.

### AC-11: Warning is dismissable
- **Given**: duplicate warning is showing
- **When**: user clicks elsewhere or presses Escape
- **Then**: warning dismisses and the item input returns to normal state (no item created).

### AC-12: Detection runs in <1 second
- **Given**: client-side comparison
- **When**: user finishes typing and triggers add
- **Then**: the warning appears instantly (JS comparison, no HTTP call for Layer 1).

### AC-13: 100% backend test coverage
### AC-14: Frontend tests for DuplicateWarning and detection logic

## UX Decision

- **UX Designer Required**: NO
- **Stitch screen**: no dedicated duplicate/categorization screen. Modifications to existing `ListDetailPage` / `AddItemInput`.
- **S5-UX will run**: new inline warning component = UI change.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| 80% threshold too lenient (false positives) | Quality | User can always click "Añadir de todas formas". The warning is non-blocking. Adjust threshold via testing. |
| 80% threshold too strict (missed duplicates) | Quality | V2 Claude Layer 2 will catch semantic duplicates. V1 accepts the gap. |
| `similar_text` in JS behaves differently than PHP | Technical | Use the same algorithm (Ratcliff/Obershelp). Test with the same cases in both. |
| Auto-categorization mismatches ("Leche" → matches "Leche entera" category but user wanted "Leche desnatada") | Quality | Catalog lookup is exact (LOWER match). "Leche" won't match "Leche entera" — different strings. Acceptable for V1. |
| Increment creates unexpected quantity changes | UX | The warning clearly shows what will happen. User explicitly chooses. |

## Assumptions
- `producto_catalogo` covers ~250 products with categories pre-assigned.
- The existing `AddItemInput` component can be modified to add a pre-submit check.
- The list items are already available in frontend state (no extra fetch needed for comparison).
- `similar_text` JS implementation is straightforward (~20 LOC).

## Open Questions
None. All 7 from S1 resolved.

## Approval
- [ ] PRD approved by [user] on [date]

## Transition
- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
