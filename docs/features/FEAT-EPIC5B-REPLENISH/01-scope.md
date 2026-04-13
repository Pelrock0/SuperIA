# Scope Analysis: FEAT-EPIC5B-REPLENISH

## Feature Request

Epic 5B — Alertas de reposicion inteligente + Sugerencias de ingredientes complementarios. 2 user stories:

- **HU-503** (Alertas reposicion): Banner no intrusivo en el dashboard que sugiere productos habituales no presentes en ninguna lista activa del usuario. Logica sin IA primero (frecuencia + recencia), Claude API como fallback para patrones ambiguos. Max 3 sugerencias simultaneas. Acciones: aceptar / ignorar (24h) / silenciar (permanente).
- **HU-504** (Complementarios): Chip bajo un item recien anadido sugiriendo productos que el usuario suele comprar juntos. Co-ocurrencia calculada en MySQL sobre `producto_historial` (ratio >= 0.60). Claude fallback para usuarios con <5 listas completadas. Best-effort, async, no bloquea el add path.

Consume la foundation de Epic 5A: `config/ai.php` (thresholds ya definidos), `ClaudeClientInterface`, `BudgetCap`, `AiUsageTracker`, `PromptSanitizer`, `HistoryAnonymizer`.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 35-45 hours |
| Confidence | High |

## Justification

HIGH because:
1. **Dos superficies UI nuevas** con flujos distintos: dashboard banner con acciones multiples (accept/ignore/silence) + chip inline bajo item anadido con fetch async.
2. **SQL no trivial para co-ocurrencia**: self-join sobre `producto_historial` agrupado por `lista_id`, calculando el ratio de pares sobre total de listas completadas del usuario. Performance sensible a medida que crece el historial.
3. **Algoritmo de reposicion con heuristica de frecuencia media** (dias entre compras, factor configurable). Edge cases: primer producto del usuario, producto comprado solo una vez, frecuencia muy variable.
4. **Dos nuevas tablas** (`user_silenced_products`, `ai_dismissed_suggestions`) con semantica distinta (permanente vs TTL 24h).
5. **Claude API integration en dos features mas**: Replenishment (patrones ambiguos) + Complement (usuarios nuevos). Ambas comparten el rate limit 20/dia Free establecido en 5A.
6. **Integra con dashboard existente** sin romper el contrato actual — nuevo endpoint separado, no modifica `GET /api/lists`.
7. **Coordinacion con Epic 3** (`ListItemController`): el endpoint de complementarios se dispara tras crear un item, pero no modifica el flujo de creacion. Desacoplado.
8. **Caching estrategico**: 5min por usuario para el dashboard banner para absorber refreshes frecuentes.
9. **Selector modal cuando user tiene multiples listas activas**: nuevo componente de seleccion.
10. **Thresholds compartidos ya en config/ai.php** desde 5A, pero este es el primer consumidor real — test que valida el wiring completo.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Medium | Co-ocurrencia SQL self-join puede ser lento con historiales grandes. Frecuencia media requiere agregaciones sobre `producto_historial`. Cache 5min mitiga el primer riesgo, no el segundo. |
| Data | Medium | 2 tablas nuevas (`user_silenced_products`, `ai_dismissed_suggestions`). Cascade delete on user_id cascade. No afecta schema existente. |
| Security | Medium | Primer uso de Claude API por un flujo automatico (disparado por accion del usuario, no por tipeo explicito). Sigue los mismos guardrails de 5A (sanitizer, anonymizer, budget cap, per-user quota). Nueva via de gasto: user anade muchos items → many complement calls. Rate limit 20/dia Free es el backstop. |
| Performance | Medium | Dashboard load += 1 query sync (cacheada 5min). Co-ocurrencia query ejecutada on-demand por cada complement request. Indice nuevo sobre `(user_id, lista_id)` en `producto_historial` deseable (ya existe `(user_id, producto_nombre)` y `(user_id, fecha_compra)` de Epic 3). |
| Operational | Low | Sin nuevas dependencias externas. Sin scheduled jobs nuevos en 5B. Rate limit reutiliza `ai_usage_log` + `AiUsageTracker` existentes. |

## Affected Areas

- **database/migrations/** — 2 nuevas:
  - `create_user_silenced_products_table` (silencio permanente per-user per-product)
  - `create_ai_dismissed_suggestions_table` (ignore TTL 24h per-user per-product)
- **app/Models/** — `UserSilencedProduct`, `AiDismissedSuggestion`
- **app/Enums/** — `ReplenishmentAction` (`accepted`, `ignored`, `silenced`) para documentacion y tests; no requiere columna en DB
- **config/ai.php** — Anadir `replenishment_factor = 0.8` bajo `thresholds`
- **app/Services/** — New:
  - `ReplenishmentSuggestionService` (query frecuencia + recencia, max 3, excluye silenciados + dismissed + productos en listas activas)
  - `ComplementarySuggestionService` (co-ocurrencia local + Claude fallback)
  - `ProductHistoryStatsService` (helper compartido: lista completada count, frecuencia media per-product, co-occurrence pair matrix)
- **app/Support/Ai/** — Extender `ClaudeClientInterface` con `suggestComplements(string $productName): array` + impl en `ClaudeClient` y `FakeClaudeClient`
- **app/Http/Controllers/** — New:
  - `ReplenishmentController` (GET dashboard, POST accept, POST ignore, POST silence)
  - `ComplementController` (GET complements para producto recien anadido)
- **app/Http/Requests/** — New FormRequests para accept/ignore/silence + complement query
- **routes/api.php** — Nuevos endpoints bajo el grupo JWT existente
- **resources/js/components/dashboard/** — Nuevo `ReplenishmentBanner.jsx` (hasta 3 chips, con 3 acciones por item) + `SelectListModal.jsx` (selector cuando >1 lista activa)
- **resources/js/components/items/** — Nuevo `ComplementaryChip.jsx` (inline bajo ItemRow, aparece tras crear item, dismissable)
- **resources/js/pages/DashboardPage.jsx** — Integrar `ReplenishmentBanner` arriba del listado de listas
- **resources/js/pages/ListDetailPage.jsx** — Integrar `ComplementaryChip` tras creacion exitosa de un item (reusa el flujo existente)
- **resources/js/lib/** — New `replenishmentApi.js`, `complementsApi.js`
- **tests/** — Full coverage: unit services, feature controllers, mailable (si aplica), frontend component tests
- **`ai_usage_log`**: **ya tiene columna `operation`** con enum `Suggestion|Generation|Summary|Complement|Replenishment`. Epic 5B usa `AiOperation::Replenishment` y `AiOperation::Complement` existentes. Ningun schema change para trackear breakdown — el admin ya puede desglosar por `operation`.

## Resolved Questions

1. **Algoritmo reposicion**: frecuencia media en dias entre compras de las ultimas N ocurrencias del producto. Sugerir si `dias_desde_ultima_compra > frecuencia_media_dias * replenishment_factor`, con `replenishment_factor = 0.8` configurable en `config/ai.php`. Sugiere **antes** de que se agote, no despues.

2. **Silenciar producto (HU-503 crit 4)**: nueva tabla `user_silenced_products` (user_id, producto_nombre, silenced_at). Unique `(user_id, producto_nombre)`. Silenciado permanente. Des-silenciar queda fuera de scope (futuro Epic settings).

3. **Ignorar sugerencia (HU-503 crit 4)**: nueva tabla `ai_dismissed_suggestions` (user_id, producto_nombre, dismissed_until) con TTL 24h. Se purga on-read (`WHERE dismissed_until > NOW()`). No `sessionStorage` — persiste across tabs y browsers.

4. **Accion "Aceptar"**: si el user tiene **1 lista activa** → anade el producto directamente a esa lista. Si tiene **varias** → abre `SelectListModal` para elegir destino. Si **ninguna** → el criterio de HU-503 crit 1 ya excluye este caso (el banner no se muestra).

5. **Sync vs job nocturno**: sync al abrir dashboard. Cache 5min por usuario (`Cache::remember("replenishment:user:{id}", 300, ...)`). Job nocturno queda fuera de 5B — sera para Epic 5C (HU-505 resumen semanal).

6. **"Lista completada" (HU-504 crit 6)**: opcion (a) — **100% de los items de la lista estan `is_purchased = true`**. Consistente con el flujo HU-306 (clear completed). Lista vacia no cuenta como completada.

7. **Fuente co-ocurrencia**: `producto_historial` agrupado por `lista_id`. Append-only, captura listas ya eliminadas. `list_items` descartado porque desaparece al borrar la lista.

8. **Threshold co-ocurrencia**: `0.60` (60%) confirmado en `config('ai.thresholds.co_occurrence_ratio')` desde Epic 5A.

9. **Claude fallback HU-504**: umbral `completed_lists < config('ai.thresholds.min_completed_lists')` (= 5). Prompt: "Cuando alguien anade {X} a su lista de compra espanola, ¿que 2 productos complementarios suele necesitar? Responde en JSON." Sanitizado via `PromptSanitizer` antes de pasar a Claude.

10. **Endpoint complementario**: opcion (b) — endpoint separado `GET /api/suggestions/complements?product=X&list_id=Y`. Disparado async tras creacion exitosa de item. **No modifica** `POST /api/lists/{list}/items` (Epic 3 intacto). El add path se mantiene rapido.

11. **Rate limit Claude**: **bucket unico** 20/dia Free compartido entre `Suggestion`, `Replenishment` y `Complement`. Implementacion: `AiUsageTracker::canUse` ya acepta un `AiOperation` param, pero el check debe sumar TODAS las operations de AI para el conteo diario. Se requiere un pequeno cambio en `AiUsageTracker::canUse` para que el quota check no filtre por operation, solo por date + success status. El breakdown por operation sigue disponible en `ai_usage_log` para el panel admin futuro.

12. **Scope monolitico**: Epic 5B contiene HU-503 + HU-504 en un solo feature. 35-45h estimadas. No split.

## Open Questions

None. All resolved.

## Recommendation

- [ ] Proceed directly (LOW -> STEP 1b)
- [x] Require PRD (MEDIUM/HIGH -> STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)

## Dependencies on Epic 5A

- `config/ai.php` thresholds (`min_completed_lists=5`, `co_occurrence_ratio=0.60`) already defined — Epic 5B is the first real consumer
- `ClaudeClientInterface` + `ClaudeClient` + `FakeClaudeClient` reused, extended with `suggestComplements`
- `PromptSanitizer`, `HistoryAnonymizer` reused as-is
- `BudgetCap` reused as-is (dual cap: global monthly + per-user daily)
- `AiUsageTracker` reused, with a **minor refactor** to make the daily quota shared across all AI operations (not per-operation)
- `ai_usage_log` table schema unchanged — already supports `Replenishment` and `Complement` in the `operation` enum

## Note on AiUsageTracker refactor

Resolved Question 11 requires a small tweak to `AiUsageTracker::canUse`: currently it filters by `operation` when counting today's usage. For the bucket-unico decision, the quota check must count across ALL operations. Implementation:

```php
public function canUse(User $user, AiOperation $operation): bool
{
    $plan = $this->planFor($user);
    $quota = $plan->dailySuggestionQuota();
    if ($quota === null) return true;
    return $this->usedTodayAcrossAllOperations($user) < $quota;
}
```

New method `usedTodayAcrossAllOperations` counts without the operation filter. `usedToday($user, $operation)` is kept for the breakdown/analytics use case. This is the smallest possible change — existing Epic 5A tests continue to pass because the behavior at the top level (`canUse`) is unchanged when only one operation is in use (suggestions).

Epic 5A's `ProductSuggestionService` will get the same benefit transparently: a user who has used 15 suggestions + 3 replenishment today has 2 remaining for any operation.
