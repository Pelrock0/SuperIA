# Scope Analysis: FEAT-EPIC9-HISTORY

## Feature Request

Implement HU-901 (Ver historial de listas completadas) and HU-902 (Ver estadísticas de gasto y consumo) from `docs/Superia_HU_v3.md` § Épica 9.

**HU-901**: Dedicated history page showing archived lists with name, date, items count, price total. View detail of any past list. Duplicate a past list as base for a new one.

**HU-902**: Statistics section: monthly spend bar chart (6 months), top 5 categories pie chart, top 10 products. Minimum 3 completed lists to show. Disclaimer on estimates.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **MEDIUM** |
| Estimated Effort | 12–16 hours |
| Confidence | High |

## Justification

**MEDIUM** because:

- **Much of HU-901 already exists**: `ShoppingListController::index` returns archived lists. `ListDetailPage` renders any list by ID. DashboardPage shows archived section. What's new: a dedicated history page + "duplicate list" endpoint + price total display.
- **HU-902 is read-only aggregation**: no writes, no Claude, no external APIs. Just SQL queries + frontend charts.
- **No new tables, potentially 0 migrations**: archived lists already have `status = 'archived'`. Price data is in `list_items.estimated_price` (Epic 7). Product history in `producto_historial`.
- **Charting**: a simple visual (CSS bars, Tailwind-based) avoids adding a charting library. Or a lightweight one (chart.js).
- **Stitch screen** "Historial" exists.

**Existing infrastructure**:
- `ShoppingList` model with `ListStatus::Archived`
- `ListItem` with `estimated_price`
- `producto_historial` with purchase counts and categories
- `ShoppingListService::getListsForUser` returns both active/archived

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | All data already exists. Duplicate is a clone operation. Stats are aggregate queries. |
| Data | Low | No new tables. Aggregation on existing data. |
| Security | Low | All endpoints are read-only (except duplicate which creates a list — subject to freemium). Auth required. |
| Performance | Low-Medium | Stats queries aggregate across `producto_historial` (could be large). Need efficient GROUP BY queries. |
| Operational | Low | No scheduled jobs, no external APIs, no Claude. |

## Resolved Decisions (S1, 2026-04-12)

| # | Decision | Source |
|---|----------|--------|
| 1 | **Dedicated page**: `/app/historial`. Dashboard archived section stays as preview. | User |
| 2 | **Completed = archived**: `status='archived'` is completed. No new status. | User |
| 3 | **Duplicate**: "Copia de {original}", clean state, freemium limit. | User |
| 4 | **Price total**: confirmed total > estimated sum. | User |
| 5 | **Charts**: **recharts** (not pure CSS). Bar chart for monthly spend, percentage bars for categories. Worth the dependency for premium feel. | User |
| 6 | **Scope**: **both HU-901 + HU-902** included. Stats make history valuable. | User |
| 7 | **Stitch**: fetch historial screen via MCP in S4. | User |

> All resolved.

## Open Questions (historical)

1. **Dedicated history page vs enhance dashboard**:
   - (a) New page `/app/historial` with full history, search/filter, pagination.
   - (b) Just enhance the existing archived section on DashboardPage with price totals + duplicate button.
   - Recommend **(a)**: dedicated page per the HU and Stitch screen. Dashboard keeps showing the archived preview.

2. **"Listas completadas"**: what does "completed" mean?
   - (a) `status = 'archived'` (the existing archival mechanism).
   - (b) A new `status = 'completed'` (separate from archived).
   - Recommend **(a)**: archived IS completed. No new status needed.

3. **Duplicate list**: creates a new active list?
   - (a) New list with name "Copia de {original}", same items (name, quantity, unit, category), no purchased state, no prices. Subject to freemium 3-list limit.
   - Recommend **(a)**: clean copy, fresh state.

4. **Price total per list**: where does it come from?
   - (a) Sum of `list_items.estimated_price` (from Epic 7 estimation).
   - (b) The confirmed total from HU-702 (`confirm-prices` total).
   - (c) Both: show confirmed total if available, else estimated sum.
   - Recommend **(c)**: confirmed takes precedence, else estimated sum.

5. **HU-902 charting library**:
   - (a) `recharts` (React, lightweight, well-maintained).
   - (b) `chart.js` + `react-chartjs-2`.
   - (c) Pure CSS/Tailwind bars (no library, simpler but less polished).
   - Recommend **(c)** for V1: simple CSS bars. No new npm dependency. Pie chart becomes a simple "top 5 categories" list with percentage bars.

6. **HU-902 defer option**: should we defer HU-902 to keep scope tight?
   - (a) Include both HU-901 + HU-902.
   - (b) HU-901 only, defer stats to a later feature.
   - Recommend: **user decides**. HU-901 is ~8h, adding HU-902 is +4-6h.

7. **Stitch screen**: "Historial" exists in Stitch. Fetch via MCP in S4 frontend.

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1 PENDING
- Next Step: STEP 2 — PRD Writing
