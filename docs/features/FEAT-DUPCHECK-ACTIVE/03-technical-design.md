# Technical Design: FEAT-DUPCHECK-ACTIVE

## Overview

Cambio acotado al servicio `ListItemService` y a los componentes `AddItemInput`/`AddItemModal` para que:

1. El check de duplicado frontend opere **solo contra items pendientes**.
2. El match exacto backend y el check frontend reconozcan **variantes singular/plural en español** (pan↔panes, tomate↔tomates, etc.).
3. Al crear un item pendiente, el backend **elimine los items comprados homónimos** de la misma lista (misma forma normalizada y unidad).

La normalización lingüística se implementa como un helper determinista en dos lados (PHP + JS) que comparten el mismo contrato de tests. Sin migraciones, sin nuevos endpoints, sin cambios visuales.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|------------------|---------------------|
| Domain | Reglas de match singular/plural sobre nombres de producto | `App\Support\Inflector\SpanishInflector` (helper sin dependencias de framework) |
| Services | Lógica de delete del comprado homónimo dentro de la transacción del add | `App\Services\ListItemService::create()`, `createOrIncrement()` |
| Infrastructure | Persistencia Eloquent (lookups por `shopping_list_id`, `is_purchased`) | `ListItem` model, queries vía `$list->items()` |
| Controllers/API | Sin cambios | `ListItemController::store`, `incrementQuantity` |
| Frontend | Filtro de `is_purchased` en `findDuplicate`; match singular/plural en cliente | `resources/js/components/items/AddItemInput.jsx`, `AddItemModal.jsx`, `resources/js/lib/spanishInflector.js` |

**Hexagonal note**: `SpanishInflector` es pure-function helper (sin I/O, sin framework, sin estado). Vive en `app/Support/` siguiendo el patrón de `App\Support\Price\*` y `App\Support\Ai\*`. No requiere port/adapter dado que no hace I/O.

### DDD Checklist

- [x] `docs/contexts/default/00-glossary.md` cubre `Item pendiente`, `Item comprado`, `Duplicado`, `Forma normalizada`, `Variante plural`
- [x] Bounded context: `default` (list-items). Sin imports cruzados.
- [x] Aggregate root: `ShoppingList` (vía `$list->items()`). El delete del comprado homónimo va a través del agregado (`$list->items()->whereIn('id', ...)->delete()`).
- [x] Sin nuevas referencias inter-aggregate.
- [x] Sin nuevos domain events (la operación se enmarca como add normal; el delete del comprado es side effect interno, no se publica externamente).
- [x] Sin ACL adapter (todo dentro del mismo contexto).

### Data Flow

```
POST /api/lists/{list}/items  (body: { name, quantity?, unit?, category?, estimated_price? })
  ↓
ListItemController::store
  ↓ authorizeListWrite
  ↓
ListItemService::create($list, $data)
  ↓ DB::transaction
    1. normalized = SpanishInflector::normalize($data['name'])
    2. unit       = resolveUnit($data['unit'])  // null o ItemUnit value
    3. $list->items()->where('is_purchased', true)->get()
       → filter en PHP por: SpanishInflector::normalize($item->name) === normalized
                          AND ($item->unit?->value === unit OR ambos null)
       → si hay matches: $list->items()->whereIn('id', $matchIds)->delete()
    4. $item = $list->items()->create([...])
    5. syncCounters($list)
    6. logActivity(ItemAdded)
  ↓ commit
  ↓
return ['data' => ['item' => $item, 'counters' => ...]]
```

```
WeeklySummary save → ListItemService::createOrIncrement($list, $data)
  ↓ (sin DB::transaction interna; caller envuelve)
    1. normalized = SpanishInflector::normalize($data['name'])
    2. unit       = resolveUnit($data['unit'])
    3. existing = $list->items()
                       ->where('is_purchased', false)
                       ->get()
                       ->first(fn($i) => normalize($i->name) === normalized
                                       && unitMatches($i->unit, $unit))
       Si existe: incrementar quantity y return $existing
    4. Si no existe: aplicar lógica de delete de comprados homónimos (igual que create)
    5. Crear nuevo item pendiente
```

**Frontend flow** (`AddItemInput.jsx::findDuplicate`):

```
findDuplicate(newName):
  trimmed = newName.trim()
  if !trimmed: return null
  normalizedNew = spanishInflector(trimmed)
  for item of existingItems:
    if item.is_purchased: continue           // ← NEW: skip comprados
    normalizedExisting = spanishInflector(item.name)
    if normalizedNew === normalizedExisting: return item  // match exacto normalizado
    if similarText(trimmed, item.name) > 0.80: return item // fallback fuzzy para typos
  return null
```

### Transaction Boundaries

- `ListItemService::create()` ya usa `DB::transaction`. El delete del comprado homónimo se ejecuta **dentro** de la misma transacción, antes del `$list->items()->create()`. Rollback total ante fallo.
- `ListItemService::createOrIncrement()` no abre transacción propia; el caller (`WeeklySummaryService::saveSelection`) la abre. Se mantiene el contrato; el delete se hace dentro de la transacción del caller.
- Sin nuevas operaciones idempotentes (el add nunca fue idempotente; el delete del comprado es determinista dada la misma entrada y estado).

### Locking y race conditions

El código actual de `createOrIncrement` usa `->lockForUpdate()` sobre la query SQL de match exacto del pendiente. Al cambiar a filtro en PHP, mantenemos el `lockForUpdate` aplicado a la `Builder` previa a `->get()`:

```php
$pendings = $list->items()
    ->where('is_purchased', false)
    ->lockForUpdate()
    ->get();
// luego filtrar por SpanishInflector::normalize() in-memory
```

Esto bloquea **todas las filas pendientes de la lista** durante la transacción. Es un lock más amplio que el original (que solo bloqueaba la fila exacta del pendiente coincidente), pero acotado al `shopping_list_id`. Tradeoff aceptable: dos collaborators añadiendo simultáneamente a la **misma lista** se serializan, lo cual ya es deseable para evitar inconsistencias de counters.

**Race aceptada**: dos collaborators añaden el mismo nombre nuevo al mismo tiempo en una lista donde no existe ningún pendiente con ese nombre. Cada transacción no encuentra match y ambas crean un pendiente nuevo. Resultado: dos pendientes con normalización equivalente. Comportamiento idéntico al actual (no es regresión). El usuario puede reconciliar manualmente o el siguiente add con mismo nombre incrementará uno de los dos.

Para `create()` el lock equivalente aplica al subset `is_purchased = true` (para que el delete del comprado homónimo no compita con un toggle concurrente):

```php
$purchased = $list->items()
    ->where('is_purchased', true)
    ->lockForUpdate()
    ->get();
```

## Data Model

### Tables Affected

Ninguno modificado. Sin nuevas tablas. Sin nuevos índices (los lookups por `shopping_list_id` y `is_purchased` se sirven con el índice existente sobre `shopping_list_id`).

### Migrations

Ninguna.

### API Changes

Ninguna. Mismos endpoints, mismos contratos de request/response.

## Spanish Inflector — Reglas Detalladas

Función pura `normalize(name: string): string`. Determinista, sin estado, mismo input siempre produce mismo output. Implementación en PHP (`App\Support\Inflector\SpanishInflector::normalize`) y JS (`resources/js/lib/spanishInflector.js`, default export).

Pasos en orden:

1. **Trim** whitespace y colapsar espacios internos múltiples a uno.
2. **Lowercase** Unicode-aware (`mb_strtolower` en PHP, `String.prototype.toLocaleLowerCase('es')` en JS).
3. **Strip accents**: á→a, é→e, í→i, ó→o, ú→u, ü→u. La `ñ` se conserva. Implementación PHP: `Normalizer::normalize($s, Normalizer::FORM_D)` + regex `\pM` (intl extension). Implementación JS: `s.normalize('NFD').replace(/[̀-ͯ]/g, '')` excluyendo el combining tilde sobre `n` mediante reinserción (`ñ`→preservada).
4. **Check invariables**: si la palabra normalizada está en el set `INVARIABLES`, retornar tal cual.
5. **Aplicar reglas de depluralización** en orden, primer match gana:
   - **R1** — Si termina en `ces` y `length >= 5` → reemplazar `ces` por `z`. Ej: `arroces`→`arroz`, `peces`→`pez`, `luces`→`luz`, `lapices`→`lapiz`.
   - **R2** — Si termina en `s` y `length >= 4`, calcular dos candidatos y elegir por last-char del candidato `strip_es`:
     - `candidate_strip_s` = quitar el último char (`-1`).
     - `candidate_strip_es` = si la palabra termina en `es` (length ≥ 2 chars finales), quitar los dos últimos chars; si no, `null`.
     - **Si `candidate_strip_es` existe y su último char está en `{n, r, l, d, j, z, x}` (consonantes típicas de raíz singular en español)** → devolver `candidate_strip_es`. Ej: `panes`→`pan`, `papeles`→`papel`, `limones`→`limon`, `flores`→`flor`, `mujeres`→`mujer`, `redes`→`red`.
     - **En otro caso** → devolver `candidate_strip_s`. Ej: `tomates`→`tomate`, `cebollas`→`cebolla`, `manzanas`→`manzana`, `aguas`→`agua`, `leches`→`leche`, `casas`→`casa`, `pies`→`pie`.
   - **R3** — En caso contrario, devolver la palabra sin cambios.
6. Retornar resultado.

**Tabla de traza de las reglas sobre AC-5 (verificación)**:

| Input normalizado | Regla aplicada | strip_s | strip_es | Last char strip_es | Output |
|-------------------|----------------|---------|----------|--------------------|--------|
| `panes` | R2 | `pane` | `pan` | `n` (✓ set) | `pan` |
| `tomates` | R2 | `tomate` | `tomat` | `t` (✗) | `tomate` |
| `cebollas` | R2 | `cebolla` | n/a (no acaba en `es`) | n/a | `cebolla` |
| `leches` | R2 | `leche` | `lech` | `h` (✗) | `leche` |
| `manzanas` | R2 | `manzana` | n/a | n/a | `manzana` |
| `papeles` | R2 | `papele` | `papel` | `l` (✓) | `papel` |
| `lapices` | R1 | n/a | n/a | n/a | `lapiz` |
| `aguas` | R2 | `agua` | n/a | n/a | `agua` |
| `limones` | R2 | `limone` | `limon` | `n` (✓) | `limon` |
| `flores` | R2 | `flore` | `flor` | `r` (✓) | `flor` |
| `arroces` | R1 | n/a | n/a | n/a | `arroz` |
| `peces` | R1 | n/a | n/a | n/a | `pez` |
| `pies` | R2 | `pie` | `pi` | `i` (✗) | `pie` |
| `lunes` | INVARIABLE | — | — | — | `lunes` |
| `polla` | R3 (no acaba en s) | — | — | — | `polla` |
| `pollo` | R3 (no acaba en s) | — | — | — | `pollo` |
| `casas` | R2 | `casa` | `cas` | `s` (✗) | `casa` |
| `caso` | R3 | — | — | — | `caso` |

### Set INVARIABLES (cerrado, ampliable en futuras features)

Palabras donde stripear `-s` rompería el match consigo mismas:

```
lunes, martes, miercoles, jueves, viernes,
crisis, tesis, atlas, analisis, sintesis, dosis, hipotesis,
virus, oasis, paraguas, cumpleaños, abrelatas, sacacorchos
```

> Nota: los invariables se almacenan ya con acentos stripeados ("miercoles" no "miércoles"), porque el paso 3 ya normalizó la entrada antes de checkear.

### Contrato de tests compartido

Lista única de pares input→output que ambas implementaciones deben pasar idéntica. Vive en `docs/features/FEAT-DUPCHECK-ACTIVE/inflector-test-cases.md` (a generar en S4) y se importa/replica en `SpanishInflectorTest.php` y `spanishInflector.test.js`. Si las dos implementaciones divergen, los tests fallan en alguno de los dos lados.

Casos mínimos obligatorios (de AC-5):

| Input | Output esperado |
|-------|-----------------|
| `Pan` | `pan` |
| `Panes` | `pan` |
| `tomate` | `tomate` |
| `tomates` | `tomate` |
| `Tomates` | `tomate` |
| `cebolla` | `cebolla` |
| `cebollas` | `cebolla` |
| `Leche` | `leche` |
| `Leches` | `leche` |
| `Manzana` | `manzana` |
| `Manzanas` | `manzana` |
| `Papel` | `papel` |
| `Papeles` | `papel` |
| `Lápiz` | `lapiz` |
| `Lápices` | `lapiz` |
| `Agua` | `agua` |
| `Aguas` | `agua` |
| `Limón` | `limon` |
| `Limones` | `limon` |
| `Flor` | `flor` |
| `Flores` | `flor` |
| `Arroz` | `arroz` |
| `Arroces` | `arroz` |
| `Pez` | `pez` |
| `Peces` | `pez` |
| `Lunes` | `lunes` |
| `Martes` | `martes` |
| `Crisis` | `crisis` |
| `Pollo` | `pollo` (no se confunde con `polla`) |
| `Polla` | `polla` |
| `Casa` | `casa` |
| `Casas` | `casa` |
| `Caso` | `caso` (no comparte normalización con `casa`) |
| ` Pan ` | `pan` (trim) |
| `Pan  blanco` | `pan blanco` (espacios colapsados) |
| `Mañana` | `mañana` (ñ preservada) |
| `Cumpleaños` | `cumpleaños` (invariable, ñ preservada) |
| `Mujeres` | `mujer` (R2: strip_es termina en `r`) |
| `Redes` | `red` (R2: strip_es termina en `d`) |
| `Pies` | `pie` (R2: strip_es termina en `i`, vocal → fallback a strip_s) |

## Backend Implementation Plan

### Files to Create

1. `app/Support/Inflector/SpanishInflector.php`
   ```php
   namespace App\Support\Inflector;
   
   final class SpanishInflector
   {
       private const INVARIABLES = [
           'lunes', 'martes', 'miercoles', 'jueves', 'viernes',
           'crisis', 'tesis', 'atlas', 'analisis', 'sintesis', 'dosis', 'hipotesis',
           'virus', 'oasis', 'paraguas', 'cumpleanos', 'abrelatas', 'sacacorchos',
       ];
       
       public static function normalize(string $name): string
       {
           // 1. trim + collapse spaces
           // 2. lowercase
           // 3. strip accents (preservar ñ)
           // 4. check invariables
           // 5. apply depluralization rules
           // 6. return
       }
   }
   ```

2. `tests/Unit/Support/Inflector/SpanishInflectorTest.php` — tests parametrizados sobre los pares del contrato compartido.

### Files to Modify

1. `app/Services/ListItemService.php`

   - Inyectar `SpanishInflector` o usar como static call (es helper puro, static OK).
   - Añadir método privado `deletePurchasedHomonyms(ShoppingList $list, string $name, ?string $unit): void` que:
     - Calcula `$normalized = SpanishInflector::normalize($name)`.
     - Obtiene `$list->items()->where('is_purchased', true)->get()` (lazy load OK; volumen bajo).
     - Filtra in-memory por `normalize($item->name) === $normalized` y unit-match.
     - Si hay matches, ejecuta `$list->items()->whereIn('id', $ids)->delete()`.
   - Llamar este método al inicio del callback de `DB::transaction` dentro de `create()`, antes del `$list->items()->create()`.
   - En `createOrIncrement()`:
     - Reemplazar la query `whereRaw('LOWER(TRIM(name)) = ?', [$normalized])` por carga en-memoria + filtro por `normalize($item->name)`.
     - Mantener `where('is_purchased', false)` para el lookup del pendiente a incrementar.
     - Si no se encuentra pendiente, antes de crear, llamar `deletePurchasedHomonyms($list, $name, $unit)`.

2. `tests/Unit/Services/ListItemServiceTest.php` — añadir casos:
   - `test_create_deletes_purchased_homonyms_same_normalized_name`
   - `test_create_deletes_purchased_homonyms_singular_plural`
   - `test_create_deletes_all_purchased_homonyms_when_multiple_match`
   - `test_create_does_not_delete_purchased_with_different_unit`
   - `test_create_does_not_delete_purchased_with_different_name`
   - `test_create_or_increment_matches_normalized_name_for_pending_increment`
   - `test_create_or_increment_deletes_purchased_homonyms_when_creating_new`
   - `test_transaction_rollback_preserves_purchased_on_failure` (simular fallo post-delete)

3. `tests/Feature/ListItemControllerTest.php` (si existe) o nuevo feature test — integración HTTP cubriendo AC-1, AC-3, AC-10.

### Files Unchanged

- `ListItemController.php` — sin cambios. Mismo contrato HTTP.
- `CreateItemRequest.php` — sin cambios. Validación de input idéntica.
- Migraciones — ninguna nueva.

## Frontend Implementation Plan

### Files to Create

1. `resources/js/lib/spanishInflector.js`
   ```js
   const INVARIABLES = new Set([
     'lunes', 'martes', 'miercoles', 'jueves', 'viernes',
     'crisis', 'tesis', 'atlas', 'analisis', 'sintesis', 'dosis', 'hipotesis',
     'virus', 'oasis', 'paraguas', 'cumpleanos', 'abrelatas', 'sacacorchos',
   ]);
   
   export default function normalize(name) {
     // 1-6 según reglas de la sección Inflector
   }
   ```

2. `resources/js/lib/spanishInflector.test.js` — los mismos pares del contrato compartido.

### Files to Modify

1. `resources/js/components/items/AddItemInput.jsx`
   - Import `spanishInflector`.
   - Reescribir `findDuplicate`:
     - Pre-calcular `normalize(trimmed)`.
     - Iterar `existingItems`, **saltar** los `is_purchased = true`.
     - Match positivo si `normalize(item.name) === normalize(trimmed)` (exacto normalizado) o `similarText > 0.80` (fallback).

2. `resources/js/components/items/AddItemModal.jsx`
   - Misma reescritura de `findDuplicate`.

**Verificación de shape (`existingItems`)**:

- `ListDetailPage.jsx:994` pasa `existingItems={Object.values(items).flat()}` al `AddItemModal`. `items` viene de `getItemsForList` agrupado por categoría; los objetos son `ListItem` con casts aplicados (incluyendo `is_purchased: boolean`). Confirmado.
- `SharedListPage.jsx:505` pasa `AddItemInput` **sin** `existingItems` (default `[]`). El check de duplicado en página compartida no opera. Sin regresión: el comportamiento previo era idéntico (sin warnings en shared). Fuera de scope de esta feature; si se quisiera activar el check ahí, sería una feature nueva.

3. `resources/js/components/items/AddItemInput.test.jsx` y `AddItemModal.test.jsx` (si existen — verificar en S4):
   - Test: warning no aparece si match es contra item comprado.
   - Test: warning sí aparece si match es contra item pendiente con variante plural.
   - Test: warning aparece para typo (fuzzy match conservado).
   - Test: `is_purchased` mixto: lista con un comprado y un pendiente del mismo producto → warning solo si match contra el pendiente.

### Files Unchanged

- `DuplicateWarning.jsx` — sin cambios. Mismo componente visual y de comportamiento.
- `similarText.js` — sin cambios. Se mantiene como fallback fuzzy.

## Performance

### Query Cost

Por cada `POST /api/lists/{list}/items`:

- 1 query adicional: `SELECT * FROM list_items WHERE shopping_list_id = ? AND is_purchased = true` (usado por `deletePurchasedHomonyms`).
- 0 o 1 query de delete: `DELETE FROM list_items WHERE id IN (...)` cuando hay matches.
- Volumen esperado por lista: ≤100 items totales, de los cuales típicamente <30 son comprados. Carga en memoria es trivial.

Cost upper bound: O(N) PHP-side filter sobre comprados, donde N ≤ 100. Despreciable.

### Frontend Cost

`findDuplicate` itera `existingItems` (≤100). Por cada item, calcula `normalize(item.name)` (O(longitud del nombre), pocos chars). Sin memoización; recomputo en cada keystroke aceptable dado el tamaño.

Optimización futura (no en scope): memoizar `normalize(item.name)` por item ID si `existingItems` se vuelve grande. No implementar hasta que haya evidencia.

### Caching

Sin nuevo caching. `normalize()` es pura y rápida; sin necesidad de cache LRU.

### N+1

No introduce N+1. Una sola query `SELECT` por add. No se itera Eloquent dentro de bucles.

## Security

- Autorización sin cambios: `authorizeListWrite($list)` en el controller protege el acceso. El delete del comprado opera solo dentro de la lista a la que el usuario ya tiene write access.
- Sin nueva superficie de input: el cliente sigue enviando el mismo payload (`name`, `quantity`, `unit`, `category`, `estimated_price`).
- Sin riesgo de injection: `SpanishInflector::normalize()` no toca SQL; los filtros se aplican en PHP sobre objetos Eloquent ya hidratados.
- Sin información sensible expuesta: el delete del comprado homónimo no se devuelve al cliente (no aparece en response). El cliente percibe la lista actualizada vía `counters` y, en el próximo `GET /items`, ya no ve el comprado.

## Trade-offs

| Decisión | Pros | Cons | Resultado |
|----------|------|------|-----------|
| Normalización singular/plural con reglas heurísticas españolas | Determinista. Sin dependencias externas. Implementación liviana (~50 líneas). Cubre 95% de productos de compra. | Falla en irregulares (no cubre: pie/pies con stem distinto; raíz/raíces). Mantenimiento al añadir invariables. | **Seleccionado** |
| Usar librería externa (`doctrine/inflector` o equivalente) | Soporta más casos. Bien testeada. | Depende de regla por idioma; soporte español parcial; añade dependencia para una utilidad pequeña. | Rechazado |
| Llamar a Claude/IA para normalizar nombres | Maneja irregulares y sinónimos. | Latencia, coste, no determinista, requiere mocking complejo en tests, viola el principio de "no AI en hot path". | Rechazado |
| **Filtro PHP en memoria** sobre comprados de la lista | Sin migración. Simple. Volumen pequeño. | Carga todos los comprados de la lista por add. | **Seleccionado** (v1) |
| Columna `normalized_name` en `list_items` + índice | Lookup O(log N) indexado. Permite usar `WHERE normalized_name = ?`. | Requiere migración, backfill de items existentes, sincronización en cada update. | Rechazado para v1; reservado como optimización futura si listas crecen |
| Stemmer compartido replicado en PHP y JS | Cada lado opera local; sin latencia. Mismo contrato de tests fija paridad. | Dos implementaciones a mantener. | **Seleccionado** |
| Stemmer solo backend; FE consulta endpoint para normalizar | Single source of truth. | Latencia por keystroke. Innecesario para reglas simples. | Rechazado |
| Match fuzzy backend (similarText) | Captura typos servidor también. | El usuario ya tiene oportunidad en frontend; backend solo necesita match preciso para el delete (false positives en delete son destructivos). | Rechazado: backend usa solo match exacto normalizado |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Stemmer drift entre PHP y JS | Med | Med | Contrato de tests compartido (`inflector-test-cases.md`); CI ejecuta ambos en cada PR. Si una rama actualiza reglas en un lado, los tests del otro fallan. |
| Over-match destructivo: borra comprado equivocado | High | Low | Reglas conservadoras (raíz mínima 3 chars; verificación vocal-consonante antes de strip). AC-8 cubre el caso `pollo`/`polla`. AC-6 cubre unit mismatch. Test exhaustivo. |
| Under-match: dos items que el usuario percibe como mismo producto no se reconcilian | Med | Med | Comportamiento idéntico al actual (sin regresión). Fallback fuzzy (`similarText > 0.80`) sigue activo en frontend para casos no cubiertos por stem. Aceptable: el usuario puede borrar manualmente. |
| Encoding/locale: ñ, ç, palabras con diéresis | Low | Low | Tests específicos para `mañana`, `niño`. `ñ` preservada explícitamente; otros casos raros (ç, ü) cubiertos por strip de combining marks. |
| Delete del comprado en transacción + race con otro collaborator añadiendo el mismo item | Low | Low | `lockForUpdate` sobre el subset `is_purchased = true` durante el delete y sobre `is_purchased = false` durante el lookup de increment. Serializa adds concurrentes sobre la misma lista. Race aceptada documentada en sección "Locking y race conditions". |
| Backwards compat: items existentes con nombres no normalizados | Low | High | No requiere backfill. `normalize()` se aplica on-the-fly al comparar. Items existentes siguen funcionando porque el storage del `name` no cambia. |
| Set INVARIABLES incompleto: aparece palabra del set en producción que no normalizamos correctamente | Low | Med | Documentado como conjunto cerrado ampliable. Si se descubre caso, se añade en patch posterior. Sin impacto destructivo (solo falla el match, no produce delete erróneo). |
| Words con stem singular que termina en `s` (e.g. `bus/buses`, `pais/paises`) | Low | Low | R2 no las normaliza correctamente (`buses`→`buse` en vez de `bus`). Aceptado: no son items habituales de lista de compra. El match exacto sigue funcionando si el usuario escribe consistentemente. Documentado como Known Limitation. |

## Open Questions

Ninguna. Diseño implementable sin clarificaciones adicionales.

## Implementation Notes (para S4)

Orden sugerido de implementación:

1. **S4.1** — Crear `SpanishInflector.php` + test parametrizado. Verificar 100% coverage en el helper.
2. **S4.2** — Crear `spanishInflector.js` + test parametrizado. Mismo contrato; mismo output.
3. **S4.3** — Modificar `ListItemService::create()` con `deletePurchasedHomonyms`. Extender tests existentes en `ListItemServiceTest`.
4. **S4.4** — Modificar `ListItemService::createOrIncrement()` con normalización. Extender tests.
5. **S4.5** — Frontend: actualizar `AddItemInput.jsx` y `AddItemModal.jsx`. Extender tests Vitest.
6. **S4.6** — Smoke test end-to-end (manual o feature test): AC-1, AC-3, AC-10.

Branch: `feature/FEAT-DUPCHECK-ACTIVE-dup-vs-active-only`.

Commit prefix: `[FEAT-DUPCHECK-ACTIVE]`.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation (backend-developer → frontend-developer)
- Required Artifacts: `02-prd.md`, `03-technical-design.md`
