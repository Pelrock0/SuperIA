# Technical Design — FEAT-AUTOCOMPLETE-LIST-SOURCE

Tercera capa local en el pipeline de autocompletado, vía método privado `searchListItems()` en `ProductSuggestionService` (no se introduce nueva clase). Migración aditiva añade índice compuesto.

## Arquitectura

| Layer | Responsabilidad | Key Class |
|-------|----------------|-----------|
| Service | Orquestar layers + dedup + AI fallback | `ProductSuggestionService::suggest()` |
| Infra | Query prefix scoped a user | `ProductSuggestionService::searchListItems()` (private) |
| Infra | Query catálogo (sin cambio) | `ProductSuggestionService::searchCatalog()` |
| Infra | Query historial pesado (sin cambio) | `ProductHistoryWeightingService::search()` |

## Flujo de datos

```
GET /api/suggestions?q={query}&ai={bool}
  └─ ProductSuggestionService::suggest(User, query, includeAi)
       ├─ Layer 1: history.search()         → producto_historial (user_id scoped)
       ├─ Layer NEW: searchListItems()
       │     SELECT DISTINCT list_items.name
       │     FROM list_items
       │     JOIN shopping_lists ON shopping_lists.id = list_items.shopping_list_id
       │     WHERE shopping_lists.user_id = ?
       │       AND list_items.name LIKE ?%
       │     LIMIT 5
       ├─ Layer 2: searchCatalog()           → producto_catalogo
       ├─ dedup([layer1, layerList, layer2], LOCAL_LIMIT)
       └─ if includeAi && count < 3: tryAiFallback()
```

## Migración

| Migration | Cambio | Reversible |
|-----------|--------|------------|
| `2026_04_21_133606_add_name_index_to_list_items_table` | Index compuesto `(shopping_list_id, name)` | Sí (dropIndex) |

Composite porque la query filtra por `shopping_list_id` (via JOIN) antes de aplicar `name LIKE ?%`.

## API

Sin cambios de endpoint. Campo `source` gana valor `'list'` además de `'history'`, `'catalog'`, `'ai'`.

## Decisiones de diseño

- **Método privado, no nueva clase** — consistente con patrón `searchCatalog()`. Nueva clase = overkill para un método.
- **Composite index `(shopping_list_id, name)`** — cubre JOIN + prefix en un solo scan.
- **DISTINCT sobre `name`** — mismo producto en varias listas devuelve una entrada.
- **`LIMIT 5`** por layer; cap global aplicado por `dedup()`.

## Seguridad

- **User scoping crítico**: `WHERE shopping_lists.user_id = $user->id` enforced en query, no en controller. AC-4 verifica con test negativo.
- **LIKE escape**: caracteres `%`, `_`, `\` escapados antes de interpolar — mismo patrón que `searchCatalog()` y `ProductHistoryWeightingService::search()`.
- **Sin cambios auth**: middleware existente sobre suggestion endpoint.

## Gotchas

- LIKE escape pattern duplicado en 3 métodos (deuda técnica pre-existente). Candidato a helper privado en futuro refactor.
- No N+1: query única JOIN con sub-query.

## Hallazgos críticos S5

Ninguno blocker. CODE/SEC/TEST aprueban en una iteración. Sin deuda nueva.

Origen: `docs/features/FEAT-AUTOCOMPLETE-LIST-SOURCE/03-technical-design.md`, `04-implementation-notes.md`, `05-review-results.md`.
