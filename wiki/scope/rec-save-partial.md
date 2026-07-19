# Scope — FEAT-REC-SAVE-PARTIAL

Guardado parcial de recomendaciones del resumen semanal: el usuario selecciona qué items guardar y elige destino (lista existente activa o nueva), reemplazando el flujo legacy "convertir todo a lista nueva". `payload_json` muta tras cada guardado; cuando queda vacío, summary pasa a `WeeklySummaryStatus::Actioned`.

## Clasificación

| Atributo | Valor |
|----------|-------|
| Complexity | MEDIUM |
| Effort | 8–12 h |
| Status | S5 PASSED + S6 hotfix LLM coercion 2026-05-05 |

## Historias / ACs (15)

| AC | Descripción |
|----|-------------|
| AC-1 | Todos los items marcados por defecto, contador en CTA |
| AC-2 | Toggle local de checkboxes actualiza contador |
| AC-3 | CTA disabled con 0 selección |
| AC-4 | Click CTA abre `SaveTargetSheet` (bottom sheet mobile / modal desktop) |
| AC-5 | "+ Nueva lista" crea lista con items + redirige tras 1.5s |
| AC-6 | "+ Nueva lista" disabled si 3 listas activas (freemium) |
| AC-7 | Añadir a lista existente sin duplicado crea item nuevo |
| AC-8 | Duplicado (name + unit + no purchased) → suma `quantity` |
| AC-9 | Selección parcial → quedan pendientes, summary sigue visible |
| AC-10 | Selección total → payload vacío, summary `Actioned`, oculto en `latest` |
| AC-11 | IDOR sobre `target_list_id` → 404 |
| AC-12 | `selected_indices` fuera de rango → 422 |
| AC-13 | `selected_indices` vacío → 422 |
| AC-14 | Lista archivada como destino → 404/422 |
| AC-15 | Idempotencia transaccional: fallo intermedio → rollback total |

## Dependencias clave

- **Endpoint nuevo**: `POST /weekly-summary/{summary}/save` (reemplaza `/convert-to-list`)
- `WeeklySummaryService::saveSelection()` — orquesta lock, mutación payload, upsert items
- `ListItemService::createOrIncrement()` — nuevo método, upsert por (name normalizado + unit + no purchased)
- `ShoppingListService::create()` (reuso) — propaga `FREEMIUM_LIMIT`
- Enum: `WeeklySummaryStatus::Actioned` (sin migración DB; `status` es varchar(20))
- Frontend: `SaveTargetSheet.jsx` (nuevo), `WeeklySummaryPage.jsx` (modificado), `weeklySummaryApi.js`

## Decisiones de producto

- Default: todos marcados
- Solo listas **activas** como destino (archivadas excluidas)
- Items duplicados: sumar `quantity` solo si match exacto (case-insensitive name, misma unit, no purchased)
- Mutación de `payload_json` (cambio de semántica: era inmutable)
- Rollout directo sin feature flag — `/convert-to-list` se elimina
- UX Designer requerido — wireframes en `docs/features/FEAT-REC-SAVE-PARTIAL/ux-wireframes.html`

## Desviaciones scope → implementación

| Desviación | Razón |
|------------|-------|
| `SaveWeeklySummarySelectionRequest` omite `exists:shopping_lists,id` + `required_if` para `new_list_name` | Validación ownership+status+lock se hace en servicio (más seguro que `exists`); `new_list_name` tiene fallback legacy "Resumen semanal del DD/MM/YYYY" |
| `createOrIncrement` no llama `logActivity()` ni dispatcha job AI inference | Items vienen del summary IA con categoría ya resuelta; sync inferer basta |

## Hallazgos críticos S5/S6

- **S5-UX iter1 → CHANGES REQUIRED**: 3 High a11y (sin focus trap, sin focus indicator, contraste freemium ilegible) + 2 Medium spec (CTA copy estático, modal no centrado en desktop) + 1 Medium UX (card no clickable). **Todos resueltos iter2**.
- **S6 HOTFIX 2026-05-05**: `WeeklySummaryService::saveSelection` pasaba `categoria`/`unidad_tipica` raw del payload LLM → Eloquent enum cast lanzaba `ValueError`. Fix: `createOrIncrement` coerce con `ProductCategory::tryFrom()?->value` y `ItemUnit::tryFrom()?->value`. +2 tests.

Origen: `docs/features/FEAT-REC-SAVE-PARTIAL/01-scope.md` → `05-*.md` + iter2.
