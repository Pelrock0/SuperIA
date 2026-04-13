# PRD: FEAT-EPIC3-ITEMS - Items dentro de una Lista

## Business Objective

Users with shopping lists (Epic 2) need to add, manage, and track products within each list. This is the core utility of Superia — without items, lists are empty containers. This epic also establishes the `producto_historial` data pipeline that feeds all AI features (Epics 5-8).

## Problem Statement

Users can create and manage shopping lists but cannot add products to them. The list detail page is a placeholder. There is no way to track what to buy, mark items as purchased, or build purchase history for future AI-powered suggestions.

## Scope

### In Scope

- **HU-301**: List detail page with items grouped by product category, pending items above completed, progress counter (X de Y)
- **HU-302**: Add item to list (name required max 80 chars, optional: quantity, unit kg/g/L/ml/ud/pack, product category from 10 predefined, estimated price)
- **HU-303**: Toggle item as purchased (checkbox, strikethrough visual, moves to bottom, records in `producto_historial`)
- **HU-304**: Edit item (panel: name, quantity, unit, category, estimated price)
- **HU-305**: Delete item (button, no confirmation, undo via snackbar 5 seconds, frontend-only undo)
- **HU-306**: Clear all completed items (menu action, confirmation dialog, only removes checked items, resets progress)
- `producto_historial` table creation (user_id, producto_nombre, categoria, cantidad, unidad, fecha_compra, lista_id, precio_real nullable)
- Update `shopping_lists.items_total` and `items_completed` counters on every item operation
- 10 product categories: Frutas y verduras, Carnes y pescados, Lacteos y huevos, Panaderia, Bebidas, Congelados, Limpieza, Higiene personal, Conservas, Otros

### Out of Scope

- AI suggestions while typing (Epic 5 — HU-501)
- Duplicate detection (Epic 8 — HU-801)
- Automatic category inference (Epic 8 — HU-802)
- Price estimation (Epic 7 — HU-701)
- Shared list real-time sync (Epic 4)
- Drag-and-drop item reordering within category
- Item images or barcodes
- Swipe gesture for delete on mobile (will use button; swipe is complex and fragile)

## Acceptance Criteria

### AC-1: List detail — items grouped by category
- **Given**: A list with items in different categories
- **When**: User navigates to `/app/listas/:id`
- **Then**: Items are displayed grouped by product category. Pending items appear above completed items within each category.

### AC-2: List detail — progress counter
- **Given**: A list with 3 items, 1 completed
- **When**: User views the list
- **Then**: Progress shows "1 de 3 items comprados"

### AC-3: List detail — empty list
- **Given**: A list with no items
- **When**: User views the list
- **Then**: Message "Esta lista esta vacia. Anade tu primer producto." with the add input visible.

### AC-4: Add item — full data
- **Given**: User on list detail page
- **When**: They type "Leche entera", quantity 2, unit "L", category "Lacteos y huevos", price 1.50, and submit
- **Then**: Item appears at the end of its category group. Input clears for next item. `items_total` increments by 1.

### AC-5: Add item — name only
- **Given**: User on list detail page
- **When**: They type "Pan" and press Enter
- **Then**: Item created with name only, other fields null. Appears in "Otros" category (or uncategorized).

### AC-6: Add item — validation
- **Given**: User on list detail page
- **When**: They submit with empty name or name over 80 characters
- **Then**: Validation error shown. Item not created.

### AC-7: Mark item as purchased
- **Given**: A pending item in the list
- **When**: User clicks the checkbox
- **Then**: Item shows strikethrough, moves to completed section. `items_completed` increments by 1. Entry created in `producto_historial` with: user_id, producto_nombre, categoria, cantidad, unidad, fecha_compra=now, lista_id.

### AC-8: Unmark item as purchased
- **Given**: A completed item
- **When**: User clicks the checkbox again
- **Then**: Strikethrough removed, item moves back to pending section. `items_completed` decrements by 1. No deletion from `producto_historial` (history is append-only).

### AC-9: All items completed
- **Given**: All items in the list are marked as completed
- **When**: The last item is checked
- **Then**: A congratulations message appears: "Lista completada!"

### AC-10: Edit item — open panel
- **Given**: A list item
- **When**: User taps on the item (not the checkbox)
- **Then**: An edit panel opens showing: name, quantity, unit, category, estimated price (all editable)

### AC-11: Edit item — save changes
- **Given**: Edit panel open
- **When**: User changes quantity to 3 and closes/saves
- **Then**: Changes saved. Item reflects updated values.

### AC-12: Edit item — cancel
- **Given**: Edit panel open
- **When**: User cancels
- **Then**: No changes saved. Panel closes.

### AC-13: Delete item — with undo
- **Given**: An item in the list
- **When**: User clicks the delete button on the item
- **Then**: Item disappears immediately. Snackbar appears: "Item eliminado. Deshacer" for 5 seconds. `items_total` decrements (and `items_completed` if was checked).

### AC-14: Delete item — undo
- **Given**: Snackbar showing after item deletion
- **When**: User clicks "Deshacer" within 5 seconds
- **Then**: Item is re-created with all original data. Counters restored.

### AC-15: Delete item — undo expires
- **Given**: Snackbar showing after item deletion
- **When**: 5 seconds pass without clicking undo
- **Then**: Snackbar disappears. Deletion is final.

### AC-16: Clear completed items
- **Given**: A list with 5 items, 3 completed
- **When**: User selects "Limpiar comprados" from list menu
- **Then**: Confirmation dialog: "Se eliminaran 3 items comprados. Continuar?"

### AC-17: Clear completed — execution
- **Given**: User confirms clearing
- **When**: Clearing executes
- **Then**: Only the 3 completed items are removed. 2 pending remain. `items_total` updates to 2, `items_completed` to 0.

### AC-18: producto_historial — data integrity
- **Given**: User marks "Leche" (2L, Lacteos) as purchased in list #5
- **When**: The historial record is created
- **Then**: Record contains: user_id (owner), producto_nombre="Leche", categoria="lacteos_huevos", cantidad=2, unidad="L", fecha_compra=now, lista_id=5, precio_real=null

### AC-19: Counter sync
- **Given**: Any item operation (add, delete, toggle, clear)
- **When**: The operation completes
- **Then**: `shopping_lists.items_total` and `items_completed` reflect the exact current count. Dashboard cards show updated counts.

## UX Decision

- **UX Designer Required**: YES
- **UX Artifacts**: Stitch MCP screens "Detalle lista" and "Anadir item" exist. Consumed at S4, reviewed at S5-UX.
- **Screens involved**:
  - `ListDetailPage` — Stitch "Detalle lista" → `/app/listas/:id`
  - `AddItemSheet` — Stitch "Anadir item" (inline input, not separate page)
  - Edit panel (inline in ListDetailPage)
  - Undo snackbar (floating component)

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Counter desync on concurrent operations | Technical | Update counters atomically in same transaction as item operation. Use DB count as source of truth, not increment/decrement. |
| producto_historial schema wrong for Epic 5 | Data | Schema reviewed with all Epic 5 HUs. Added precio_real for Epic 7. Append-only design. |
| Undo re-creation race condition | Technical | Frontend-only undo. If user navigates away during 5s window, deletion is final. Acceptable trade-off. |
| Large lists (100+ items) slow rendering | Performance | Category grouping done server-side. Frontend uses simple list rendering. Optimize if needed in future. |

## Assumptions

- Product categories are the 10 fixed values from HU-802 (no dynamic categories until Epic 8)
- Items without category appear under "Otros"
- producto_historial is append-only — no edits, no deletes (except on account deletion via RGPD)
- Undo is frontend-only: backend delete is immediate, frontend holds data 5s for re-creation
- Stitch MCP screens "Detalle lista" and "Anadir item" are accessible

## Open Questions

None. All resolved in S1.

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
