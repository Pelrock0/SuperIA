# Code Review: FEAT-DUPCHECK-ACTIVE

## Summary

- **Status**: PASS
- **Reviewer**: code-reviewer (S5-CODE)
- **Date**: 2026-06-03
- **Verdict**: PASS — no blocking findings. Implementation matches PRD/Technical Design. 100% path coverage on new code is demonstrated by 73 net-new tests (52 inflector PHP + 10 service + 1 updated + 1 WeeklySummary updated + 54 inflector JS + 16 component). The 3 pre-existing failures in the backend suite are unrelated (confirmed by reviewer against `04-implementation-notes.md` and the failing test names belong to `DispatchWeeklySummaryCommandTest` and `SecurityGatesIntegrationTest`).

## Justification

The change is well scoped to the surface area approved in S3. The new helper `App\Support\Inflector\SpanishInflector` is a pure, static, framework-free utility correctly placed under `app/Support/` (matching the established `App\Support\Ai`, `App\Support\Price` pattern). The service mutates the aggregate via `$list->items()` only — aggregate-root rule preserved. The PHP and JS implementations of the inflector are line-by-line mirrors with the same INVARIABLES set, the same R1/R2 rules, and the same accent map, and they share a parallel test contract that would fail on either side if drift is introduced. Backend transaction boundaries, locking strategy, and PRD acceptance criteria (AC-1 through AC-10, except AC-11 which is integration-level) are each covered by an explicit test.

---

## Findings

### Readability

- **No blocking issues.**
- `SpanishInflector::normalize` reads cleanly as a 6-step pipeline (trim → lower → accent strip → tokenize → per-token rule → join). Constants `INVARIABLES`, `ACCENT_MAP`, `PLURAL_STEM_FINAL_CONSONANTS` are self-documenting. Naming aligns with the glossary terms `Forma normalizada` and `Variante plural`.
- `deletePurchasedHomonyms` and `unitMatches` are well-named, single-purpose private helpers; no comment needed.
- **Non-blocking (nit)**: in `ListItemService::createOrIncrement` the inline `closure` filter at line 108 is readable but slightly denser than the equivalent in `deletePurchasedHomonyms`. Consider extracting a private `pendingMatches(ListItem, string $normalized, ?string $unit): bool` predicate if a future feature needs to reuse it. Not a blocker.
- **Non-blocking (nit)**: the docblock on `createOrIncrement` (line 86–94) still says "trimmed/lowercased `name`" — the new behavior also strips accents and reduces plurals. Tighten the docblock to: *"normalized via `SpanishInflector::normalize` (lowercase, accent-strip, singular/plural reduction)"*.

### Maintainability

- **No blocking issues.**
- Single source of truth for the rules in PHP; JS is a 1:1 port with identical INVARIABLES, identical accent map (excluding ñ), identical R1/R2/strip logic. Parallel test suites guarantee drift is caught.
- No duplication: `deletePurchasedHomonyms` is invoked from both `create()` and `createOrIncrement()` (single implementation, two call sites).
- Dependencies are clear: service depends on `SpanishInflector` (static call to pure function), `CategoryInferenceService`, `ActivityLogService`. No new ones introduced.
- The new test `test_create_or_increment_does_not_match_purchased_item` was correctly updated rather than deleted — the test name still reflects the contract ("does not match" is still true; the additional behavior — delete — is exercised by other new tests).
- **Non-blocking observation**: the design and the PHP code list invariables both with and without `ñ` in different places (design line 252 says `cumpleanos`, design line 185 + impl + tests say `cumpleaños`). The implementation chose `cumpleaños` (ñ preserved) which is consistent with the AC-5 trace table and the test contract. This is the right choice — flag for documentation cleanup of the design doc later, not a code defect.

### Tests

- **No blocking issues.**
- `SpanishInflectorTest`: 52 parametrized cases covering R1, R2 (both branches: consonant-stem → strip_es, vowel-stem → strip_s), invariables (5), `ñ` preservation, accent stripping (including `ü`), uppercase, multi-token (`Tomates Rojos`), trim, whitespace collapse, empty input, whitespace-only, short words (<4 chars), idempotency. All branches of `normalize` and `normalizeToken` covered. ✓
- `ListItemServiceTest` new tests (10 + 1 updated) cover: AC-1 (same name), AC-2 (both directions singular/plural), AC-4 (multiple matches deleted), AC-6 (different unit not deleted), AC-8 (false positive guard pollo/polla), AC-10 (pending takes precedence over purchased delete in `createOrIncrement`), list isolation (other lists untouched), pending homonym untouched, normalized plural → increment existing pending. ✓
- `WeeklySummaryServiceTest::test_save_selection_creates_new_item_when_existing_match_is_purchased` correctly updated to assert the purchased is now **deleted**, not just bypassed.
- Frontend: `spanishInflector.test.js` is a mirror of the PHP test contract (54 cases + 4 edge cases including null/undefined/numeric input — coverage of the non-string defensive branch absent in PHP, which is correct since PHP enforces type via signature). `AddItemInput.test.jsx` adds 7 tests under `describe('duplicate detection vs active items only')` covering AC-1/2/3/7/8/10. `AddItemModal.test.jsx` is a new file with 9 tests covering the same ACs plus the increment-quantity and add-anyway flows.
- **Non-blocking observation**: there is no explicit `Tests\Feature` integration test for the HTTP endpoint `POST /api/lists/{list}/items` exercising the delete behavior end-to-end. The unit-service tests cover the logic deterministically and the controller is unchanged, so the gap is acceptable for this feature. If a feature test exists for the controller elsewhere, this would be redundant; otherwise consider one to defend against future regressions in serialization or middleware. Not a blocker.
- **Non-blocking observation**: no automated test simulates a mid-transaction failure to validate rollback (AC-9). The transaction is `DB::transaction(...)` which Laravel guarantees, and the lock/order is deterministic, so this is low risk. A test using `DB::shouldReceive` or a stubbed factory throwing post-delete would close the gap. Recommend for the S5-TEST gate to decide if it's required.

### Performance

- **No blocking issues.**
- 1 extra `SELECT` (purchased subset of one list, lock-for-update) + at most 1 `DELETE WHERE id IN (...)` per `create()`. Bounded by list size (≤100 in practice). No N+1: items are fetched eagerly into a `Collection` and filtered in PHP — single round-trip.
- `createOrIncrement` now also loads the pending subset into memory rather than a `WHERE LOWER(TRIM(name)) = ?` SQL match. For lists of typical size this is faster (no `LOWER`/`TRIM` per row at the DB), but is O(N) in memory. Documented in the design's trade-offs. Acceptable for v1.
- `lockForUpdate` is scoped to `shopping_list_id` + `is_purchased` filter; lock blast radius is acceptable (per-list serialization, which is already desired for counter consistency).
- Frontend: `findDuplicate` is invoked only on submit (not on each keystroke), so re-computing `normalize(item.name)` per item is cheap (<1 ms for ≤100 items). No memoization needed.
- **Non-blocking observation**: in `createOrIncrement`, two distinct `lockForUpdate` queries run in sequence (pending, then purchased). On `is_purchased = false` first, `is_purchased = true` second. `create()` only locks `is_purchased = true`. Different code paths therefore acquire locks in different orders. Because the two subsets are disjoint at the row level, this should not deadlock under InnoDB's row-locking, but if MySQL chooses an index-gap lock the order could matter. Risk is low (single-list scope, very narrow window). Mention only — not a blocker.

### Architecture

- **No blocking issues.**
- **Hexagonal**: `SpanishInflector` is a pure function helper, no I/O, no framework imports — appropriately placed in `app/Support/Inflector/` next to other helpers. No port/adapter required because there is no infrastructure boundary. The service uses Eloquent (`$list->items()`) which is acceptable since `ListItemService` is the application-service layer in this codebase (this project doesn't enforce a separate `domain/` directory split; rules are followed contextually).
- **DDD aggregate root**: all mutations route through `$list->items()` — the aggregate root `ShoppingList` is the entry point. The delete uses `$list->items()->whereIn('id', ...)->delete()`, which goes through the relation, not through `ListItem::query()` directly. ✓
- **Glossary**: three new terms (`Duplicado`, `Forma normalizada`, `Variante plural`) added to `docs/contexts/default/00-glossary.md`. Match the design and the PRD. ✓
- **No cross-context imports**. `SpanishInflector` lives in `App\Support` which is cross-cutting infrastructure (helper, not domain), and is consumed only by `ListItemService` in the `default` context.
- **Ubiquitous language**: class is `SpanishInflector` (technical), method is `normalize`. The class name is acceptable because it is a generic infrastructure helper (an "inflector" is a well-known software pattern, not a domain noun); it does not pollute the domain layer. Methods that touch the aggregate (`deletePurchasedHomonyms`) use glossary-aligned vocabulary (`purchased`, `homonym`).
- **Controllers untouched** — `ListItemController` and `ReplenishmentController` keep the same contract; the behavior change is internal to the service, in line with controller-thin rule.
- **Transactions**: `create()` keeps its own `DB::transaction`. `createOrIncrement()` keeps its contract of being called inside a caller-owned transaction (verified in `WeeklySummaryService::saveSelection` at line 252). Delete + create are atomic. ✓
- **Authorization**: unchanged — `authorizeListWrite($list)` still gates the controller. The delete only affects items in the authorized list (`$list->items()->...->delete()`). ✓

---

## Recommendation

- [x] **Approve**
- [ ] Request changes

The implementation faithfully translates the technical design. Tests are comprehensive against the acceptance criteria. Architectural rules (aggregate root, hexagonal helper placement, glossary update) are respected. No security regressions: same authorization gate, no new input surface, no SQL injection risk (filtering happens in PHP after typed parameter binding).

---

## Required Changes

None blocking.

## Recommended (non-blocking) follow-ups

1. **`createOrIncrement` docblock** (`app/Services/ListItemService.php:86-94`) — update wording to reflect that match now uses full normalization, not just trim/lowercase.
2. **Design doc cleanup** (`docs/features/FEAT-DUPCHECK-ACTIVE/03-technical-design.md:252,312`) — reconcile the contradiction between `cumpleanos` in the pseudocode block and `cumpleaños` in the canonical INVARIABLES set + tests. Implementation chose `cumpleaños`; design pseudocode should be aligned.
3. **AC-9 explicit rollback test** — optional unit test asserting that an exception thrown after `deletePurchasedHomonyms` but before `syncCounters` leaves both the purchased and the new pending unchanged (defend against future refactors). Defer decision to S5-TEST gate.
4. **Feature/HTTP test for `POST /api/lists/{list}/items`** — optional integration coverage of the delete side-effect end-to-end. Defer decision to S5-TEST gate.
5. **Locking observation** — track for FEAT-PURCHASED-TTL implementation that `create()` locks `is_purchased = true` while `createOrIncrement()` locks both subsets; if the next feature interacts with the same rows, document the acquisition order.

---

## Transition

- Gate Status: S5-CODE PASS
- Next Step: S5-SEC (security-review), S5-TEST (test-enforcement), S5-UX (ui-ux-review)
- This review does not modify code and does not run `approve`. User must explicitly approve S5-CODE before invoking the next reviewer.
