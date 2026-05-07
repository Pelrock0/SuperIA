# FEAT-EPIC8-DUPLICATES — Duplicate Detection + Auto-Categorization

**Complexity:** MEDIUM (10-14h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-801 | Duplicate detection: >80% similarity warning, "Añadir de todas formas" + "Incrementar cantidad" | Client-side JS only (V1) |
| HU-802 | Auto-categorize items: catalog lookup on create, null if unknown | Backend inline |

## Complexity Classification

- Client-side detection: LOW — JS `similarText()` algorithm, ~30 LOC
- Auto-categorization: LOW — 1 extra query on item create
- Increment endpoint: LOW — new PATCH endpoint

## Key Dependencies

- Zero migrations, zero new tables
- `producto_catalogo.categoria` (250+ pre-populated)
- `ListItem.category` (nullable enum, ProductCategory)

## Design Decisions

- Duplicate detection 100% client-side for instant (<1ms) response; no server round-trip
- 80% threshold (Ratcliff/Obershelp algorithm, configurable)
- Warning: inline below input, non-blocking, two buttons
- Increment: new endpoint `PATCH /api/lists/{list}/items/{item}/increment-quantity`
- Auto-categorization: backend inline lookup, null if not found (user sets manually)
- V1 deliberately avoids Claude for <1s performance target

## Deviations

- V1 skips Claude semantic duplicate detection and Claude categorization inference (V2 deferred)

## Review Findings

- Leanest feature: zero Claude, zero migrations
- Client-side O(N) on ≤25 items = <1ms
- 852 tests passing (26 new)
