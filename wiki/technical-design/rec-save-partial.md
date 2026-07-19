# Technical Design — FEAT-REC-SAVE-PARTIAL

Transacción atómica con triple pessimistic lock (summary + target list + items candidatos) que muta `payload_json` y aplica upsert por nombre normalizado. Endpoint nuevo `/save` reemplaza legacy `/convert-to-list`.

## Arquitectura

| Layer | Responsabilidad | Key Class |
|-------|----------------|-----------|
| Domain | Invariante "payload vacío ⇒ status=Actioned" | `WeeklySummary`, `WeeklySummaryStatus::Actioned` (nuevo) |
| Application | Caso de uso `saveSelection`: lock, validar, mutar payload, marcar | `WeeklySummaryService::saveSelection()` |
| Application | Upsert por nombre normalizado | `ListItemService::createOrIncrement()` |
| Application | Crear nueva lista (propaga FREEMIUM_LIMIT) | `ShoppingListService::create()` |
| HTTP | Auth + validación + traducir excepciones | `WeeklySummaryController::save()`, `SaveWeeklySummarySelectionRequest` |
| Frontend | Selección local + sheet + redirect | `WeeklySummaryPage.jsx`, `SaveTargetSheet.jsx` |

## Flujo de datos

```
POST /api/weekly-summary/{summary}/save
  body: { selected_indices: int[], target_list_id: int|null, new_list_name?: string }
  ▼
WeeklySummaryController::save
  ├─ FormRequest valida tipos + min:1 + max:50
  ├─ $summary->user_id !== auth()->id() → 404
  ▼
DB::transaction(function () {
  ├─ ① $summary->lockForUpdate()
  ├─ ② re-leer payload_json desde DB (fresh)
  ├─ ③ validar selected_index ∈ [0, count(payload)-1] (post-lock)
  ├─ ④ resolver lista destino:
  │     target_list_id ? ShoppingList WHERE id=? AND user_id=? AND status=Active FOR UPDATE
  │                    : ShoppingListService::create() (puede tirar OverflowException → 403 FREEMIUM_LIMIT)
  ├─ ⑤ para cada item seleccionado:
  │     ListItemService::createOrIncrement($targetList, $itemData)
  │       ├─ SELECT FOR UPDATE WHERE list_id=? AND LOWER(TRIM(name))=? AND unit=? AND is_purchased=0
  │       ├─ if found: ++quantity
  │       └─ else: items()->create([..., position = max+1])
  ├─ ⑥ reescribir payload_json sin los índices guardados (preservar orden)
  ├─ ⑦ si payload vacío: $summary->update(['status' => Actioned])
  └─ ⑧ syncCounters() lista destino
});
  ▼
200 { data: { list, summary: { status, remaining_items, is_actioned } } }
```

## Data Model

| Tabla | Cambio |
|-------|--------|
| `weekly_summaries` | Sin schema change. Enum `status` acepta `'actioned'` (varchar(20)) |
| `list_items` | Sin schema change. Inserts + updates `quantity` |
| `shopping_lists` | Sin schema change. Update `items_total` via helper existente |

**Sin migración DB.** Enum `Actioned` es enum PHP, no enum SQL.

## API contract

| Endpoint | Method | Estado |
|----------|--------|--------|
| `/api/weekly-summary/{summary}/convert-to-list` | POST | **Eliminado** |
| `/api/weekly-summary/{summary}/save` | POST | **Nuevo** |
| `/api/weekly-summary/latest` | GET | Modificado: oculta `actioned` además de `failed` |

Códigos de error:

| Code | Causa |
|------|-------|
| 200 | éxito |
| 403 `FREEMIUM_LIMIT` | 3 listas activas + intento "+ Nueva lista" |
| 404 | summary no propio · target list no propia · target archivada |
| 422 | `selected_indices` vacío / fuera de rango (post-lock) / payload mutado entre lectura y escritura |

## Decisiones de diseño

| Decisión | Alternativa rechazada | Por qué |
|----------|----------------------|---------|
| Service-with-Eloquent | Hexagonal puro con Port `Repository` | Ningún otro Service del repo lo hace; introducir uno solo aquí crea inconsistencia. Documentado como deuda colectiva. |
| Enum `Actioned` | Columna `actioned_at` timestamp | Una fuente de verdad para ciclo de vida. Sin migración. |
| Pessimistic lock | Optimistic CAS sobre `updated_at` | Patrón ya en uso en `ShoppingListService::create/restore`. Concurrencia esperada baja (mismo user, dos tabs). |
| Upsert: name normalizado + unit + !purchased | Solo name (ignorar unit) | "Aceite 1L" + "Aceite 1ud" no deben sumar |
| Endpoint `/save` reemplaza `/convert-to-list` | Mantener legacy en paralelo | Sin clientes externos según `routes/api.php` |
| Cargar todos items de target en memoria | O(N) SELECTs | <100 items por lista → trivial |

## Seguridad

- **Authz crítico AC-11**: validación ownership + status Active + lock en una sola query (no `exists` en FormRequest)
- **`selected_indices` validado POST-lock** — el rango depende del payload mutable
- **`max:50` cap** defensivo (summary IA típicamente <20)
- **`payload_json` nombres** ya pasaron por `PromptSanitizer` en generación; no re-saneo

## Lock strategy

| Lock | Razón |
|------|-------|
| `WeeklySummary::lockForUpdate()` | Serializa requests mismo summary → segunda ve payload mutado → 422 |
| `ShoppingList::lockForUpdate()` (si existe) | Race con otras mutations de items_total/items_completed |
| Items `SELECT ... FOR UPDATE WHERE lista+name+unit+!purchased` | Evita que dos saves concurrentes creen 2 items "Leche" en vez de sumar |

## Frontend

- Selección: `useState(new Set<number>())` — inicializada con `new Set([0..N-1])`
- Sheet lazy-mount (no renderiza hasta `isOpen=true`)
- `useIsDesktop()` con `matchMedia('(min-width: 768px)')` — desktop = modal centrado, mobile = bottom sheet
- Focus trap manual con `keydown` Tab/Shift+Tab + `querySelectorAll(FOCUSABLE_SELECTOR)` + wrap-around
- Focus indicator: `box-shadow: 0 0 0 3px rgba(0,62,84,0.35)` reemplaza `outline:none`
- `<label>` envuelve el card → click en cualquier parte alterna checkbox por asociación nativa
- A11y: `role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-pressed` en filas, `aria-live="polite"` en contador

## Gotchas

- **LLM enum coercion (S6 hotfix)**: payload viene del LLM con `categoria`/`unidad_tipica` raw → Eloquent enum cast lanza `ValueError`. `createOrIncrement` debe coercer con `ProductCategory::tryFrom()?->value` y `ItemUnit::tryFrom()?->value`. Si no match → `null` (estado válido).
- **`LOWER(TRIM(name))` no usa índice** en MySQL. <100 items/lista → trivial. Futuro: columna `name_normalized` con índice si crece.
- **Idempotencia**: doble save consecutivo con mismos índices → segundo 422 (índices ya inválidos post-mutación). Cliente debe mostrar "Selección obsoleta, recarga".
- **Asimetría con `ListItemService::create`**: `createOrIncrement` no llama `logActivity()` ni dispatcha job AI inference (intencional).
- **Despliegue**: backend + frontend en mismo PR. Rutas viejas → 404 si bundle viejo cacheado (aceptado).

## Hallazgos críticos S5/S6

- **S5-UX iter1 CHANGES REQUIRED** (3 High a11y + 3 Medium): focus trap, focus indicator visible, contraste freemium `#41484c` (era `#9ca3af` ~1.16:1), CTA copy dinámica, modal centrado desktop, card clickable. Todos fixed iter2.
- **S6 HOTFIX**: LLM enum coercion missing → 500 en producción. Fix permanent en `createOrIncrement`. +2 tests.

Origen: `docs/features/FEAT-REC-SAVE-PARTIAL/03-technical-design.md`, `04-implementation-notes.md`, `05-*.md`.
