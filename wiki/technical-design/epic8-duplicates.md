# Technical Design — FEAT-EPIC8-DUPLICATES

## Architecture

100% client-side duplicate detection (zero backend involvement). Backend adds: auto-categorization inline on item create, and one new increment-quantity endpoint.

## Data Flow

```
Duplicate detection (client-side, typing in item input):
  User types → JS similarText(newName, existingItem.name) for each existing item
  IF any similarity > 0.80:
    Show inline warning: "Posible duplicado de X"
    Two buttons: "Añadir de todas formas" | "Incrementar cantidad"
  
  "Añadir de todas formas":
    → Normal POST /api/lists/{list}/items (no server-side check)
  
  "Incrementar cantidad":
    → PATCH /api/lists/{list}/items/{matched_item}/increment-quantity
      → ListItemService::incrementQuantity()
        → UPDATE list_items SET quantity = quantity + 1
           (or quantity = 1 if quantity was null)
        → syncCounters(list)

Auto-categorization (backend, on item create):
  POST /api/lists/{list}/items { name, ... }
  → IF category null:
      CategoryInferenceService::inferFromCatalog(name)
        SELECT categoria FROM producto_catalogo
        WHERE LOWER(nombre) = LOWER(?) LIMIT 1
      → Set category if found; leave null if not found
```

## Key Decisions

| Decision | Rationale |
|----------|-----------|
| 100% client-side detection | <1ms target; no network round-trip; O(N) on ≤25 items is negligible |
| 80% threshold | Tuned by user; catches "leche entera" vs "leche" while ignoring "pollo" vs "polla" |
| Catalog-only categorization | No AI for <1s performance target; user can set manually if wrong |
| Increment endpoint (not re-create) | Keeps item history intact; avoids duplicate entries |

## Gotchas

- No server-side duplicate check — user can bypass by clicking "Añadir de todas formas" (intentional)
- `similarText()` is Ratcliff/Obershelp algorithm; not fuzzy phonetic — "leche" vs "lche" = high similarity, "leche" vs "milk" = low (no translation)
- Auto-categorization null case is common (only ~250 products in catalog); user manually sets category
- `existingItems` array recreated on every render — memoize if performance becomes concern (non-blocking)
