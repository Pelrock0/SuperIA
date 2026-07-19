# Wiki Meta

| Property | Value |
|----------|-------|
| Build date | 2026-04-25 |
| Build completed | 2026-04-25 |
| Last sync | 2026-05-14 |
| Git HEAD (build) | d0d3b6629b8b37115af604b8dbfd7df7a85c19df |
| Git HEAD (sync) | 34fe4b3 |
| Mode | epic-based |
| Stack | Laravel 12 + React (Vite SPA) |
| PHP | 8.4+ |
| Contexts DDD | default (list-items) — added in 2026-05-14 sync |
| Commits indexed | 50 |
| Files created | 79 (build 69 + sync 10) |
| TODOs open | 1 (FEAT-AUTH-LOGIN, FEAT-DASHBOARD-V2, FEAT-PAYMENT-GATEWAY: empty doc dirs) |

## Épics tracked

FEAT-EPIC0-LANDING, FEAT-EPIC1-AUTH, FEAT-EPIC2-LISTS, FEAT-EPIC3-ITEMS, FEAT-EPIC4-COLLAB,
FEAT-EPIC5A-AUTOCOMPLETE, FEAT-EPIC5B-REPLENISH, FEAT-EPIC5C-SUMMARY, FEAT-EPIC6-GENERATION,
FEAT-EPIC7-PRICES, FEAT-EPIC8-DUPLICATES, FEAT-EPIC9-HISTORY, FEAT-EPIC10-ADMIN,
FEAT-AUTH-LOGIN, FEAT-AUTOCOMPLETE-LIST-SOURCE, FEAT-BIOMETRIC-AUTH, FEAT-BIOMETRIC-UX,
FEAT-DASHBOARD-V2, FEAT-LISTS-MOVE-ITEMS, FEAT-OPS-SECURITY-GATES, FEAT-PAYMENT-GATEWAY,
FEAT-PURCHASE-ANIMATION, FEAT-PURCHASED-ITEM-SINK, FEAT-REC-SAVE-PARTIAL, FEAT-SHARED-AUTH,
FEAT-WAITLIST-ADMIN-NOTIFY

## Sync log

### 2026-05-14 — sync + update --from-todo
- Drift detectado:
  - **Routes**: `convert-to-list` → `save` (FEAT-REC-SAVE-PARTIAL endpoint cambió)
  - **Releases**: commit 34fe4b3 no indexado (1 nuevo)
  - **Bounded contexts**: glosario `default` añadido a `docs/contexts/` después del build
  - **Épics**: 4 features con docs sin entrada wiki (AUTOCOMPLETE-LIST-SOURCE, LISTS-MOVE-ITEMS, PURCHASE-ANIMATION, REC-SAVE-PARTIAL)
  - **Technical Design**: 3 entries en scope sin TD wiki (biometric-ux, purchased-item-sink, waitlist-admin-notify)
- Models: sin drift (19 match)
- Services: sin drift (24 match)
- Archivos creados: 10
  - wiki/architecture/bounded-contexts.md
  - wiki/scope/autocomplete-list-source.md, lists-move-items.md, purchase-animation.md, rec-save-partial.md
  - wiki/technical-design/autocomplete-list-source.md, biometric-ux.md, purchase-animation.md, purchased-item-sink.md, rec-save-partial.md, waitlist-admin-notify.md
- Archivos modificados: 4 (SUMMARY.md, releases/README.md, technical-docs/app/routes.md, _todo.md)
- TODOs: 1 PENDING restante (3 FEAT-* empty dirs en docs/features sin contenido — necesitan confirmación usuario)
- Nota: stack corregido (`Next.js 15` → `React + Vite SPA`); el proyecto no usa Next.js (verificado por `vite.config.js` + `routes/web.php` SPA catch-all)
