# Technical Design: FEAT-REC-SAVE-PARTIAL

## Overview

Reemplazo del flujo "convertir todo a lista nueva" por **guardado parcial con destino seleccionable**. El usuario elige un subconjunto de recomendaciones del `WeeklySummary` y las envía a una lista activa existente o a una nueva. El `payload_json` se muta tras cada guardado: los items guardados se eliminan del payload, y cuando queda vacío, el summary se marca con un nuevo estado terminal `WeeklySummaryStatus::Actioned` que lo oculta del endpoint `latest`.

La operación se ejecuta en una sola transacción atómica con pessimistic lock sobre la fila del summary para evitar mutaciones concurrentes (dos pestañas, dos requests simultáneos del mismo usuario). Para lista existente se aplica upsert-por-nombre: si ya existe un item con mismo `name` normalizado (trim+lowercase) y misma `unit` y `is_purchased = false`, se incrementa `quantity`; en caso contrario se crea item nuevo. Items con distinta unidad o ya comprados se tratan como entradas distintas.

El endpoint `POST /weekly-summary/{summary}/convert-to-list` se reemplaza por `POST /weekly-summary/{summary}/save`. El flujo legacy se elimina (decisión PO: rollout directo sin feature flag, no se mantiene en paralelo).

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain (modelos + enums) | Estado del summary, invariante de "actuado cuando payload vacío", value objects de items dentro del payload | `App\Models\WeeklySummary`, `App\Enums\WeeklySummaryStatus` (añadir `Actioned`) |
| Application (Services) | Caso de uso "guardar selección": validar selección, mutar payload, crear/actualizar items en lista destino, marcar summary actuado, gestionar transacción y locks | `App\Services\WeeklySummaryService::saveSelection()` (nuevo método) — orquesta `ShoppingListService` (creación de lista nueva), `ListItemService::createOrIncrement()` (nuevo método, upsert-por-nombre) |
| Infrastructure | Persistencia Eloquent, generación de identificadores, lock pesimista | Eloquent en los Services (sigue convención del proyecto — ver Trade-offs §1) |
| Controllers/API | Validación HTTP, autorización (ownership), traducción de excepciones a códigos de error, mapeo a JSON | `App\Http\Controllers\WeeklySummaryController::save()` (nuevo método), `App\Http\Requests\SaveWeeklySummarySelectionRequest` (nuevo FormRequest) |
| Frontend (React) | Estado de selección local, apertura del sheet, llamada API, recarga del summary tras guardado parcial, redirección tras guardado total | `resources/js/pages/WeeklySummaryPage.jsx` (modificada), `resources/js/components/weekly-summary/SaveTargetSheet.jsx` (nuevo), `resources/js/lib/weeklySummaryApi.js` (nuevas funciones) |

**Dependencia hacia adentro**: Controllers → Services → Models. El servicio `WeeklySummaryService` no importa nada de `Http\` ni `Mail` (salvo lo ya existente). Sigue el patrón actual del repo.

**DDD checklist:**
- [x] `docs/contexts/default/00-glossary.md` extendido en S2 con términos de recomendaciones — todos los conceptos del feature están en el glossary.
- [x] Bounded context único (`default` = listas-compra). No hay imports cross-context.
- [x] Aggregate root: `WeeklySummary`. Invariante: "si `payload_json` queda vacío, `status` debe ser `Actioned`". Se enforza en `saveSelection()`.
- [x] Inter-aggregate refs: `WeeklySummary.user_id`, `target_list_id` apuntando a `ShoppingList` por ID. No hay object references entre aggregates.
- [x] Domain events: este feature NO emite eventos de dominio (operación local del usuario, sin necesidad de notificar otros bounded contexts ni jobs externos). Se documenta explícitamente para evitar sobreingeniería.
- [x] No hay ACL adapter porque no se cruzan contextos.

### Data Flow

```
[Usuario marca/desmarca items en WeeklySummaryPage]
  └─ estado local React (Set<int> selectedIndices)

[Usuario pulsa "Guardar N items"]
  └─ abre <SaveTargetSheet> (bottom sheet mobile / modal desktop)
     └─ carga listas activas via GET /lists (reusa endpoint existente)

[Usuario elige destino (lista existente | "+ Nueva lista") y confirma]
  └─ POST /weekly-summary/{summary}/save
     │  body: { selected_indices: int[], target_list_id: int | null, new_list_name?: string }
     │
     ▼
  [WeeklySummaryController::save]
     ├─ FormRequest valida tipos y rangos básicos
     ├─ Authorize: $summary->user_id === auth()->id()
     ├─ Si target_list_id != null: valida pertenencia + status=Active vía rule custom
     ▼
  [WeeklySummaryService::saveSelection] (DB::transaction)
     ├─ ① $summary->lockForUpdate() en DB
     ├─ ② Re-leer payload_json desde DB (fresh)
     ├─ ③ Validar que cada selected_index ∈ [0, count(payload)-1]
     ├─ ④ Resolver lista destino:
     │     ├─ if target_list_id: ShoppingList::find + lockForUpdate + ownership check
     │     └─ else: ShoppingListService::create() (puede tirar OverflowException → FREEMIUM_LIMIT)
     ├─ ⑤ Para cada item seleccionado del payload:
     │     └─ ListItemService::createOrIncrement($targetList, $itemData)
     │          ├─ SELECT ... FOR UPDATE WHERE list_id = ? AND LOWER(TRIM(name)) = ? AND unit = ? AND is_purchased = 0
     │          ├─ if found: $existing->update(['quantity' => $existing->quantity + $newQuantity])
     │          └─ else: $list->items()->create([...]) con position = max(position)+1
     ├─ ⑥ Reescribir payload_json removiendo los índices guardados (preservando orden de los restantes)
     ├─ ⑦ Si payload queda vacío: $summary->update(['status' => WeeklySummaryStatus::Actioned])
     ├─ ⑧ syncCounters() en lista destino (reuso de helper existente)
     ▼
  Response 200: { data: { list: {...}, summary: { id, status, remaining_items: [...], is_actioned: bool } } }

[Frontend recibe respuesta]
  ├─ if summary.is_actioned: navigate('/app/listas/{list.id}') (mismo patrón actual con setTimeout 1.5s)
  └─ else: actualiza estado local con summary.remaining_items, muestra banner "✓ N items añadidos a 'X'. Quedan M pendientes."
```

### Transaction Boundaries

- **Una sola transacción** por request (`DB::transaction(...)`), arranca en `WeeklySummaryService::saveSelection()` y abarca: lock del summary, lock de la lista destino (si existente), creación/actualización de items, mutación del payload, marcado del estado.
- **Lock pesimista** sobre la fila del summary (`->lockForUpdate()`) — previene que dos requests simultáneos del mismo usuario lean el mismo `payload_json` y dupliquen items.
- **Lock pesimista** sobre la fila de la lista destino (si existe) — previene race entre este flujo y operaciones concurrentes que modifiquen `items_total`/`items_completed`.
- **Lock pesimista** sobre items candidatos al upsert por nombre (`SELECT ... FOR UPDATE`) — previene que dos selecciones concurrentes a la misma lista creen dos items "Leche" en lugar de uno con cantidad sumada.
- **Rollback**: si cualquier paso falla (validación tardía, query exception, OverflowException por freemium en creación de lista nueva), la transacción se revierte. Ni se modifica el payload, ni se crean items, ni se marca el summary actuado. El cliente puede reintentar sin estado parcial.
- **Idempotencia**: la operación NO es idempotente per se (cada request genera mutación visible — items añadidos / payload reducido). Se mitiga con: (a) botón disabled con loading state durante la request; (b) lock impide duplicación en doble click rápido (la segunda transacción ve el payload ya mutado y los selected_indices originales serán inválidos → 422). El cliente debe manejar 422 mostrando "Selección obsoleta, recarga la página".

## Data Model

### Tables Affected

| Tabla | Cambio |
|-------|--------|
| `weekly_summaries` | Sin cambio de schema. Columna `status` sigue siendo `varchar(20)`. Se añade un valor de enum `actioned` que el cast PHP traduce automáticamente. |
| `list_items` | Sin cambio de schema. Inserciones nuevas y updates de `quantity` sobre filas existentes. |
| `shopping_lists` | Sin cambio de schema. Update de `items_total` ya hecho por helper existente. |

### New Tables/Collections

Ninguna. **Sin migración de schema.** La feature reutiliza columnas existentes.

### Enum Change

- `App\Enums\WeeklySummaryStatus`: añadir `case Actioned = 'actioned';`.
- Justificación de elegir enum frente a columna `actioned_at`: coherencia con el patrón existente (`Pending`, `Dispatched`, `Failed` ya son enum; añadir un timestamp paralelo introduciría dos fuentes de verdad para el ciclo de vida del summary). Trade-off documentado en §Trade-offs.
- **Sin migración DB requerida** porque el schema acepta cualquier string ≤20 chars en `status`.

### API Changes

| Endpoint | Method | Cambio | Body | Response |
|----------|--------|--------|------|----------|
| `/weekly-summary/{summary}/convert-to-list` | POST | **Eliminado** | — | — |
| `/weekly-summary/{summary}/save` | POST | **Nuevo** | `{ selected_indices: int[], target_list_id: int\|null, new_list_name?: string }` | `200 { data: { list: ShoppingList, summary: { id, status, payload_json: array, is_actioned: bool } } }` |
| `/weekly-summary/latest` | GET | **Modificado**: oculta también summaries con `status = actioned` (además del `failed` actual) | — | `404 NO_SUMMARY_THIS_WEEK` cuando actioned |
| `/lists` | GET | Sin cambio (reutilizado por el frontend para listar listas activas) | — | — |

**Códigos de error del nuevo endpoint:**
- `422 VALIDATION_FAILED`: índices fuera de rango, lista destino no propia, lista destino archivada, selección vacía, payload mutado entre lectura y escritura (selected_indices no coinciden con el payload actual tras tomar el lock).
- `403 FREEMIUM_LIMIT`: al crear nueva lista cuando ya hay 3 activas (propagado desde `ShoppingListService::create`).
- `404`: summary no pertenece al usuario.
- `200`: éxito.

### Indexing

Sin nuevos índices. Las queries del flujo:
- `SELECT ... FROM weekly_summaries WHERE id = ? FOR UPDATE` — usa PK.
- `SELECT ... FROM shopping_lists WHERE id = ? FOR UPDATE` — usa PK.
- `SELECT ... FROM list_items WHERE shopping_list_id = ? AND LOWER(TRIM(name)) = ? AND unit = ? AND is_purchased = 0 FOR UPDATE` — usa el índice existente sobre `shopping_list_id`. La función `LOWER(TRIM(...))` no usará índice pero el dataset por-lista es pequeño (<100 items típicos), aceptable.
- `SELECT MAX(position) FROM list_items WHERE shopping_list_id = ?` — usa índice sobre `shopping_list_id`.

## Performance

### Query Optimization

- **Operación O(N)** sobre el número de items seleccionados (típicamente <20). Cada item: 1 SELECT FOR UPDATE + 1 INSERT/UPDATE + posiblemente 1 SELECT MAX(position).
- **Sin N+1**: las llamadas a `ListItemService::createOrIncrement` se ejecutan secuencialmente dentro de la misma transacción, no en un loop con `each()` que cause re-fetch.
- **Optimización opcional**: cargar todos los items existentes de la lista destino una vez (`->lockForUpdate()->get()`), construir un mapa por nombre normalizado, y resolver upsert en memoria. Reduce queries de O(N) a O(1) SELECT + O(N) INSERTs/UPDATEs. **Recomendado**.

### Caching Strategy

No aplica. La operación es transaccional y no hay datos cacheables. El listado de listas activas en el frontend (`GET /lists`) ya usa el cache del navegador (cabecera ETag si existe; si no, no se introduce cache nuevo).

### Frontend Performance

- Selección local con `useState(new Set<number>())`. Re-render localizado por checkbox toggle. N<20 → sin necesidad de virtualización ni memoization.
- Sheet/modal: lazy-mount (no renderizar el componente hasta que el usuario abra el sheet). `Suspense` no necesario.
- Navegación tras guardado total: mismo patrón `setTimeout(navigate, 1500)` ya en uso.

## Security

### Authentication

- Endpoint dentro del grupo `auth('api')` ya existente. Sin cambios en el guard.

### Authorization (CRÍTICO — AC-11, AC-14)

- **Summary ownership**: `$summary->user_id !== auth('api')->id()` → `abort(404)` (404 no 403 para no revelar existencia).
- **Target list ownership**: si `target_list_id != null`, validar dentro del FormRequest mediante regla custom o explícitamente en el servicio:
  ```php
  $list = ShoppingList::where('id', $request->target_list_id)
      ->where('user_id', auth('api')->id())
      ->where('status', ListStatus::Active)
      ->lockForUpdate()
      ->first();
  if (!$list) abort(404);
  ```
- **Rationale**: una validación combinada (id + user_id + status) en una sola query previene tanto IDOR (lista de otro usuario) como AC-14 (lista archivada). El `lockForUpdate()` se aplica dentro de la transacción.

### Input Validation

`SaveWeeklySummarySelectionRequest` (FormRequest):
```php
return [
    'selected_indices' => ['required', 'array', 'min:1', 'max:50'],
    'selected_indices.*' => ['integer', 'min:0'],
    'target_list_id' => ['nullable', 'integer', 'exists:shopping_lists,id'],
    'new_list_name' => ['nullable', 'string', 'max:80', 'required_if:target_list_id,null'],
];
```
- `min:1` enforza AC-13 (no selección vacía).
- `max:50` cap defensivo (los summaries no exceden ~10 items por configuración del prompt).
- La validación de "índice ∈ rango del payload" ocurre **dentro del servicio tras tomar el lock** (no en FormRequest), porque el rango depende del estado actual del payload tras posibles mutaciones concurrentes.
- `target_list_id` con `exists` es defensa básica; la verificación de ownership y status va en el servicio (FormRequest no tiene acceso al user de forma robusta para este caso).

### Data Protection

- Sin nuevos campos sensibles. `payload_json` ya almacena nombres de productos del usuario, no PII adicional.
- Nombres del payload se persisten en `list_items.name` tal cual vienen del summary — ya validados por el `PromptSanitizer` durante la generación. No se requiere re-saneo.

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| **Hexagonal estricto con port `WeeklySummaryRepositoryInterface` + adapter Eloquent** | Domain layer 100% framework-agnostic, alineado con `core.md §2.1` | El proyecto entero usa Eloquent directo en Services; introducir un puerto solo para este feature crea inconsistencia interna. Ningún otro Service del repo lo hace. | **Rechazada** — se sigue el patrón establecido del proyecto (Service con Eloquent directo). Documentado como deuda técnica colectiva del repo, no de este feature. |
| **Patrón Service-with-Eloquent (existente)** | Coherente con `WeeklySummaryService`, `ShoppingListService`, `ListItemService` actuales. Reduce fricción de revisión. | No estrictamente hexagonal. | **Seleccionada** |
| **Estado nuevo `WeeklySummaryStatus::Actioned`** | Coherente con enum existente (`Pending`, `Dispatched`, `Failed`). Una sola fuente de verdad para el ciclo de vida del summary. Mismo cast Eloquent ya en el modelo. Sin migración. | Menos información temporal (no se sabe *cuándo* se actuó). | **Seleccionada** |
| Columna `actioned_at` timestamp | Permite ordenar/filtrar por momento de la acción. | Introduce dos fuentes de verdad (status + actioned_at). Requiere migración. Las queries de visibilidad necesitan unir ambas condiciones. | Rechazada — no hay requisito de auditoría temporal en el PRD. |
| **Endpoint nuevo `/save` reemplaza `/convert-to-list`** | Contrato limpio sin compatibilidad legacy. Frontend actualizado en el mismo PR. | Si hay clientes externos consumiendo `/convert-to-list`, se rompe. | **Seleccionada** — no hay clientes externos del API según `routes/api.php`. PO confirmó rollout directo. |
| Mantener `/convert-to-list` deprecado en paralelo | Compatibilidad hacia atrás. | Doble código, doble test, decisión de cuándo retirar. PO descartó feature flag → no aplica este modo. | Rechazada |
| **Pessimistic lock (`lockForUpdate`)** | Garantía fuerte contra race conditions. Patrón ya en uso en `ShoppingListService::create` y `restore`. | Bloquea filas durante la transacción (típicamente <100ms). | **Seleccionada** |
| Optimistic lock (compare-and-swap sobre `updated_at`) | Sin bloqueo, mayor concurrencia. | Requiere reintentos del cliente en colisión. Más complejo en tests. Ningún otro flujo del repo lo usa. | Rechazada — over-engineering para el patrón concurrente esperado (raro: mismo usuario, dos pestañas). |
| **Upsert por nombre normalizado (trim+lowercase) + misma unit + is_purchased=false** | Coincide con la expectativa del usuario ("ya tengo Leche pendiente, súmamela"). Items comprados o con distinta unidad no se mezclan (semánticamente distintos). | Lógica nueva (no hay upsert existente). Tests nuevos. | **Seleccionada** |
| Upsert solo por nombre (ignorar unit) | Simpler. | "Aceite 1 L" + "Aceite 1 ud" se mezclarían incorrectamente. | Rechazada |
| Sin upsert (siempre crear nuevo item) | Simpler. | Contradice AC-8 explícito del PRD. | Rechazada |
| **Cargar todos los items de la lista destino una vez + mapa en memoria** | O(1) SELECT en lugar de O(N). Más simple de razonar. | Carga items aunque no haya match. | **Seleccionada** — para listas con <100 items es trivial. |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Race condition: dos pestañas del mismo usuario guardan a la vez | Med | Low | Pessimistic lock sobre la fila del summary; segunda transacción ve payload mutado, valida y rechaza con 422. Frontend muestra mensaje "Selección obsoleta, recarga la página". |
| Lista destino archivada entre apertura del sheet y confirmación | Low | Low | Validación dentro de la transacción (`status = Active` en la query con `lockForUpdate`). Si cambió, 404 controlado. |
| `selected_indices` desincronizados con `payload_json` (cliente con vista vieja) | Med | Med | Validación post-lock dentro del servicio: si algún índice ≥ count(payload_actual), 422 con mensaje claro. |
| Upsert por nombre: nombres con caracteres unicode/acentos diferenciados ("Café" vs "Cafe") | Low | Low | Normalización con `Str::lower(trim($name))` (Laravel) preserva acentos — match es case-insensitive solo. Se documenta: "Café" y "Cafe" se tratan como distintos. Aceptable. |
| Suma de cantidades produce `quantity > 99999.99` (overflow del decimal:2,5) | Low | Very Low | Cap defensivo en el servicio: si suma > 99999, no sumar y crear item nuevo. Tests cubren el límite. **Verificar en S4 el tipo exacto de la columna `quantity` en la migración**. ⚠️ Unverified — confirmar contra `database/migrations/*list_items*`. |
| Eliminar `/convert-to-list` rompe clientes que aún lo llamen tras despliegue (race entre asset bundle viejo y backend nuevo) | Low | Low | Mantener route definida durante el despliegue del backend, retornando 410 Gone. Frontend nuevo se despliega tras backend. Tras 24h se elimina la ruta. **Plan de despliegue documentado en S4**. |
| Tests existentes de `WeeklySummary` cubren `convertToList` legacy → fallarán | Med | High | Adaptar `WeeklySummaryServiceTest` y `WeeklySummaryEndpointsTest` en S4. Mantener cobertura 100%. |
| `payload_json` mutable rompe alguna asunción de inmutabilidad en otro código | Med | Low | Auditoría: `WeeklySummaryService::generateForUser` ya escribe el payload (no es inmutable a nivel código). `dispatchEmailFor` solo lee. Sin otros consumidores en el repo. |
| Bottom sheet en mobile bloquea el scroll del fondo si no se gestiona body overflow | Low | Med | Implementar `overflow: hidden` en `<body>` mientras el sheet está abierto. Patrón estándar. Tests de a11y verifican focus trap. |
| Cobertura 100% de `createOrIncrement` exige tests de cada rama (match same unit, match different unit, match purchased=true, no match, overflow) | Med | High | Test plan explícito en S4. Lista de casos en §Implementation Notes. |

## Open Questions

Ninguna pendiente. Todos los TBDs listados en `01-scope.md` y `02-prd.md` quedan resueltos por las decisiones de esta sección. Las mitigaciones marcadas "verificar en S4" son tareas concretas, no decisiones abiertas.

## Implementation Notes

### Backend file plan

| Acción | Path |
|--------|------|
| Modificar | `app/Enums/WeeklySummaryStatus.php` (añadir `Actioned`) |
| Modificar | `app/Services/WeeklySummaryService.php` (eliminar `convertToList`, añadir `saveSelection`) |
| Modificar | `app/Services/ListItemService.php` (añadir `createOrIncrement`) |
| Modificar | `app/Http/Controllers/WeeklySummaryController.php` (eliminar `convertToList`, añadir `save`; modificar `latest` para ocultar `actioned`) |
| Crear | `app/Http/Requests/SaveWeeklySummarySelectionRequest.php` |
| Modificar | `routes/api.php` (eliminar route `convert-to-list`, añadir `save`) |
| Modificar | `tests/Unit/Services/WeeklySummaryServiceTest.php` |
| Modificar | `tests/Feature/WeeklySummaryEndpointsTest.php` |
| Crear | `tests/Unit/Services/ListItemServiceCreateOrIncrementTest.php` (o ampliar el existente) |

### Frontend file plan

| Acción | Path |
|--------|------|
| Modificar | `resources/js/pages/WeeklySummaryPage.jsx` |
| Modificar | `resources/js/pages/WeeklySummaryPage.test.jsx` |
| Crear | `resources/js/components/weekly-summary/SaveTargetSheet.jsx` |
| Crear | `resources/js/components/weekly-summary/SaveTargetSheet.test.jsx` |
| Modificar | `resources/js/lib/weeklySummaryApi.js` (eliminar `convertSummaryToList`, añadir `saveSummarySelection`, añadir helper para listar listas activas si no existe) |

### Test cases (mínimos para 100% coverage)

Backend:
- `saveSelection` — happy path nueva lista (todos los items).
- `saveSelection` — happy path lista existente (nuevos items, sin duplicados).
- `saveSelection` — duplicado mismo nombre + misma unit → suma quantity (AC-8).
- `saveSelection` — duplicado mismo nombre + DIFERENTE unit → crea item nuevo.
- `saveSelection` — duplicado mismo nombre pero existente está purchased=true → crea item nuevo.
- `saveSelection` — selección parcial → payload restante correcto, status sigue `Pending`.
- `saveSelection` — selección total → payload vacío, status = `Actioned`.
- `saveSelection` — `selected_indices` fuera de rango → 422 (AC-12).
- `saveSelection` — lista destino de otro usuario → 404 (AC-11).
- `saveSelection` — lista destino archivada → 404 (AC-14).
- `saveSelection` — selección vacía → 422 (AC-13).
- `saveSelection` — al crear nueva lista, freemium agotado → 403 FREEMIUM_LIMIT (AC-6).
- `latest` — summary con status `Actioned` → 404.
- Idempotencia: doble save consecutivo con mismos índices → segundo falla con 422 (índices ya no apuntan a items existentes).
- Race: simular dos transacciones, una espera el lock, segunda ve payload mutado.

Frontend:
- Por defecto todos los items marcados, contador refleja N (AC-1).
- Toggle de checkbox actualiza estado y contador (AC-2).
- 0 selección → botón disabled (AC-3).
- Click en "Guardar" abre el sheet (AC-4).
- Sheet muestra listas activas y "+ Nueva lista" (AC-4).
- Si 3 listas activas → "+ Nueva lista" disabled con mensaje (AC-6).
- Confirmar con lista existente → POST con `target_list_id`, banner de éxito si quedan items, redirect si vacío.
- Confirmar con nueva lista → POST con `target_list_id=null`, redirect.
- Error 403 FREEMIUM_LIMIT → mensaje en banner.
- Error 422 → mensaje "Selección inválida".
- Tras guardado parcial: la página recarga el summary mostrando solo items pendientes (AC-9).
- Tras guardado total: la página redirige a `/app/listas/{id}` (AC-10).

### Frontend implementation hints

- Usar `useReducer` o `useState(new Set<number>())` para la selección. Iniciar con `new Set([0,1,...,n-1])` cuando llega el summary.
- Componente `SaveTargetSheet` recibe `props`: `{ isOpen, onClose, onConfirm(targetListId | null), activeLists, freemiumExceeded }`. La carga de `activeLists` es responsabilidad del padre (la página).
- Animación del sheet: transform translateY + transition. No requiere lib externa.
- Focus trap: implementación manual con `useEffect` que mueve focus al primer item al abrir y atrapa Tab/Shift+Tab. O usar el patrón ya existente en el repo si hay alguno (revisar en S4).
- A11y: `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, `aria-live="polite"` en el contador del CTA principal.

### Deployment plan

1. Mergear backend + frontend en el mismo PR (cambio coordinado).
2. Desplegar backend primero (la ruta `/convert-to-list` se elimina; clientes con bundle viejo recibirán 404 hasta cargar el bundle nuevo).
3. Desplegar frontend nuevo (mismo build).
4. Verificar logs de errores 24h post-deploy: si hay 404 sobre `/convert-to-list` por bundles cacheados, considerar invalidación de cache CDN.

### Stack-specific reminders (Laravel)

- ⚠️ Unverified: la firma exacta de `Str::lower()` y `trim()` con multibyte. Confirmar con `mb_strtolower($name)` en S4 si los tests con caracteres acentuados fallan.
- Migrations: NINGUNA en este feature (verificado en §Data Model).
- FormRequest sigue convención del repo (`extends FormRequest`, método `rules()`, namespace `App\Http\Requests`).
- Eager loading: `WeeklySummary::find($id)` no requiere eager loads adicionales para este flujo (no se navega a relaciones del summary tras guardar).

## Transition

- Gate Status: **S3 PASSED**
- Next Step: STEP 4 – Implementation
- Required Artifacts: `02-prd.md`, `03-technical-design.md`, `ux-wireframes.html`
