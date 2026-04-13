# PRD: FEAT-EPIC5B-REPLENISH - Replenishment Alerts + Complementary Items

## Business Objective

Turn Superia from a reactive list app into a proactive shopping assistant. Two capabilities:

1. **Replenishment alerts** — tell users when they are about to run out of a product they buy regularly but haven't added to any active list. "Usually you buy leche every 7 days. Last time was 10 days ago. Add it?" This reduces the #1 user frustration reported in waitlist feedback: "I always forget to buy milk and cereal on the weekend run."

2. **Complementary suggestions** — when the user adds a product, nudge them with a product they usually buy alongside it. "You added pasta. You also usually buy tomate frito." This addresses the #2 pain point: "I always go home and realize I forgot the sauce for the pasta I just bought."

Both features lean on `producto_historial` (the table Epic 3 populates on every item check) and the Claude API foundation established in Epic 5A. No net-new infrastructure.

## Problem Statement

- **Users forget recurring products**. The app currently shows no signal that "hey, you usually buy this, you haven't added it this week." Users notice only when they get home.
- **Users forget complementary products**. The user is focused on the dish they're planning, not on the full ingredient graph. The app has the data to connect the dots but doesn't use it.
- **Claude foundation exists but only one feature uses it**. Epic 5A set up rate limits, budget cap, sanitizer, anonymizer, and circuit breaker. Epic 5B is the second consumer and validates the foundation's reusability.
- **No signal to dismiss or silence**. Without a dismiss/silence channel, a wrong suggestion would become noise quickly.

## Scope

### In Scope

- **HU-503 Replenishment alerts**:
  - Detection algorithm (no AI, pure SQL):
    - For each product in the user's `producto_historial` with `COUNT(*) >= config('ai.thresholds.min_occurrences')` (3 by default):
    - Compute `avg_days_between_purchases` (mean of deltas between consecutive `fecha_compra` values for that product).
    - Compute `days_since_last_purchase = DATEDIFF(NOW(), MAX(fecha_compra))`.
    - Mark as candidate if `days_since_last_purchase > avg_days_between_purchases * config('ai.thresholds.replenishment_factor')` (0.8 default).
    - Exclude any product present in any active list the user currently owns.
    - Exclude products in `user_silenced_products` for that user.
    - Exclude products in `ai_dismissed_suggestions` for that user where `dismissed_until > NOW()`.
    - Sort by urgency (highest `days_since_last_purchase / avg_days_between_purchases` ratio first).
    - Cap at `max 3` simultaneous suggestions.
  - Claude fallback (optional, for ambiguous patterns): if SQL returns <3 candidates AND user has >= 10 distinct products in history, call Claude to infer ambiguous patterns from the user's anonymized product history. Counts toward the shared `20/day Free` AI quota. Best-effort — never blocks the dashboard.
  - Dashboard endpoint: `GET /api/dashboard/replenishment` returns up to 3 suggestions. Cached 5 minutes per user.
  - Dashboard banner UI: `ReplenishmentBanner.jsx` renders up to 3 cards with product name, frequency text ("Sueles comprar leche cada 7 dias"), last purchase relative time ("Hace 10 dias"), and three actions per card:
    - **Accept**: adds the product to the user's active list (if only 1 active list, direct; if >1, opens `SelectListModal`; if 0, banner never shows).
    - **Ignore**: persists a row in `ai_dismissed_suggestions` with `dismissed_until = NOW() + 24h`. Card disappears from this and future dashboard loads for 24h.
    - **Silence**: persists a row in `user_silenced_products` permanently. Card disappears from this and all future dashboard loads.
  - Skip entire banner if user has no active list with >=3 items (per HU-503 crit 1).

- **HU-504 Complementary suggestions**:
  - Co-occurrence algorithm (no AI, pure SQL over `producto_historial`):
    - A "completed list" = a `shopping_list` where `COUNT(items) > 0 AND items.is_purchased = true for all`. Checked via join against `list_items`.
    - For the user, compute total count of completed lists. If `<5`, skip local calculation and go to Claude fallback.
    - For a given input product `X`, find lists that contained X (via `producto_historial` grouped by `lista_id`).
    - For each other product Y in those same lists, compute `co_ratio = count(lists containing X AND Y) / count(lists containing X)`.
    - Return Y where `co_ratio >= config('ai.thresholds.co_occurrence_ratio')` (0.60 default), limited to 2 results, sorted by `co_ratio` desc.
    - Exclude Y if it's already present in the current list (passed as `list_id` param).
  - Claude fallback: if `completed_lists < 5`, call Claude with prompt "Cuando alguien anade {X} a su lista de compra espanola, ¿que 2 productos complementarios suele necesitar? Responde en JSON con claves nombre, unidad_tipica, categoria." Sanitized via `PromptSanitizer`. Counts toward daily AI quota.
  - Endpoint: `GET /api/suggestions/complements?product={name}&list_id={id}`. Called async by the frontend after a successful item creation. Best-effort, never blocks `POST /api/lists/{list}/items`.
  - Frontend chip: `ComplementaryChip.jsx` renders inline below the recently added item row with "Quieres anadir tambien: [name]?" + accept (adds to list) and dismiss (hides locally, no persistence) buttons. Max 2 suggestions per item, fade out after 30s if ignored.

- **New DB tables**:
  - `user_silenced_products` (`id`, `user_id` FK cascade, `producto_nombre` string(80), `silenced_at` timestamp). Unique `(user_id, producto_nombre)`.
  - `ai_dismissed_suggestions` (`id`, `user_id` FK cascade, `producto_nombre` string(80), `dismissed_until` timestamp, `created_at`). Index `(user_id, dismissed_until)` for fast exclusion lookup. No unique constraint — user can dismiss the same product twice on different days (only the latest row matters for the TTL check).

- **Config**: add `replenishment_factor = 0.8` under `config('ai.thresholds')`.

- **Minor refactor of `AiUsageTracker`**: rename `usedToday` semantics to keep per-operation breakdown (new method `usedTodayForOperation`) and add `usedTodayAcrossAllOperations` for the shared quota check. `canUse` uses the new across-all method. Epic 5A tests stay green.

- **New Claude capability**: extend `ClaudeClientInterface` with `suggestComplements(string $productName): array` returning `{products: [{nombre, unidad_tipica, categoria}], estimated_cost_usd}`. Hardcoded system prompt, strict JSON parsing, same error handling as other methods.

- **100% test coverage on new code**: unit tests for services and support extensions, feature tests for every new endpoint, frontend component tests for every new component, PII anti-leak test for the new Claude call, rate-limit-enforcement test for the shared quota.

### Out of Scope

- HU-505 weekly summary (Epic 5C)
- Epic 6 list generation by context
- Un-silencing products from the profile (future Epic)
- Push notifications (out of scope, email or in-app deferred)
- Replenishment based on list *category* (e.g., "supermercado" vs "farmacia") — treats all lists equally
- Replenishment based on seasonal patterns (summer vs winter) — linear frequency model only
- Complementary suggestions across users (collaborative filtering) — uses only the user's own history
- Prompt personalization with user dietary preferences — generic Spanish supermarket context
- Chip placement on checked items — only shows on newly added pending items
- Undo on accept — accept is immediate, the user can delete the item afterwards through the existing HU-305 flow
- A/B testing of banner copy or ordering
- Analytics dashboard for admin showing AI operation breakdown (`operation` column exists in `ai_usage_log` for future use)
- Re-showing a dismissed suggestion on the same day even if the user's history changes
- Batch accept (accept all 3 replenishment suggestions at once)

## Acceptance Criteria

### AC-1: Replenishment — detection threshold
- **Given**: A user who has purchased "Leche entera" 5 times over the past 60 days, last purchased 10 days ago, average 7 days between purchases
- **When**: The user opens the dashboard
- **Then**: "Leche entera" appears in the replenishment banner with frequency text "Sueles comprar leche entera cada 7 dias" and relative time "Hace 10 dias".

### AC-2: Replenishment — excludes products in active lists
- **Given**: A product qualifies for replenishment but is already in one of the user's active lists
- **When**: The dashboard endpoint runs
- **Then**: That product is **not** returned in the suggestions.

### AC-3: Replenishment — min occurrences threshold
- **Given**: A product the user has purchased only twice
- **When**: The algorithm runs
- **Then**: That product is **not** eligible because `COUNT(*) < min_occurrences` (3).

### AC-4: Replenishment — factor applied
- **Given**: A product with average 7 days between purchases, last purchased 5 days ago
- **When**: `replenishment_factor = 0.8`, so threshold = 5.6 days
- **Then**: The product is **not** suggested (5 < 5.6). If the product were last purchased 6 days ago, it **would** be suggested.

### AC-5: Replenishment — max 3 simultaneous
- **Given**: 10 products qualify for replenishment
- **When**: The dashboard endpoint runs
- **Then**: The response contains exactly 3 suggestions, sorted by urgency ratio descending.

### AC-6: Replenishment — no banner when no active list with >=3 items
- **Given**: User has 0 active lists, or all active lists have <3 items
- **When**: The dashboard endpoint runs
- **Then**: The response is `{suggestions: []}`. Frontend hides the banner entirely.

### AC-7: Replenishment — accept with 1 active list
- **Given**: User has exactly 1 active list and the banner shows "Leche entera"
- **When**: The user clicks "Anadir"
- **Then**: A new item "Leche entera" is created in that list (via the existing `ListItemService::create`). The card disappears from the banner. The list counters update.

### AC-8: Replenishment — accept with multiple active lists
- **Given**: User has 3 active lists and the banner shows "Leche entera"
- **When**: The user clicks "Anadir"
- **Then**: A `SelectListModal` opens listing the 3 lists. User picks one. Item is created in the selected list. Card disappears from the banner.

### AC-9: Replenishment — ignore creates 24h dismiss
- **Given**: The banner shows a suggestion
- **When**: The user clicks "Ignorar"
- **Then**: A row is inserted in `ai_dismissed_suggestions` with `dismissed_until = NOW() + 24h`. The card disappears. If the user refreshes the dashboard within 24h (or opens it on another device), that product does not reappear.

### AC-10: Replenishment — dismiss expires after 24h
- **Given**: A product was dismissed 25 hours ago
- **When**: The dashboard endpoint runs
- **Then**: The product is eligible for suggestion again (if it still matches the algorithm conditions).

### AC-11: Replenishment — silence is permanent
- **Given**: The banner shows "Chocolate"
- **When**: The user clicks "Silenciar"
- **Then**: A row is inserted in `user_silenced_products`. "Chocolate" will never appear in the replenishment banner for that user until the row is removed (out of scope for 5B).

### AC-12: Replenishment — cache 5 min
- **Given**: User opens the dashboard and the replenishment query executes
- **When**: User refreshes the dashboard within 5 minutes
- **Then**: The response comes from cache; no SQL query or Claude call is made. After 5 minutes, the cache expires and a fresh query runs.

### AC-13: Replenishment — cache invalidated on action
- **Given**: Cache is warm from a previous dashboard load
- **When**: The user accepts, ignores, or silences any suggestion
- **Then**: The cache for that user is cleared so the next dashboard load reflects the change immediately.

### AC-14: Complements — local co-occurrence
- **Given**: A user with >=5 completed lists. In their history, pasta and tomate frito appear together in 80% of lists containing pasta
- **When**: The user adds "pasta" and the frontend calls `GET /api/suggestions/complements?product=pasta&list_id=N`
- **Then**: The response includes "tomate frito" with `source: "history"`. Co-ratio is 0.80.

### AC-15: Complements — threshold enforced
- **Given**: A user with >=5 completed lists. Pasta and queso parmesano appear together in 40% of pasta lists (below 60% threshold)
- **When**: The user adds pasta
- **Then**: "queso parmesano" is **not** returned.

### AC-16: Complements — Claude fallback for new users
- **Given**: A user with only 2 completed lists (below the 5-list threshold)
- **When**: The user adds "pasta"
- **Then**: The service calls Claude with the sanitized product name and the prompt "Cuando alguien anade pasta a su lista de compra espanola, ¿que 2 productos complementarios suele necesitar?". The response is parsed as JSON, returned with `source: "ai"`.

### AC-17: Complements — excludes already-present items
- **Given**: Pasta is in the current list and tomate frito is already in the current list too
- **When**: The user adds a new item and the complement endpoint returns tomate frito as a match
- **Then**: The endpoint excludes tomate frito from the response because it's already in the list.

### AC-18: Complements — async, best-effort, non-blocking
- **Given**: The user creates an item via `POST /api/lists/{list}/items`
- **When**: The complement endpoint is slow or fails
- **Then**: The item creation response is already returned to the user. The complement chip either appears later or not at all. Item creation is not affected.

### AC-19: Complements — chip accept adds to list
- **Given**: The complement chip shows "tomate frito"
- **When**: The user clicks the chip
- **Then**: A new item "tomate frito" is added to the same list (`list_id` param). Chip disappears.

### AC-20: Complements — chip dismiss hides locally
- **Given**: The complement chip is showing
- **When**: The user clicks the dismiss (x) button or 30s pass
- **Then**: The chip is hidden in the current session. No persistence. Next item creation can trigger new suggestions.

### AC-21: Shared AI quota
- **Given**: A Free user has used 15 suggestions + 3 replenishment + 2 complement calls today
- **When**: Any new AI-triggered operation tries to run
- **Then**: `AiUsageTracker::canUse` returns false (15+3+2 = 20 = quota). The operation returns early with `ai_limit_reached: true`. The local (non-AI) path continues to work.

### AC-22: Budget cap blocks Claude in both features
- **Given**: The project-wide monthly Claude spend exceeds the cap
- **When**: The replenishment fallback OR complement fallback tries to run
- **Then**: `BudgetCap::canSpend()` returns false. The operation records a `BudgetCapped` usage log row and returns no AI results. Local results still work. Alert email is queued (deduped per day).

### AC-23: PII never leaves via complement call
- **Given**: The user triggers a complement Claude call
- **When**: The prompt payload is captured in tests
- **Then**: The payload contains only the sanitized product name. It does NOT contain user_id, email, list_id, or any identifier.

### AC-24: Replenishment endpoint auth required
- **Given**: An unauthenticated request to `/api/dashboard/replenishment`, `/api/replenishment/accept`, `/api/replenishment/ignore`, or `/api/replenishment/silence`
- **When**: It reaches the API
- **Then**: 401 response. No data leaked.

### AC-25: Complement endpoint auth required
- **Given**: An unauthenticated request to `/api/suggestions/complements`
- **When**: It reaches the API
- **Then**: 401 response.

### AC-26: Cross-user isolation on silence/dismiss
- **Given**: User A silences a product. User B has the same product in history.
- **When**: User B opens the dashboard
- **Then**: User B's banner is unaffected. Silence is user-scoped.

### AC-27: Replenishment input validation
- **Given**: A POST to `/api/replenishment/accept` with missing or invalid `producto_nombre` (empty, >80 chars) or missing `list_id`
- **When**: The request is validated
- **Then**: 422 with field errors. No row written.

### AC-28: Complement endpoint input validation
- **Given**: A GET to `/api/suggestions/complements` with missing `product` or `list_id`, or `product` longer than 80 chars
- **When**: Validation runs
- **Then**: 422 with field errors.

### AC-29: Replenishment ignore does not affect other users
- **Given**: User A ignores "Leche". User B has "Leche" in their own history.
- **When**: User B opens the dashboard
- **Then**: User B's banner can still show "Leche" (scoped by `ai_dismissed_suggestions.user_id`).

### AC-30: Existing Epic 5A suggestion flow unaffected
- **Given**: All Epic 5A `ProductSuggestionServiceTest` cases
- **When**: Epic 5B is installed and the `AiUsageTracker` refactor is applied
- **Then**: All Epic 5A tests continue to pass. The shared quota check is a superset of the previous per-operation check.

## UX Decision

- **UX Designer Required**: YES
- **UX Artifacts**: Stitch MCP screens for Superia. Consumed at S4, reviewed at S5-UX.
- **Screens / components involved**:
  - `ReplenishmentBanner` — dashboard-top banner. Stitch screen "Reposicion" (HU-503 note). Up to 3 cards. Each card: product name, frequency text, relative last-purchased time, 3 action buttons.
  - `SelectListModal` — modal listing user's active lists when accepting a replenishment suggestion and >1 list exists. No Stitch screen — design inline following existing modal pattern.
  - `ComplementaryChip` — inline chip component below a newly added item. No Stitch screen — design inline as a small rounded pill with product name + accept + dismiss.

> **UI changes heads-up**: Epic 5B introduces two new user-facing surfaces (dashboard banner + inline chip). **S5-UX review required**, covering: banner rendering with 0/1/2/3 suggestions, banner with no suggestions (hidden), all three action buttons, SelectListModal opening flow, ComplementaryChip appearance after item creation, chip accept and dismiss, chip auto-hide after 30s.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Co-occurrence SQL slow on large histories | Performance | Co-occurrence query filters by `user_id` first (existing index). Sub-grouping on `lista_id`. Only runs on complement endpoint, not on every request. Latency logged from day 1 via `Log::info`. |
| Frequency calculation fragile with few data points | Technical | Hard minimum `COUNT(*) >= 3` before computing average. Products with only 1-2 purchases skip the calculation entirely. Tests cover the edge. |
| Dashboard cache stale after user action | Technical | Cache invalidated explicitly on every accept/ignore/silence. 5-minute TTL as a safety net. |
| Replenishment suggesting something the user just dismissed on another device | Technical | `ai_dismissed_suggestions` is server-side and user-scoped. Works across devices and tabs. Test covers. |
| Complement Claude call blocking the add-item flow | Performance | Endpoint is strictly separate (`GET /api/suggestions/complements`). Frontend fires it after the item creation resolves, best-effort. Add-item path unchanged. |
| Shared quota accidentally blocks layer 3 of suggestions when replenishment has used it up | UX | Intentional per Resolved Question 11. The quota is global for a reason: users don't care which AI feature "spent" their quota. Frontend footer hint ("Has alcanzado tu limite") appears in both Epic 5A and Epic 5B UIs consistently. |
| AiUsageTracker refactor breaks Epic 5A | Technical | The refactor is additive (new method `usedTodayAcrossAllOperations`), `canUse` switches to the new method. Existing unit tests for `canUse` are updated only if they specifically checked per-operation behavior — the one Epic 5A test that asserted "suggestion and generation are independent" must be updated to reflect the new shared behavior. **Small breaking change in one test**, not in production behavior. |
| Silenced product list grows unbounded over time | Data | Minimal per row (~40 bytes) and user-scoped. Cascades on user deletion. No proactive cleanup in 5B. Could add a "manage silenced products" section in a future Epic. |
| Claude hallucinating complementary products (e.g., brand names) | Data | `PromptSanitizer` cannot prevent output hallucinations, only input injections. Frontend renders whatever Claude returns as strings. If the user selects a hallucinated product, `CreateItemRequest` validates the fields at write time. |
| Complement Claude call rate cost multiplier | Operational | Every item added by a new user could fire one Claude call. Budget cap is the backstop. Per-user quota (20/day) caps the risk: a user adding 30 items rapidly hits the wall and gets local-only results for the rest of the day. |
| Ignore vs silence confusion in UX | UX | Labels are explicit: "Ignorar" (24h) vs "Silenciar" (permanente). S5-UX review will verify wording in live browser. |
| Banner visual clutter competing with list CTA | UX | Max 3 cards. Compact layout. Not shown if no suggestions. S5-UX review confirms real look. |
| Replenishment endpoint becomes a performance bottleneck at scale | Performance | Monitor latency in production via `Log::info`. If p99 > 500ms, add `ai_replenishment_cache` table with pre-computed suggestions refreshed by a scheduled job (follow-up). |

## Assumptions

- Laravel `Cache` driver handles the `replenishment:user:{id}` key correctly under cache invalidation. The project currently uses whatever driver is configured; tests run on `array` driver which is fine for correctness.
- The `(user_id, producto_nombre)` composite index on `producto_historial` (from Epic 3) is still in place and is the right index for both the replenishment and complement queries.
- `producto_historial` rows persist even when their `lista_id` is set to null (after a list is deleted) — verified in Epic 3's migration (nullable FK with `nullOnDelete`).
- The `ProductoHistorial::recordPurchase` method from Epic 3 is the only writer to this table; no other flow bypasses it. Verified by inspection.
- The `AiUsageTracker` refactor breaks zero production behavior and at most one unit test that asserted per-operation independence.
- `config/ai.php` thresholds (`min_occurrences=3`, `min_completed_lists=5`, `co_occurrence_ratio=0.60`) defined in Epic 5A are the intended values — Resolved Question 8 confirmed.
- Stitch MCP screens for "Reposicion" are accessible at S4 start if available; otherwise the components follow the existing Tailwind aesthetic.
- The existing dashboard's data fetching pattern (`DashboardPage.jsx`) has room for one more API call without timing regressions.
- Claude API cost for `Replenishment` and `Complement` operations is in the same order of magnitude as `Suggestion` — per-call cost assumed ~$0.005-0.01 for estimation purposes.

## Open Questions

None. All resolved at S1.

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
- Required Artifacts for Next Step: 01-scope.md, 02-prd.md
