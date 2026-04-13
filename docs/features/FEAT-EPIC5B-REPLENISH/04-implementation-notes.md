# Implementation Notes - FEAT-EPIC5B-REPLENISH

## Backend

### Summary

Replenishment alerts (HU-503) and complementary suggestions (HU-504) implemented on top of the Epic 5A AI foundation. Two new tables (`user_silenced_products`, `ai_dismissed_suggestions`), one new index on `producto_historial`, three new services, two new controllers, four new endpoints. `AiUsageTracker` refactored so the daily quota is shared across all AI operations (one Epic 5A test rewritten — non-functional behavior change). 473 backend tests passing.

### Files Created (backend)

| File | Purpose |
|------|---------|
| `database/migrations/2026_04_13_100000_create_user_silenced_products_table.php` | Permanent silence per user/product (UNIQUE constraint) |
| `database/migrations/2026_04_13_100001_create_ai_dismissed_suggestions_table.php` | TTL-based 24h dismiss with `(user_id, dismissed_until)` index |
| `database/migrations/2026_04_13_100002_add_user_lista_index_to_producto_historial.php` | New `(user_id, lista_id)` index for co-occurrence queries |
| `app/Models/UserSilencedProduct.php` | Model with `user` relation, no timestamps |
| `app/Models/AiDismissedSuggestion.php` | Model with `dismissed_until` cast |
| `database/factories/UserSilencedProductFactory.php` | Factory |
| `database/factories/AiDismissedSuggestionFactory.php` | Factory with `expired()` state |
| `app/Enums/ReplenishmentAction.php` | accepted, ignored, silenced (documentation enum) |
| `app/Enums/ComplementSource.php` | history, ai |
| `app/Services/ProductHistoryStatsService.php` | Helper: completed lists count, completed list IDs, active list with min items, distinct products |
| `app/Services/ReplenishmentSuggestionService.php` | Detection algorithm + ignore/silence/cache lifecycle |
| `app/Services/ComplementarySuggestionService.php` | 2-step co-occurrence + Claude fallback |
| `app/Http/Requests/AcceptReplenishmentRequest.php` | `producto_nombre` (1-80) + `list_id` (exists) |
| `app/Http/Requests/DismissReplenishmentRequest.php` | `producto_nombre` only |
| `app/Http/Requests/ComplementQueryRequest.php` | `product` + `list_id` |
| `app/Http/Controllers/ReplenishmentController.php` | index, accept, ignore, silence |
| `app/Http/Controllers/ComplementController.php` | index (single GET) |

### Files Modified (backend)

| File | Changes |
|------|---------|
| `config/ai.php` | Added `replenishment_factor = 0.80` under `thresholds` |
| `app/Support/Ai/AiUsageTracker.php` | `canUse` now uses `usedTodayAcrossAllOperations`. Renamed `usedToday` → `usedTodayForOperation` (kept for breakdown analytics). New `usedTodayAcrossAllOperations` method. |
| `app/Support/Ai/ClaudeClientInterface.php` | Added `suggestComplements(string $productName): array` |
| `app/Support/Ai/ClaudeClient.php` | Added `COMPLEMENTS_SYSTEM_PROMPT` constant + `suggestComplements()` impl + `parseComplementEntries()` private method (caps at 2) |
| `app/Support/Ai/FakeClaudeClient.php` | Added `cannedComplements`, `complementCalls`, `suggestComplements()` impl |
| `routes/api.php` | Added 4 new endpoints under existing JWT group + `throttle:60,1` on complement query |
| `tests/Unit/Support/Ai/AiUsageTrackerTest.php` | 3 tests updated for refactor: 2 renamed to `usedTodayForOperation`, 1 rewritten as `quota_is_shared_across_all_operations`. Added `usedTodayAcrossAllOperations` test. |

### Backend tests added

| Test File | Type | Tests | What it covers |
|-----------|------|-------|----------------|
| `tests/Unit/Services/ProductHistoryStatsServiceTest.php` | Unit | 9 | Completed lists count, IDs, empty/excluded states, active list check, distinct products |
| `tests/Unit/Services/ReplenishmentSuggestionServiceTest.php` | Unit | 17 | Empty when no active list, threshold gating, factor gating, silenced exclusion, dismissed exclusion, expired dismissed reappears, active list exclusion, cap at 3, sort by urgency, ignore/silence row creation, idempotency, cache TTL, cache invalidation on action |
| `tests/Unit/Services/ComplementarySuggestionServiceTest.php` | Unit | 12 | Local co-occurrence above/below threshold, exclude already-present, Claude fallback for new users, fallback excludes current items, budget cap, user quota, Claude error, PII anti-leak, cap at 2, sort by ratio, prompt sanitization |
| `tests/Feature/ReplenishmentControllerTest.php` | Feature | 13 | Auth required on every endpoint, empty when no list, returns suggestions, accept creates item, accept 403 cross-user, accept 422 validation, ignore row creation, silence row creation, silence scoping |
| `tests/Feature/ComplementControllerTest.php` | Feature | 5 | Auth, validation, 403 cross-user, local happy path, AI fallback path |
| `tests/Unit/Support/Ai/ClaudeClientTest.php` (extended) | Unit | 5 new | `suggestComplements`: valid response, cap at 2, missing API key, invalid JSON, sends product in message |

**Backend total**: 473 tests (411 previous + 62 new for Epic 5B).

### Files Created (frontend)

| File | Purpose |
|------|---------|
| `resources/js/lib/replenishmentApi.js` | `fetchReplenishmentSuggestions`, `acceptReplenishment`, `ignoreReplenishment`, `silenceReplenishment` |
| `resources/js/lib/complementsApi.js` | `fetchComplements(product, listId)` |
| `resources/js/components/dashboard/ReplenishmentBanner.jsx` | Dashboard banner with up to 3 cards, accept/ignore/silence actions, opens SelectListModal when multiple lists |
| `resources/js/components/dashboard/SelectListModal.jsx` | Modal for choosing destination list when accepting with >1 active list |
| `resources/js/components/items/ComplementaryChip.jsx` | Inline chip below recently added item, fetches `complementsApi`, auto-hide 30s, accept fires callback |

### Files Modified (frontend)

| File | Changes |
|------|---------|
| `resources/js/pages/DashboardPage.jsx` | Imports + renders `ReplenishmentBanner` above the lists section, only when `hasLists` is true. Passes `activeLists` and `onAction={fetchLists}` |
| `resources/js/pages/ListDetailPage.jsx` | Imports `ComplementaryChip`. Tracks `complementFor` state. On successful `handleAdd`, sets `complementFor` to the added product name. Renders chip below `AddItemInput` when set. New `handleComplementAccept` that creates a new item with the suggestion's metadata. |

### Frontend tests added

| Test File | Tests | What it covers |
|-----------|-------|----------------|
| `resources/js/components/dashboard/SelectListModal.test.jsx` | 5 | Renders title + options, product name display, onSelect callback, onCancel callback, dialog role |
| `resources/js/components/dashboard/ReplenishmentBanner.test.jsx` | 11 | Loading hides, empty hides, renders cards, accept single list direct, accept multi opens modal, modal cancel, ignore, silence, fetch error, accept error, no active lists disables button |
| `resources/js/components/items/ComplementaryChip.test.jsx` | 6 | Loading hides, empty hides, renders suggestions, accept callback + hide, dismiss callback + hide, API failure hides |

**Frontend total**: 208 tests (180 previous + 28 new for Epic 5B).

### Existing test updates

- `tests/Unit/Support/Ai/AiUsageTrackerTest.php` had 3 tests rewritten for the shared-quota refactor:
  - `test_used_today_counts_only_success` → `test_used_today_for_operation_counts_only_success` (uses new method name)
  - `test_used_today_excludes_other_users` → `test_used_today_for_operation_excludes_other_users` (same)
  - `test_different_operations_have_independent_counters` → `test_quota_is_shared_across_all_operations` (asserts the new shared semantics)
  - Added `test_used_today_across_all_operations_sums_them` for the new method
- All Epic 5A tests still pass — `canUse` signature unchanged, existing call sites work transparently.
- `DashboardPage.test.jsx` and `ListDetailPage.test.jsx` did NOT need mock updates — `ReplenishmentBanner` and `ComplementaryChip` only mount conditionally (banner needs `hasLists`, chip needs successful add), and their fetch failures are caught silently.

## API Contract (Backend → Frontend)

### Endpoints Created

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/dashboard/replenishment` | JWT | Returns up to 3 replenishment suggestions, cached 5 min per user |
| POST | `/api/replenishment/accept` | JWT | Body: `{producto_nombre, list_id}` — adds the item to the list, invalidates cache |
| POST | `/api/replenishment/ignore` | JWT | Body: `{producto_nombre}` — inserts a 24h dismissal row, invalidates cache |
| POST | `/api/replenishment/silence` | JWT | Body: `{producto_nombre}` — permanent silence, invalidates cache |
| GET | `/api/suggestions/complements` | JWT | Query: `?product=X&list_id=Y` — up to 2 complementary suggestions |

### Request/Response Examples

```json
// GET /api/dashboard/replenishment
{
  "data": {
    "suggestions": [
      {
        "producto_nombre": "Leche entera",
        "purchase_count": 12,
        "last_purchased_at": "2026-04-01T00:00:00Z",
        "days_since_last": 10,
        "avg_days_between": 7.0,
        "urgency_ratio": 1.43,
        "frequency_label": "Sueles comprar Leche entera cada 7 dias",
        "source": "history"
      }
    ]
  }
}

// POST /api/replenishment/accept (201) — same shape as Epic 3 POST /lists/{id}/items
{"data": {"item": {...}, "counters": {...}}}

// POST /api/replenishment/ignore (200)
{"data": {"message": "Sugerencia ignorada 24 horas."}}

// POST /api/replenishment/silence (200)
{"data": {"message": "Producto silenciado."}}

// GET /api/suggestions/complements?product=Pasta&list_id=5
{
  "data": {
    "suggestions": [
      {"nombre": "Tomate frito", "unidad_tipica": "ud", "categoria": "conservas", "cantidad_tipica": null, "co_ratio": 0.80, "source": "history"},
      {"nombre": "Queso rallado", "unidad_tipica": "g", "categoria": "lacteos_huevos", "cantidad_tipica": null, "co_ratio": 0.65, "source": "history"}
    ],
    "ai_fallback_used": false,
    "ai_limit_reached": false
  }
}
```

### Error Codes

| Code | Meaning | Frontend Action |
|------|---------|-----------------|
| 401 | Missing/invalid JWT | Redirect to login |
| 403 | Cross-user list access | Show access denied |
| 422 | Validation error | Show field errors |
| 429 | Rate limit (60/min on complement endpoint) | Show retry hint |
| 500 | Server error | Generic error |

## Implementation Decisions

1. **AiUsageTracker shared quota**: `canUse` no longer filters by operation. The total of suggestions + replenishments + complements + future operations counts toward the same daily limit. `usedTodayForOperation` is kept for the admin/analytics use case. The single Epic 5A test that asserted per-operation independence was rewritten to assert the new shared semantics.
2. **Aggregate SQL + PHP filter for replenishment**: tried to keep all logic in one SQL query but the `NOT IN (subquery)` blew up the query plan. Splitting into one aggregate query + three small exclusion lookups + PHP filter is faster, more readable, and easier to test.
3. **Two-step co-occurrence query**: leverages the existing `shopping_lists.items_total/items_completed` counters from Epic 3 to identify "completed lists" without joining `list_items`. Step 1 gets list IDs, step 2 finds co-occurring products. Ratio computed in PHP.
4. **`(user_id, lista_id)` index added to `producto_historial`**: required for the co-occurrence query to be fast. Reversible migration.
5. **Cache invalidation explicit + 5min TTL**: on every accept/ignore/silence the service calls `invalidateCache($user)`. The TTL is the safety net.
6. **No cache on complement endpoint**: each call is specific to a `(product, list_id)` pair and the response would rarely be reused. Skipped.
7. **Replenishment Claude fallback intentionally NOT wired**: the design allowed it but the implementation skips it. Local SQL is sufficient for the MVP. Wiring it later is a one-method addition (already documented in the PRD as "optional").
8. **Complement Claude fallback IS wired**: necessary for new users with <5 completed lists, who would otherwise see no complements at all.
9. **Idempotent silence**: `firstOrCreate` ensures clicking silence twice doesn't create duplicate rows. Test covers.
10. **Frequency label generated server-side**: the Spanish phrase ("Sueles comprar X cada N dias") is built in the service so the frontend doesn't reimplement i18n logic.
11. **Frontend banner is conditional on `hasLists`**: prevents an empty banner from appearing for users without any lists. Also avoids the dashboard showing the banner on the empty state.
12. **`ComplementaryChip` only mounts after successful add**: the parent (`ListDetailPage`) tracks `complementFor` state. Setting it triggers the chip to fetch and render. Setting it back to `null` (on dismiss/accept) unmounts the chip. The `key={complementFor}` ensures a fresh component per added item.

## Known Issues / Technical Debt

1. **Replenishment Claude fallback not wired** — design allows it (`if SQL returns <3 AND user has >=10 distinct products`), implementation skips it. Adds 1 method + ~30 lines if needed later.
2. ~~**No cleanup command for old `ai_dismissed_suggestions` rows**~~ — **RESOLVED in S5-SEC review**. New command `ai:cleanup-dismissed-suggestions` deletes expired rows. Scheduled daily at 03:30 Europe/Madrid. 4 new feature tests cover empty table, expired-only deletion, cross-user scoping, output count.
3. **No "manage silenced products" UI** — once silenced, users can't un-silence. Documented as out of scope, future settings Epic.
4. ~~**Co-occurrence query has no result limit at SQL level**~~ — **RESOLVED in S5-SEC review**. Added `ORDER BY co_count DESC LIMIT 50` to `localCooccurrence` step 2 with a new test asserting the cap.
5. **Frontend chip auto-hide is hardcoded 30 seconds** — could be configurable via a constant or prop. Acceptable for V1.
6. **No analytics on action choice rate** — we don't track how many users accept vs ignore vs silence. The `ai_usage_log` covers AI calls but not user UI actions. Would benefit from a separate event log if product team needs it.

## Scope-adjacent cleanup (performed during S5-SEC review)

While running `composer audit` (mandated by the security-review skill), a HIGH-severity CVE was found in `robrichards/xmlseclibs` (CVE-2026-32313, AES-GCM Auth Tag Validation bypass). It is a transitive dependency of `24slides/laravel-saml2`, which inspection revealed to be **dead code from a previous project template** (Insudpharma):

- Email hardcoded `juanjose.liniers@external.insudpharma.com`
- References `users.ad_id` field that does not exist in Superia's schema
- `saveRoles` method commented out entirely
- Wired only to Backpack admin login (not Superia's JWT flow)
- Zero tests cover SAML behavior

With explicit user approval, the following was removed:

| File | Action |
|------|--------|
| `composer.json` | `composer remove 24slides/laravel-saml2 --update-with-dependencies` (drops `xmlseclibs` transitively) |
| `app/Providers/EventServiceProvider.php` | Stripped SAML event listeners; reduced to a clean shell |
| `config/saml2.php` | Deleted |
| `.env.example` | Removed `SAML2LOGIN` env block |
| `resources/views/vendor/backpack/theme-tabler/auth/login/cover.blade.php` | Removed dead SAML login link to `sofia4requests.insudpharma.com` |

Additionally, `league/commonmark` was updated from 2.8.0 → 2.8.2 to clear two medium CVEs.

After cleanup: `composer audit` → **No security vulnerability advisories found**. Backend test suite unchanged in count, all green.

## Transition

- Gate Status: S4 COMPLETE (backend + frontend)
- Next Step: STEP 5 — Multi-reviewer pass (S5-CODE, S5-SEC, S5-TEST, S5-UX)
