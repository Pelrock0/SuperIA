# FEAT-EPIC7-PRICES — Price Estimation (Phase A)

**Complexity:** HIGH scope, MEDIUM implementation (12-16h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-701 | Price estimation: Layer 1 (personal history) → Layer 2 (catalog ranges); display as "35€ – 45€"; "Recalcular precios" button | Phase A implemented |
| HU-702 | Confirm real prices after purchase: total + per-item optional; per-item feeds Layer 1 | Implemented |

## Complexity Classification

- Layer pipeline: MEDIUM — 2 SQL queries per item
- Catalog seeding: LOW — one-time Haiku batch ($0.02 cost)
- Frontend: LOW — summary bar + expandable per-item breakdown

## Key Dependencies

- 1 migration: add `precio_min`, `precio_max` to `producto_catalogo`
- `producto_historial.precio_real` (Layer 1 source)
- Console command `prices:seed-catalog` (one-time, re-runnable)
- Zero new tables

## Design Decisions

- Phase A explicit: OFF + Claude real-time deferred to Phase B (user decision)
- On-demand recalculation ("Recalcular precios" button), not real-time on every item mutation
- Midpoint stored to `list_items.estimated_price`; min/max range in API response only
- `HU-702` per-item prices feed `producto_historial.precio_real` (Layer 1)
- Total-only confirm does NOT distribute to items (no artificial per-item data)

## Deviations

Phase B (OFF + Claude real-time) explicitly deferred by user decision.

## Review Findings

- No new attack surface
- Per-item resolution: 2 queries × N items (acceptable for N≤25)
- Non-blocking: refactor to chain-of-responsibility when Phase B adds layers
- 826 tests passing (32 new: 19 backend + 13 frontend)
