# Wiki TODO

Pending items discovered during wiki build or query resolution.

<!-- Format:
## [PENDING] [original question]

**Asked:** YYYY-MM-DD
**Query:** "exact user query"
**Found in code:** file:line refs

**Answer (pending documentation):**
answer text

**Target wiki file:** `wiki/[target.md]` (section: [section])

**Status:** PENDING | DOCUMENTED
-->

## [DOCUMENTED] Drift de rutas: weekly-summary save vs convert-to-list

**Asked:** 2026-05-14
**Query:** "/wiki sync"
**Found in code:** routes/api.php:131; app/Http/Controllers/WeeklySummaryController.php
**Documented in:** wiki/technical-docs/app/routes.md (Weekly Summary section)

**Resolución:** Corregida la entrada `convert-to-list` → `save`. Endpoint vigente: `POST /api/weekly-summary/{summary}/save` → `WeeklySummaryController@save`.

**Status:** DOCUMENTED

## [DOCUMENTED] Drift de releases: commit 34fe4b3 no indexado

**Asked:** 2026-05-14
**Query:** "/wiki sync"
**Found in code:** git log; commit 34fe4b3
**Documented in:** wiki/releases/README.md + SUMMARY.md RELEASES table

**Resolución:** Añadido `34fe4b3` (2026-05-07) como r001. Renumeración completa de las 49 entradas previas (r002..r050). Initial commit ahora es r050.

**Status:** DOCUMENTED

## [DOCUMENTED] Drift bounded contexts: glosario default no documentado

**Asked:** 2026-05-14
**Query:** "/wiki sync"
**Found in code:** docs/contexts/default/00-glossary.md
**Documented in:** wiki/architecture/bounded-contexts.md (nuevo)

**Resolución:** Creado `bounded-contexts.md` con 26 términos del glosario default (list-items context): item pendiente/comprado, marcar como comprado, sink, feedback inmediato, toggle, lista de compra, resumen semanal, recomendación, guardar selección, lista destino, conversión parcial, resumen actuado, etc. Añadida entrada en SUMMARY.md ARCHITECTURE.

**Status:** DOCUMENTED

## [DOCUMENTED] Drift épics: 4 features con docs sin entrada en wiki/scope

**Asked:** 2026-05-14
**Query:** "/wiki sync"
**Found in code:** docs/features/FEAT-AUTOCOMPLETE-LIST-SOURCE, FEAT-LISTS-MOVE-ITEMS, FEAT-PURCHASE-ANIMATION, FEAT-REC-SAVE-PARTIAL
**Documented in:** wiki/scope/ + wiki/technical-design/

**Resolución:**
- FEAT-AUTOCOMPLETE-LIST-SOURCE → scope + TD creados
- FEAT-LISTS-MOVE-ITEMS → solo scope (S1 PASSED, S2-S5 pendientes en code)
- FEAT-PURCHASE-ANIMATION → scope + TD creados
- FEAT-REC-SAVE-PARTIAL → scope + TD creados (incluye S6 hotfix LLM enum coercion)

SUMMARY.md actualizado con 4 nuevas filas en SCOPE table + 4 nuevas filas en TECHNICAL DESIGN (3 nuevas + lists-move-items omitido en TD por estar pre-S2).

**Status:** DOCUMENTED

## [PENDING] Drift épics: 3 features sin docs en code

**Asked:** 2026-05-14
**Query:** "/wiki sync"
**Found in code:** docs/features/FEAT-AUTH-LOGIN, FEAT-DASHBOARD-V2, FEAT-PAYMENT-GATEWAY (directorios vacíos)

**Answer (pending documentation):**
Tres FEAT-* presentes en docs/features pero sin archivos 01-scope.md ni siguientes. Mencionadas en _meta.md "Épics tracked" pero sin wiki/scope/*.md correspondiente. Decisión: no documentar hasta que tengan contenido en docs/features. Estos placeholders parecen ser features abortadas o no comenzadas. Conviene confirmar con el usuario si deben borrarse de docs/features o si son work-in-progress.

**Target wiki file:** `wiki/scope/` (pendiente creación de docs origen)

**Status:** PENDING

## [DOCUMENTED] Drift técnica: 3 entries en scope sin technical-design

**Asked:** 2026-05-14
**Query:** "/wiki sync"
**Found in code:** wiki/scope/biometric-ux.md, purchased-item-sink.md, waitlist-admin-notify.md
**Documented in:** wiki/technical-design/biometric-ux.md, purchased-item-sink.md, waitlist-admin-notify.md

**Resolución:** Las 3 features tenían `03-technical-design.md` en `docs/features/` pero el build de 2026-04-25 los omitió. Destilados ahora. SUMMARY.md TECHNICAL DESIGN table actualizada con las 3 nuevas filas.

**Status:** DOCUMENTED
