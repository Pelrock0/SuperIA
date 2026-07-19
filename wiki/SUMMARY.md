# Superlistia Wiki — Index

**Stack:** Laravel 12 + React/Vite (SPA) | **Mode:** Epic-based | **Build:** 2026-04-25 | **Last sync:** 2026-05-14

---

## SCOPE — Épics

| Épic | Título | Complexity | Status | File |
|------|--------|-----------|--------|------|
| FEAT-EPIC0-LANDING | Landing & Waitlist | MEDIUM | S5-PASS | [scope/epic0-landing.md](scope/epic0-landing.md) |
| FEAT-EPIC1-AUTH | Authentication & JWT | HIGH | S5-PASS | [scope/epic1-auth.md](scope/epic1-auth.md) |
| FEAT-EPIC2-LISTS | Shopping List CRUD | MEDIUM | S5-PASS | [scope/epic2-lists.md](scope/epic2-lists.md) |
| FEAT-EPIC3-ITEMS | List Items + Purchase History | HIGH | S5-PASS | [scope/epic3-items.md](scope/epic3-items.md) |
| FEAT-EPIC4-COLLAB | Sharing & Real-Time Collaboration | HIGH | S5-PASS | [scope/epic4-collab.md](scope/epic4-collab.md) |
| FEAT-EPIC5A-AUTOCOMPLETE | AI Autocomplete Pipeline | HIGH | S5-PASS | [scope/epic5a-autocomplete.md](scope/epic5a-autocomplete.md) |
| FEAT-EPIC5B-REPLENISH | Replenishment + Complementary | HIGH | S5-PASS | [scope/epic5b-replenish.md](scope/epic5b-replenish.md) |
| FEAT-EPIC5C-SUMMARY | Weekly Summary Email | HIGH | S5-PASS | [scope/epic5c-summary.md](scope/epic5c-summary.md) |
| FEAT-EPIC6-GENERATION | AI List Generation | HIGH | S5-PASS | [scope/epic6-generation.md](scope/epic6-generation.md) |
| FEAT-EPIC7-PRICES | Price Estimation (Phase A) | HIGH | S5-PASS | [scope/epic7-prices.md](scope/epic7-prices.md) |
| FEAT-EPIC8-DUPLICATES | Duplicate Detection | MEDIUM | S5-PASS | [scope/epic8-duplicates.md](scope/epic8-duplicates.md) |
| FEAT-EPIC9-HISTORY | History & Statistics | MEDIUM | S5-PASS | [scope/epic9-history.md](scope/epic9-history.md) |
| FEAT-EPIC10-ADMIN | Admin Dashboard | HIGH | S5-PASS | [scope/epic10-admin.md](scope/epic10-admin.md) |
| FEAT-AUTOCOMPLETE-LIST-SOURCE | Autocomplete from List Items | MEDIUM | S5-PASS | [scope/autocomplete-list-source.md](scope/autocomplete-list-source.md) |
| FEAT-BIOMETRIC-AUTH | WebAuthn / Passkeys | HIGH | S5-PASS | [scope/biometric-auth.md](scope/biometric-auth.md) |
| FEAT-BIOMETRIC-UX | Biometric Onboarding UX | MEDIUM | S4-PASS | [scope/biometric-ux.md](scope/biometric-ux.md) |
| FEAT-LISTS-MOVE-ITEMS | Move Items Between Lists | MEDIUM | S1-PASS | [scope/lists-move-items.md](scope/lists-move-items.md) |
| FEAT-OPS-SECURITY-GATES | CI Security Gates | HIGH | S5-PASS | [scope/ops-security-gates.md](scope/ops-security-gates.md) |
| FEAT-PURCHASE-ANIMATION | Purchase Item Animation | LOW | S5-PASS | [scope/purchase-animation.md](scope/purchase-animation.md) |
| FEAT-PURCHASED-ITEM-SINK | Purchased Item Sort | LOW | S5-PASS | [scope/purchased-item-sink.md](scope/purchased-item-sink.md) |
| FEAT-REC-SAVE-PARTIAL | Partial Save of Weekly Recommendations | MEDIUM | S5-PASS + S6-hotfix | [scope/rec-save-partial.md](scope/rec-save-partial.md) |
| FEAT-SHARED-AUTH | Shared List Collaborators | HIGH | S5-PASS | [scope/shared-auth.md](scope/shared-auth.md) |
| FEAT-WAITLIST-ADMIN-NOTIFY | Admin Notification on Signup | MEDIUM | S5-PASS | [scope/waitlist-admin-notify.md](scope/waitlist-admin-notify.md) |

---

## TECHNICAL DESIGN

| Épic | Título | File | Keywords |
|------|--------|------|----------|
| FEAT-EPIC0-LANDING | Landing & Waitlist | [technical-design/epic0-landing.md](technical-design/epic0-landing.md) | waitlist, HMAC token, rate limit, Backpack |
| FEAT-EPIC1-AUTH | Authentication & JWT | [technical-design/epic1-auth.md](technical-design/epic1-auth.md) | JWT, jwt_version, lockout, invitation, deletion |
| FEAT-EPIC2-LISTS | Shopping List CRUD | [technical-design/epic2-lists.md](technical-design/epic2-lists.md) | freemium, SELECT FOR UPDATE, atomic, archive |
| FEAT-EPIC3-ITEMS | List Items + History | [technical-design/epic3-items.md](technical-design/epic3-items.md) | counter sync, COUNT, producto_historial, undo |
| FEAT-EPIC4-COLLAB | Sharing & Collaboration | [technical-design/epic4-collab.md](technical-design/epic4-collab.md) | HMAC, share token, anonymous, heartbeat, rolling-50 |
| FEAT-EPIC5A-AUTOCOMPLETE | AI Autocomplete | [technical-design/epic5a-autocomplete.md](technical-design/epic5a-autocomplete.md) | LIKE prefix, weighting, BudgetCap, CircuitBreaker, FakeClaudeClient |
| FEAT-EPIC5B-REPLENISH | Replenishment + Complementary | [technical-design/epic5b-replenish.md](technical-design/epic5b-replenish.md) | frequency algorithm, co-occurrence, cache, shared quota |
| FEAT-EPIC5C-SUMMARY | Weekly Summary | [technical-design/epic5c-summary.md](technical-design/epic5c-summary.md) | cron, UNIQUE constraint, idempotency, eligibility, signed URL |
| FEAT-EPIC6-GENERATION | AI List Generation | [technical-design/epic6-generation.md](technical-design/epic6-generation.md) | free-text, preview, confirm, silent retry, quota stack |
| FEAT-EPIC7-PRICES | Price Estimation | [technical-design/epic7-prices.md](technical-design/epic7-prices.md) | 4-layer pipeline, price cache, catalog seeding, unit conversion |
| FEAT-EPIC8-DUPLICATES | Duplicate Detection | [technical-design/epic8-duplicates.md](technical-design/epic8-duplicates.md) | client-side, similarText, Ratcliff, increment-quantity |
| FEAT-EPIC9-HISTORY | History & Statistics | [technical-design/epic9-history.md](technical-design/epic9-history.md) | pagination, price SUM, duplicate clone, recharts |
| FEAT-EPIC10-ADMIN | Admin Dashboard | [technical-design/epic10-admin.md](technical-design/epic10-admin.md) | Backpack CRUD, is_active, metrics, Telescope gate |
| FEAT-AUTOCOMPLETE-LIST-SOURCE | Autocomplete from List Items | [technical-design/autocomplete-list-source.md](technical-design/autocomplete-list-source.md) | list_items layer, composite index, prefix LIKE, user scoping, dedup |
| FEAT-BIOMETRIC-AUTH | WebAuthn / Passkeys | [technical-design/biometric-auth.md](technical-design/biometric-auth.md) | WebAuthn, FIDO2, sign_count, RP ID, challenge cache |
| FEAT-BIOMETRIC-UX | Biometric Onboarding UX | [technical-design/biometric-ux.md](technical-design/biometric-ux.md) | hook, modal, localStorage, prompt, opt-in, 30d cooldown |
| FEAT-OPS-SECURITY-GATES | CI Security Gates | [technical-design/ops-security-gates.md](technical-design/ops-security-gates.md) | Psalm, psalm.xml, gitleaks, composer audit |
| FEAT-PURCHASE-ANIMATION | Purchase Item Animation | [technical-design/purchase-animation.md](technical-design/purchase-animation.md) | green flash, sink, justChecked, exitingItems, fake timers |
| FEAT-PURCHASED-ITEM-SINK | Purchased Item Sort | [technical-design/purchased-item-sink.md](technical-design/purchased-item-sink.md) | SharedListPage, pendingCategories, purchasedItems, Ya en el carro |
| FEAT-REC-SAVE-PARTIAL | Partial Save of Weekly Recommendations | [technical-design/rec-save-partial.md](technical-design/rec-save-partial.md) | saveSelection, lockForUpdate, createOrIncrement, Actioned, payload mutation, SaveTargetSheet |
| FEAT-SHARED-AUTH | Shared List Collaborators | [technical-design/shared-auth.md](technical-design/shared-auth.md) | ListCollaborator, retroactive, UPSERT, authorizeListAccess |
| FEAT-WAITLIST-ADMIN-NOTIFY | Admin Notification on Signup | [technical-design/waitlist-admin-notify.md](technical-design/waitlist-admin-notify.md) | AdminWaitlistNotificationMail, ShouldQueue, Spatie role, side effect |

---

## RELEASES

See [releases/README.md](releases/README.md) for the full commit index.

| # | Hash | Date | Summary |
|---|------|------|---------|
| r001 | 34fe4b3 | 2026-05-07 | Infection mutation testing + DDD review skills |
| r002 | d0d3b66 | 2026-04-22 | ListDetailPage price estimation on mount |
| r003 | 83ef439 | 2026-04-22 | Token usage tracking in AI services |
| r004 | 872b1ae | 2026-04-21 | Security docs refactor, source-driven dev |
| r005 | e9d627e | 2026-04-19 | WebAuthn API simplification |
| r006 | 72fa46b | 2026-04-19 | Price estimation 3-layer pipeline |
| r007 | 7d0dfe2 | 2026-04-19 | Privacy text, PriceBar empty state |
| r008 | 7befae7 | 2026-04-18 | i18n support, Spanish translations |
| r009 | 25382e2 | 2026-04-17 | Rebrand Superia → Superlistia, Resend email |
| r010 | 9832ff7 | 2026-04-16 | WebAuthn passwordless auth |
| r011 | 1c773ad | 2026-04-15 | Retroactive collaborator linking tests pass |
| r012 | 9f67360 | 2026-04-15 | Category inference prompt seeder |
| r013 | f1d48b1 | 2026-04-15 | Collaboration features + shared list save |
| r014-r022 | various | 2026-04-14/15 | Dockerfile + admin + landing iterations |
| r050 | caba3b1 | 2026-04-13 | Initial commit: SuperIA full project |

---

## ARCHITECTURE

| Artifact | File | Keywords |
|----------|------|----------|
| Stack & dependencies | [architecture/stack.md](architecture/stack.md) | Laravel, Next.js, PHP, packages, versions, dependencies |
| Service map | [architecture/service-map.md](architecture/service-map.md) | services, classes, AuthService, ShoppingListService, ClaudeClient |
| Model map | [architecture/model-map.md](architecture/model-map.md) | models, tables, entities, fields, relations, database |
| Enums & constants | [architecture/enums.md](architecture/enums.md) | enums, constants, limits, config values, quotas, TTL |
| Async jobs & commands | [architecture/async-jobs.md](architecture/async-jobs.md) | jobs, queue, scheduler, cron, commands |
| Middleware | [architecture/middleware.md](architecture/middleware.md) | middleware, rate limit, throttle, JWT, share token, admin |
| Bounded contexts (DDD) | [architecture/bounded-contexts.md](architecture/bounded-contexts.md) | DDD, glosario, ubicuo, list-items, resumen semanal, conversión parcial |

---

## TECHNICAL DOCS

| Module | File | Keywords |
|--------|------|----------|
| All routes | [technical-docs/app/routes.md](technical-docs/app/routes.md) | routes, endpoints, API, URL, HTTP, method, middleware |
| Auth & JWT | [technical-docs/app/auth.md](technical-docs/app/auth.md) | login, register, JWT, password, WebAuthn, lockout, invitation |
| Shopping lists | [technical-docs/app/shopping-lists.md](technical-docs/app/shopping-lists.md) | lists, archive, freemium, create, delete, emoji, category |
| List items | [technical-docs/app/list-items.md](technical-docs/app/list-items.md) | items, toggle, purchase, history, counter, undo, position |
| Collaboration | [technical-docs/app/collaboration.md](technical-docs/app/collaboration.md) | share, token, anonymous, presence, heartbeat, HMAC, collaborator |
| AI suggestions | [technical-docs/app/ai-suggestions.md](technical-docs/app/ai-suggestions.md) | autocomplete, suggestions, Claude, AI, budget, quota, circuit breaker, replenishment, complements |
| Price estimation | [technical-docs/app/price-estimation.md](technical-docs/app/price-estimation.md) | prices, estimation, catalog, history, layers, Claude, precio |
| Weekly summary | [technical-docs/app/weekly-summary.md](technical-docs/app/weekly-summary.md) | summary, email, cron, weekly, unsubscribe, eligibility, Monday |
| List generation | [technical-docs/app/list-generation.md](technical-docs/app/list-generation.md) | generate, AI, description, preview, confirm, retry |
| Statistics | [technical-docs/app/statistics.md](technical-docs/app/statistics.md) | stats, history, charts, spend, categories, recharts, duplicate |
| Admin | [technical-docs/app/admin.md](technical-docs/app/admin.md) | admin, Backpack, metrics, users, deactivate, is_active, Telescope |
| AI support layer | [technical-docs/app/ai-support.md](technical-docs/app/ai-support.md) | BudgetCap, CircuitBreaker, PromptSanitizer, ClaudeClient, AiUsageTracker, HistoryAnonymizer |

---

## TESTS

| File | Area | Keywords |
|------|------|----------|
| Coverage map | [tests/coverage-map.md](tests/coverage-map.md) | test files, scenarios, feature tests, unit tests, coverage |
| Testing patterns | [tests/testing-patterns.md](tests/testing-patterns.md) | AAA, factories, transactions, mocks, FakeClaudeClient, DatabaseTransactions |

---

## DESIGN

| Artifact | File | Keywords |
|----------|------|----------|
| UX Principles | [design/ux-principles.md](design/ux-principles.md) | UX, accessibility, mobile, consent, progressive disclosure, optimistic |
| Views Map | [design/views-map.md](design/views-map.md) | components, views, pages, frontend, React, Blade, i18n |

---

## FAQ

| Question | Answer | Source |
|----------|--------|--------|
| ¿Se notifica a admins cuando alguien se apunta a la waitlist? | Sí — `AdminWaitlistNotificationMail` enviado async (queue) a todos los admin/superadmin al registrar un waitlist entry. | [scope/waitlist-admin-notify.md](scope/waitlist-admin-notify.md) |
| ¿Cómo guarda el endpoint las recomendaciones del resumen semanal? | `POST /api/weekly-summary/{summary}/save` con `selected_indices[]` + `target_list_id` opcional. Una transacción con triple lock (summary + lista + items) muta `payload_json` y aplica upsert por nombre normalizado. Si payload queda vacío, status pasa a `Actioned`. Reemplaza el legacy `/convert-to-list`. | [technical-design/rec-save-partial.md](technical-design/rec-save-partial.md) |
| ¿Qué fuentes alimentan el autocompletado? | 4 layers en orden: history (`producto_historial`) → list-items (items añadidos a listas del usuario, sin importar si están comprados) → catalog (`producto_catalogo`) → AI fallback. Dedup entre layers. | [technical-design/autocomplete-list-source.md](technical-design/autocomplete-list-source.md) |
| ¿Por qué tarda 1.5s en moverse un item al marcarlo comprado? | Animación de feedback intencional: `bg-green-100` + line-through inmediatos (<50ms), 1.5s delay + 300ms exit (fade+height). La llamada API toggle dispara al instante; solo el sink visual se retrasa. | [technical-design/purchase-animation.md](technical-design/purchase-animation.md) |
| ¿Dónde se documenta el lenguaje ubicuo (DDD) del proyecto? | `docs/contexts/default/00-glossary.md` (single bounded context "list-items"). Resumen en [architecture/bounded-contexts.md](architecture/bounded-contexts.md). | [architecture/bounded-contexts.md](architecture/bounded-contexts.md) |
