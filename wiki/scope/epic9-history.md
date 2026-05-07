# FEAT-EPIC9-HISTORY — Completed Lists + Statistics

**Complexity:** MEDIUM (12-16h) | **Status:** S5-PASS (all reviews)

## User Stories

| ID | Story | Notes |
|----|-------|-------|
| HU-901 | History: `/app/historial`, paginated (20/page) archived lists, name+date+count+price, "Duplicar" button | Implemented |
| HU-902 | Statistics: monthly spend bar chart (6 months), top 5 categories, top 10 products; gate: ≥3 lists | Implemented |

## Complexity Classification

- Read-heavy aggregation: MEDIUM — subquery-per-list price total (acceptable N=20)
- Recharts visualizations: LOW — standard bar + pie charts
- Duplicate list: LOW — clone archived → new active (freemium-gated)

## Key Dependencies

- Zero migrations, zero new tables
- Reuses archived lists (`status='archived'`)
- `list_items.estimated_price` (from Epic 7)
- `producto_historial` aggregates
- recharts library

## Design Decisions

- Price total: confirmed prices take precedence over estimated
- Duplicate clones items without `is_purchased` or `estimated_price` (clean slate)
- Statistics on same page as history (not separate route)
- 6-month span hardcoded (V1)
- `has_enough_data` boolean gates statistics display (≥3 lists minimum)

## Deviations

- Stitch "Historial" screen not fetched during implementation (MCP deferred)

## Review Findings

- Per-list price subqueries noted (non-blocking optimization for V2)
- Pre-existing flaky `AuthServiceTest` (unrelated to this feature)
- 878 total tests (26 new)
