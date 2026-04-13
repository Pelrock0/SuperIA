# Scope Analysis: FEAT-EPIC8-DUPLICATES

## Feature Request

Implement HU-801 (Detectar ítems duplicados o similares) and HU-802 (Agrupar ítems por categoría automáticamente) from `docs/Superia_HU_v3.md` § Épica 8.

**HU-801**: 2-layer duplicate detection on item add: Layer 1 Levenshtein (>90% = duplicate, instant), Layer 2 Claude (50-90% = semantic check). Non-blocking warning. User can ignore or increment existing item's quantity.

**HU-802**: Auto-infer category when item has no category. Visual grouping already exists in `ListDetailPage` (CATEGORY_LABELS + grouped rendering). Missing: auto-inference + user correction + category reorder.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **MEDIUM** |
| Estimated Effort | 10–14 hours |
| Confidence | High |

## Justification

**MEDIUM** because:

- **HU-801 Layer 1** is trivial: PHP `similar_text()` or `levenshtein()` on a list of <25 items. No DB, no API, instant.
- **HU-801 Layer 2** reuses existing Claude infra (6th integration, pattern fully established). Single Haiku call for one pair of product names. Fast (<1s).
- **HU-802 is mostly already done**: `ListDetailPage` already groups by category. `ListItem` has `category` column. The `CreateItemRequest` already validates against `ProductCategory` enum. What's missing: auto-inference when category is null (lookup from `producto_catalogo` or Claude) + category display order preference.
- No new tables, no new migrations (potentially 1 for category order preference, but that could be user-level JSON or `users` column).
- The main risk is the UX of the duplicate warning: it must be non-blocking, dismissable, and appear in <1 second.

**Existing infrastructure**:
- `ListItem.category` — exists, nullable, `ProductCategory` enum
- `ListDetailPage` — already groups items by category
- `producto_catalogo.categoria` — 250+ products with pre-assigned categories
- All Claude infra (sanitizer, budget, circuit breaker, tracker) — established

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | Levenshtein is built-in PHP. Claude pattern is established. Category lookup from catalog is a simple query. |
| Data | Low | No new tables. Category data already exists in `producto_catalogo`. |
| Security | Low | Layer 2 Claude call uses existing guardrails. Duplicate check input is just two product names (short strings). |
| Performance | Medium | HU-801 requires <1 second. Layer 1 is instant. Layer 2 (Claude Haiku) is ~0.5-1s. If Layer 2 is triggered, it's close to the 1s target. May need to skip Layer 2 and accept textual-only detection for V1. |
| Operational | Low | No new scheduled jobs. No external APIs. |

## Affected Areas

### Backend
- `app/Services/DuplicateDetectionService.php` (NEW) — Layer 1 + Layer 2 pipeline
- `app/Services/CategoryInferenceService.php` (NEW) — auto-categorize from catalog or Claude
- `app/Support/Ai/ClaudeClientInterface.php` — new method `checkDuplicate` (optional, may defer Layer 2)
- `app/Http/Controllers/ListItemController.php` — modify item creation to include duplicate check
- Possibly: new endpoint `POST /api/lists/{list}/check-duplicate` OR modify existing store response

### Frontend
- `resources/js/components/items/DuplicateWarning.jsx` (NEW) — non-blocking alert
- `resources/js/pages/ListDetailPage.jsx` — integrate warning into add-item flow
- `resources/js/components/items/AddItemInput.jsx` — trigger duplicate check before/after submit

### Tests
- Service tests for Levenshtein + Claude duplicate detection
- Category inference tests
- Frontend tests for warning component

## Resolved Decisions (S1, 2026-04-12)

| # | Decision | Source |
|---|----------|--------|
| 1 | **Duplicate check**: option (c) — **JS client-side** Layer 1 (`similar_text` in JS). Item NOT added until user confirms. No Layer 2 endpoint in V1 (deferred per #3). | User |
| 2 | **Auto-categorization**: option (c) — **catalog lookup only** V1. Claude inference deferred. | User |
| 3 | **Claude Layer 2**: **deferred to V2**. Layer 1 textual similarity only. <1s target non-negotiable. | User |
| 4 | **Category reorder**: option (c) — **deferred**. Fixed order per existing `CATEGORY_LABELS`. | User |
| 5 | **Warning UX**: option (b) — **inline** below input, two buttons: "Añadir de todas formas" + "Incrementar cantidad". Non-blocking. Item not added yet. | User |
| 6 | **Incrementar cantidad**: option (a) — **increment existing**, don't add duplicate. | User |
| 7 | **Threshold**: `similar_text` **>80%**. | User |

> All resolved. No Claude Layer 2, no Claude categorization, no category reorder in V1. Pure client-side duplicate detection + catalog-based auto-categorization.

## Open Questions (historical — superseded)

1. **Duplicate check: separate endpoint or inline?**
   - (a) **Separate `POST /api/lists/{list}/check-duplicate`**: frontend calls before submitting. Decoupled. Two HTTP calls per item add.
   - (b) **Inline in `POST /api/lists/{list}/items` response**: add a `duplicate_warning` field to the response. One HTTP call but the item is already created — warning comes after the fact.
   - (c) **Frontend-only Layer 1**: Levenshtein computed client-side in JS (items already in state). Claude Layer 2 via a separate endpoint only if needed.
   - Recommend **(c)**: Layer 1 in JS is instant, no HTTP call. Layer 2 as a separate endpoint for the rare 50-90% cases.

2. **HU-802 auto-categorization source**:
   - (a) **Lookup from `producto_catalogo`**: `WHERE LOWER(nombre) = LOWER(item.name)` → use catalog's category. Free, instant, covers ~250 products.
   - (b) **Claude inference**: for items not in catalog. Another Claude call.
   - (c) **Catalog first, skip Claude for V1**: same approach as Epic 7 Phase A (layers without external API).
   - Recommend **(c)**: catalog lookup only. Claude inference for categorization is low value — most items match the catalog, and users can manually set category for the rare ones that don't.

3. **Layer 2 Claude for duplicate detection: include or defer?**
   - (a) Include: Claude checks "are 'Tomates cherry' and 'Tomates' the same product?" — useful but adds latency + cost.
   - (b) Defer to V2: Layer 1 textual similarity only. >90% triggers warning. <90% = no warning.
   - Risk: a user writes "Tomates" when they have "Tomate cherry" (Levenshtein ~73%) — no warning without Claude.
   - Recommend: **user decision needed**. The <1s target makes Claude Layer 2 risky.

4. **Category reorder (HU-802 AC-5)**: "El usuario puede reordenar el orden de las categorías"
   - (a) Per-user preference stored in `users` table (JSON column or separate table).
   - (b) Fixed display order (hardcoded, same for all users).
   - (c) Defer to V2: fixed order for now.
   - Recommend **(c)**: fixed order matching the existing `CATEGORY_LABELS` object in ListDetailPage. Per-user reorder adds complexity for minimal UX value.

5. **Duplicate warning UX**:
   - (a) Toast notification at top of screen.
   - (b) Inline warning below the add-item input (like the pre-fill hint in Epic 5A).
   - (c) Modal with "Añadir nuevo" / "Incrementar cantidad del existente" buttons.
   - Recommend **(b)**: inline warning with two action buttons, consistent with existing inline feedback patterns. Non-blocking — the item was NOT added yet (if using client-side check before submit).

6. **"Incrementar cantidad" action**: what exactly happens?
   - (a) Remove the new item (don't add), increment the existing item's quantity by the new quantity.
   - (b) Add both items and let the user manually merge.
   - Recommend **(a)**: the whole point is to avoid duplicates.

7. **Similarity threshold**: Levenshtein or `similar_text`?
   - Levenshtein returns edit distance (absolute), `similar_text` returns percentage (relative).
   - Recommend `similar_text` with threshold >80% (not 90% as the HU suggests — 90% is too strict for short product names where 1 character difference = 10-15% dissimilarity).

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] Require PRD (MEDIUM/HIGH → STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1 PENDING
- Next Step: STEP 2 — PRD Writing
- Required Artifacts: `01-scope.md`
