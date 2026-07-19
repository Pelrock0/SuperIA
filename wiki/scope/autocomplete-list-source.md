# Scope — FEAT-AUTOCOMPLETE-LIST-SOURCE

Añade `list_items` como tercera capa local en el pipeline de autocompletado, entre historial de compra (Layer 1) y catálogo global (Layer 2). Productos añadidos a listas pero nunca comprados pasan a ser sugeribles.

## Clasificación

| Atributo | Valor |
|----------|-------|
| Complexity | MEDIUM |
| Effort | ~5 h |
| Status | S5 PASSED 2026-04-21 (CODE+SEC+TEST) |

## Historias / ACs

| AC | Descripción |
|----|-------------|
| AC-1 | Item de lista sin historial aparece con `source: 'list'` |
| AC-2 | Si está también en historial, gana Layer 1 (`source: 'history'`) |
| AC-3 | Layer "list" gana al catálogo (`source: 'list'` antes que `'catalog'`) |
| AC-4 | Items de otros usuarios nunca aparecen (IDOR) |
| AC-5 | Prefix match only — no coincidencia mid-word |
| AC-6 | Query vacío/whitespace → 0 resultados |
| AC-7 | Dedup entre layers → producto aparece una vez |

## Dependencias clave

- `ProductSuggestionService::suggest()` — orden merge: history → **list** → catalog → AI fallback
- `dedup()` existente maneja duplicados entre layers
- Migración aditiva: index compuesto `(shopping_list_id, name)` en `list_items`

## Decisiones de producto

- Layer "list" sin frecuencia/weighting (cualquier inclusión cuenta)
- Sin cambios UI (`source` field es transparente al frontend)
- Out of scope: cambios al AI fallback, weighting, cross-user data

## Desviaciones scope → implementación

Ninguna. 8 tests añadidos cubren los 7 ACs + edge case DISTINCT cross-lists.

Origen: `docs/features/FEAT-AUTOCOMPLETE-LIST-SOURCE/01-scope.md`, `02-prd.md`, `04-implementation-notes.md`, `05-review-results.md`.
