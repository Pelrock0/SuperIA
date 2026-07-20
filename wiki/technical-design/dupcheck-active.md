# Technical Design — FEAT-DUPCHECK-ACTIVE

**Keywords:** SpanishInflector, singular/plural, is_purchased, deletePurchasedHomonyms, lockForUpdate, app/Support/Inflector

## Resumen

Cambio acotado a `ListItemService` + componentes `AddItem*` para que el check de duplicado ignore comprados y el add borre comprados homónimos, con match singular/plural español mediante un helper puro replicado en PHP y JS.

## Componentes

| Capa | Responsabilidad | Clase/Módulo |
|------|-----------------|--------------|
| Domain | Normalización singular/plural (función pura, sin I/O) | `App\Support\Inflector\SpanishInflector::normalize()` |
| Services | Borrado del comprado homónimo dentro de la transacción del add | `ListItemService::create()`, `createOrIncrement()`, privados `deletePurchasedHomonyms()` + `unitMatches()` |
| Frontend | Filtro `is_purchased` + match normalizado en `findDuplicate` | `AddItemInput.jsx`, `AddItemModal.jsx`, `resources/js/lib/spanishInflector.js` |

> **Hexagonal:** `SpanishInflector` es helper puro en `app/Support/` (patrón `App\Support\Price\*`, `App\Support\Ai\*`), sin port/adapter porque no hace I/O.

## Data flow (create)

```
POST /api/lists/{list}/items → ListItemController::store → authorizeListWrite
  → ListItemService::create($list, $data)  [DB::transaction]
     1. normalized = SpanishInflector::normalize(name)
     2. unit = resolveUnit(...)
     3. deletePurchasedHomonyms: $list->items()->where('is_purchased',true)
        ->lockForUpdate()->get()  → filtra en PHP por normalize()==normalized && unitMatches
        → whereIn('id',$ids)->delete()
     4. $list->items()->create([...])   5. syncCounters   6. logActivity
  → response { item, counters }   (los comprados borrados NO se devuelven; el cliente los verá ausentes en el próximo GET)
```

`createOrIncrement()` (llamado por `WeeklySummaryService::saveSelection`, sin transacción propia): busca pendiente por normalización → si existe incrementa; si no, borra comprados homónimos y crea.

## Reglas del inflector (`normalize`)

Función pura determinista, misma salida en PHP y JS. Pasos: trim+colapsar espacios → lowercase → strip acentos (**ñ preservada**) → check `INVARIABLES` → depluralización token-por-token:

- **R1:** termina en `ces` y len≥5 → `ces`→`z` (`arroces→arroz`, `lapices→lapiz`, `peces→pez`).
- **R2:** termina en `s` y len≥4 → dos candidatos: `strip_s` y `strip_es`. Si `strip_es` termina en `{n,r,l,d,j,z,x}` (consonante de raíz) → usar `strip_es` (`panes→pan`, `papeles→papel`, `limones→limon`, `flores→flor`, `mujeres→mujer`, `redes→red`); si no → `strip_s` (`tomates→tomate`, `cebollas→cebolla`, `aguas→agua`, `pies→pie`).
- **R3:** no termina en `s` → sin cambios (`pollo`, `polla` no colisionan).

`INVARIABLES` (set cerrado, ampliable): días de la semana, `crisis, tesis, virus, cumpleaños, paraguas`… almacenados ya sin acentos.

## Decisiones clave

- **Filtro PHP en memoria** (no SQL) sobre el subset comprado/pendiente con `lockForUpdate()`. Volumen ≤100 items/lista → coste despreciable. Rechazada columna `normalized_name`+índice para v1 (requeriría migración+backfill).
- **Stemmer replicado PHP+JS** con **contrato de tests compartido**: si una rama diverge, fallan los tests del otro lado. Rechazado stemmer solo-backend (latencia por keystroke) y librería externa (soporte español parcial) e IA (no determinista, latencia, coste).
- **Backend solo match exacto normalizado** para el delete (los false positives en delete son destructivos); el fuzzy `similarText>0.80` se mantiene solo en frontend para typos.

## Locking / races

`lockForUpdate()` sobre el subset (`is_purchased=true` para el delete, `false` para el lookup de increment) serializa adds concurrentes en la misma lista. Race aceptada (idéntica al comportamiento previo, sin regresión): dos colaboradores añaden el mismo nombre nuevo a la vez sin pendiente previo → se crean dos pendientes.

## Sin cambios

Esquema DB, migraciones, endpoints, contratos request/response, `DuplicateWarning.jsx`, `similarText.js`. Autorización intacta (`authorizeListWrite`).

## Known limitations

`bus/buses`, `país/países` (stem singular en `s`) y `tres` (len 4) no normalizan bien — documentado, no habituales en compra.
