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

<!-- ===================== SYNC 2026-07-19 ===================== -->

## [PENDING] Drift de releases: hashes invalidados por reescritura de historia + commits nuevos

**Asked:** 2026-07-19
**Query:** "/wiki sync"
**Found in code:** git log; `git cat-file -t` de 34fe4b3/d0d3b66/caba3b1 → NO EXISTE

**Answer (pending documentation):**
Se purgó el framework Sofia4Builders del historial con `git filter-repo` y se hizo force-push. Esto **reescribió todos los commits**, por lo que TODOS los hashes de la tabla RELEASES del wiki (r001 `34fe4b3`, r002 `d0d3b66`, … r050 `caba3b1`) apuntan a commits que ya no existen. Además hay ~13 commits nuevos desde la última sync (DUPCHECK, fixes WebAuthn 5.3, README, LICENSE MIT, deploy.sh, purga framework, coverage untrack). HEAD actual: `cae81fd`.
Acción recomendada: regenerar la sección RELEASES (`/wiki build --section releases`) contra la historia nueva, o al menos re-mapear los hashes. Nota: los hashes viejos son irrecuperables salvo por el bundle de backup en scratchpad.

**Target wiki file:** `wiki/releases/` + SUMMARY.md RELEASES table

**Resolución (2026-07-19):** Tabla RELEASES de SUMMARY.md regenerada con los 64 hashes nuevos (r001 `cae81fd` … r064 `2c2a0d5` initial) + nota de aviso sobre la reescritura.

**Status:** DOCUMENTED

## [PENDING] Drift épics: FEAT-DUPCHECK-ACTIVE (cerrada S6, desplegada) sin entrada en wiki

**Asked:** 2026-07-19
**Query:** "/wiki sync"
**Found in code:** docs/features/FEAT-DUPCHECK-ACTIVE/ (01→08 completos + evidence); app/Services/ListItemService.php; app/Support/Inflector/SpanishInflector.php; resources/js/lib/spanishInflector.js

**Answer (pending documentation):**
Feature completa (S1→S6) y desplegada en prod, pero sin `wiki/scope/dupcheck-active.md` ni `wiki/technical-design/dupcheck-active.md`. Comportamiento: al añadir un ítem homónimo a uno ya comprado, el check de duplicado ignora los comprados; el backend borra el/los comprado(s) coincidentes y crea el pendiente nuevo. Match por normalizador singular/plural español compartido front+back (`SpanishInflector`). Ni la feature ni el helper `SpanishInflector`/`spanishInflector.js` están en la wiki (architecture/service-map no cubre `app/Support/`).
Acción: `/wiki incorporate FEAT-DUPCHECK-ACTIVE`.

**Target wiki file:** `wiki/scope/dupcheck-active.md`, `wiki/technical-design/dupcheck-active.md`, y nota en `wiki/architecture/` sobre `app/Support/Inflector`

**Resolución (2026-07-19):** Creados `scope/dupcheck-active.md` + `technical-design/dupcheck-active.md`; filas añadidas en SUMMARY SCOPE + TECHNICAL DESIGN. `SpanishInflector` documentado en el TD. (Pendiente menor: reflejar `app/Support/Inflector` en `architecture/service-map.md` en un próximo build.)

**Status:** DOCUMENTED

## [PENDING] Drift épics: FEAT-COMPLETE-SHOPPING-PARTIAL (S1, WIP) sin entrada en wiki

**Asked:** 2026-07-19
**Query:** "/wiki sync"
**Found in code:** docs/features/FEAT-COMPLETE-SHOPPING-PARTIAL/01-scope.md (solo S1)

**Answer (pending documentation):**
Feature en scope S1 (botón "Lista completada" para cierre de compra parcial). NO implementada (solo diseño S1). No documentar en wiki hasta que avance de S1 y tenga código; registrar solo como pendiente para no dar impresión de que existe.

**Target wiki file:** `wiki/scope/complete-shopping-partial.md` (cuando pase de S1)

**Status:** PENDING

## [INFO] Sin drift en models/services/routes

**Asked:** 2026-07-19
**Query:** "/wiki sync"

Models: 19 (WebauthnCredential documentado en model-map). Services: 24. Rutas api.php: 71. El trabajo reciente (DUPCHECK, fix WebAuthn) no añadió models, services ni rutas nuevas — solo modificó `ListItemService`, el modelo `WebauthnCredential` y `WebauthnService`. Sin drift estructural.

**Status:** INFO
