# Scope Analysis: FEAT-EPIC5A-AUTOCOMPLETE

## Feature Request

Epic 5A — Autocompletado Inteligente (sub-feature de Epic 5 IA). 2 user stories:

- **HU-501**: Sugerencias de productos al escribir. Arquitectura de 3 capas con SLA <50ms para capas locales:
  - **Capa 1** (<20ms): full-text search sobre `producto_historial` personal (MySQL FULLTEXT index).
  - **Capa 2** (<50ms): busqueda en catalogo precargado ~2500 productos espanoles. Seed generado via Claude **una sola vez en este sprint**. Nunca llamada Claude en tiempo real para esta capa.
  - **Capa 3** (diferida, solo tras 2s de pausa): Claude API en background si capas 1+2 devuelven <3 resultados. Rate limit 20 llamadas/dia en plan Free.
- **HU-502**: Aprendizaje de productos habituales. Parcialmente ya entregado en Epic 3 (`producto_historial` existe). Pendiente:
  - (a) Ponderacion por recencia: ultimos 30 dias pesan mas en el orden de sugerencias.
  - (b) UI en perfil para ver y limpiar historial.

**Primer uso de Claude API en el proyecto**. Incluye setup completo del provider.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 45-55 hours |
| Confidence | High |

## Justification

HIGH because:
1. **Primer uso de Claude API en el proyecto**: requiere instalar SDK (`anthropic-sdk-php`), gestionar `CLAUDE_API_KEY`, definir timeout 30s, circuit breaker, budget cap global (mensual) + per-user (diario), logging, alerta email al admin si se supera el cap global. Es infraestructura reusable por Epic 5B, 5C, 6 y posteriores. Un error de diseno aqui afecta toda la plataforma IA.
2. **SLA estricto de rendimiento**: <50ms para capas 1+2 en una ruta server-side no es trivial. Requiere `FULLTEXT` index nuevo en `producto_historial`, cache de catalogo precargado en memoria o Redis, y queries cuidadosamente optimizadas.
3. **Catalogo precargado 2500 productos**: deliverable propio. Prompt Claude de generacion → review manual → JSON seed → seeder Laravel → migracion que lo importa. Actualizacion mensual es operativa, no incluida en scope actual.
4. **Arquitectura de 3 capas con fallback**: orquestacion no trivial — debounce 2s, background dispatch, deduplicacion de resultados cross-layer, priorizacion (historial > catalogo > Claude). UI y backend deben sincronizarse para evitar flashes de resultados.
5. **Rate limiting con persistencia en DB**: nueva tabla `ai_usage_log` (contador por usuario por dia), reset medianoche Madrid, distincion Free (20/dia) vs Premium (sin limite). Budget global check antes de CADA llamada Claude.
6. **RGPD con API externa**: primer flujo que envia datos de compra fuera del sistema. Requiere anonimizacion (sin `user_id`, solo historial agregado) + consent en terms of service. Privacy implication que afecta a toda la empresa.
7. **UI nueva en dos sitios**: (a) autocompletado inline en AddItemInput (refactor componente Epic 3), (b) vista en perfil ver/limpiar historial (nueva seccion en ProfilePage).
8. **Query de recencia ponderada**: SQL no trivial — exponencial o lineal sobre `fecha_compra`, combinado con frecuencia. Afecta relevancia de todas las sugerencias.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | High | Primer uso Claude API: SDK, timeouts, error handling, circuit breaker. Cualquier bug silente en el rate limit o budget cap puede generar facturas inesperadas. SLA <50ms requiere FULLTEXT index + cache de catalogo + queries optimizadas. |
| Data | Medium | Nueva tabla `ai_usage_log`, nuevo indice FULLTEXT sobre `producto_historial`, nueva tabla `producto_catalogo` (2500 filas). Schema diseno afecta HU-503/504/505 (Epic 5B/5C). |
| Security | **High** | `CLAUDE_API_KEY` en env, nunca expuesta al frontend (ya documentado en stack.yaml `never_expose_to_frontend: true`). Anonimizacion pre-envio: nunca `user_id`, solo nombres de productos. Prompt injection: los nombres vienen del input del usuario, podrian contener instrucciones maliciosas para Claude. Requiere sanitizacion. Budget cap = defensa contra runaway, clave para seguridad financiera. |
| Performance | High | SLA <50ms para capas 1+2 es el objetivo explicito del HU. FULLTEXT index es la unica via viable a esa latencia. Cache del catalogo (2500 filas) debe estar siempre caliente — usar `Cache::rememberForever` en Laravel. Capa 3 es diferida, no afecta el SLA percibido pero debe ser asincrona (frontend sigue usable mientras espera). |
| Operational | Medium | Nueva dependencia externa (Claude API). Monitorizacion del budget cap mensual critica. Alerta por email al admin si se supera. Reset diario del contador personal via comando programado. Script de generacion del catalogo debe ser reproducible (ejecutable via `php artisan ai:seed-catalog`). |

## Affected Areas

- **database/migrations/** — 3 nuevas:
  - `create_producto_catalogo_table` (2500 productos espanoles)
  - `create_ai_usage_log_table` (rate limit per-user + global counter)
  - `add_fulltext_index_to_producto_historial` (ALTER TABLE add FULLTEXT)
- **database/seeders/** — New `ProductoCatalogoSeeder` importando JSON generado con Claude
- **storage/app/seeds/catalogo-productos.json** — Artefacto del catalogo (~2500 entradas). Commiteado al repo.
- **config/** — New `config/ai.php` con:
  - `provider`, `api_key`, `timeout`, `budget_cap_monthly_usd`, `admin_alert_email`
  - `rate_limits.free.suggestions_per_day: 20`
  - `thresholds` (reusables por Epic 5B/5C)
- **app/Support/Ai/** — New namespace:
  - `ClaudeClient` (wrapper del SDK con circuit breaker, timeout, error handling)
  - `BudgetCap` (check global mensual, alerta)
  - `PromptSanitizer` (sanitiza input del usuario antes de meterlo en prompt)
  - `AiUsageTracker` (contador per-user con reset Madrid)
- **app/Enums/** — New `AiOperation` (suggestion, generation, summary...), `AiPlan` (free, premium)
- **app/Services/** — New:
  - `ProductSuggestionService` (orquesta las 3 capas)
  - `ProductHistoryWeightingService` (query con ponderacion recencia)
  - `ProductHistoryCleanupService` (limpiar historial desde perfil)
- **app/Http/Controllers/** — New:
  - `ProductSuggestionController` (endpoint `/api/suggestions?q=...`)
  - Extender `ProfileController` con endpoints para ver/limpiar historial
- **app/Http/Requests/** — New FormRequests para suggestion query + history clear
- **app/Http/Middleware/** — (opcional) `CheckAiBudgetCap` para cortar llamadas Claude cuando cap global alcanzado
- **app/Console/Commands/** — New `ResetAiDailyUsage` programado medianoche Madrid
- **routes/api.php** — Nuevos endpoints `/api/suggestions`, `/api/profile/history`, `/api/profile/history (DELETE)`
- **resources/js/components/items/** — Refactor `AddItemInput` para integrar `ItemAutocomplete` (nuevo componente)
- **resources/js/components/items/ItemAutocomplete.jsx** — New componente con debounce 2s para capa 3
- **resources/js/pages/ProfilePage.jsx** — Nueva seccion "Mi historial de productos" con lista + boton limpiar
- **resources/js/lib/suggestionsApi.js** — Cliente API dedicado
- **tests/** — Full coverage: Unit tests para servicios, support, command; Feature tests para controladores, middleware, rate limit
- **.env.example** — Añadir `CLAUDE_API_KEY`, `AI_BUDGET_CAP_MONTHLY_USD`, `AI_ADMIN_ALERT_EMAIL`
- **composer.json** — Añadir `anthropic-ai/sdk` (o equivalente PHP oficial)

## Resolved Questions

1. **Catalogo precargado 2500 productos (HU-501 capa 2)**: Generado con Claude **en este sprint** como deliverable. Flujo: prompt Claude → review manual → `storage/app/seeds/catalogo-productos.json` → `ProductoCatalogoSeeder`. Actualizacion mensual es operativa, no incluida aqui.

2. **Claude API setup**:
   - Paquete: `anthropic/anthropic-sdk-php` (o el cliente PHP oficial vigente)
   - Env vars: `CLAUDE_API_KEY`, `AI_BUDGET_CAP_MONTHLY_USD`, `AI_ADMIN_ALERT_EMAIL`
   - Circuit breaker: SI
   - Timeout: 30s
   - **Budget cap global mensual** obligatorio en `config/ai.php`. Si se supera, corta todas las llamadas y envia email al admin. Proteccion anti-runaway.
   - Budget cap per-user diario via tabla `ai_usage_log`.

3. **HU-505 / Laravel Scheduler**: confirmado Laravel Scheduler (HU-505 esta en Epic 5C, fuera de scope aqui — nota retenida para contexto). En este feature: solo `ResetAiDailyUsage` command programado medianoche Madrid.

4. **Rate limit Claude Free 20/dia**:
   - Contador persistido en DB: tabla `ai_usage_log` (`user_id`, `date`, `operation`, `count`)
   - Reset: medianoche Madrid (Europe/Madrid). Command `php artisan ai:reset-daily-usage` programado a las 00:00 Madrid
   - Premium: sin limite de sugerencias (la quota de generaciones completas vive en Epic 6, fuera de scope)
   - No cache — DB persiste entre deploys

5. **Notificacion HU-505**: fuera de scope en Epic 5A. Mencion solo para contexto.

6. **RGPD — envio a Claude**:
   - **Anonimizacion total**: nunca `user_id` en el prompt, nunca email, nunca nombre del usuario. Solo nombres de productos del historial.
   - Consentimiento explicito en terms of service (actualizacion del texto): "Tus datos de compra se usan de forma anonima para generar sugerencias personalizadas."
   - `PromptSanitizer` remueve cualquier caracter que parezca instruccion (`ignore previous`, etc.) y trunca a N chars.

7. **HU-502 UI en perfil**: incluida en este scope. Nueva seccion en `ProfilePage`:
   - Lista de productos mas comprados (con cantidad, frecuencia)
   - Boton "Limpiar todo el historial" con confirmacion
   - Boton individual por producto para olvidarlo
   - Endpoint `GET /api/profile/history` y `DELETE /api/profile/history` (total) + `DELETE /api/profile/history/{producto}` (individual)
   - **No** se incluye FEAT-SETTINGS, queda fuera de scope.

8. **Full-text index sobre `producto_historial`**: SI. Migracion `ALTER TABLE producto_historial ADD FULLTEXT(producto_nombre)`. Necesario para cumplir SLA <20ms capa 1.

9. **Thresholds HU-503/504**: confirmados ajustables via `config/ai.php` (fuera de scope de Epic 5A, seran consumidos por Epic 5B):
   - `ai.thresholds.min_occurrences = 3`
   - `ai.thresholds.min_completed_lists = 5`
   - `ai.thresholds.co_occurrence_ratio = 0.60` (no 0.80, ajustado por feedback del producto)
   - Los valores no se consumen en Epic 5A pero se incluyen en `config/ai.php` para que Epic 5B los encuentre listos.

10. **Scope cut Epic 5 → Epic 5A/5B/5C**: confirmado. Epic 5A = HU-501 + HU-502.

## Open Questions

None. All resolved.

## Recommendation

- [ ] Proceed directly (LOW -> STEP 1b)
- [x] Require PRD (MEDIUM/HIGH -> STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)

## Scope cut decision

**Epic 5 splitted into:**
- `FEAT-EPIC5A-AUTOCOMPLETE` (this feature): HU-501 + HU-502 — autocompletado + aprendizaje + claude API foundation
- `FEAT-EPIC5B-REPLENISH` (future): HU-503 + HU-504 — alertas reposicion + complementarios
- `FEAT-EPIC5C-SUMMARY` (future): HU-505 — resumen semanal + scheduler + email

Rationale: Epic 5 monolitico era ~100-150h con 5 superficies distintas. Dividir reduce blast radius, permite rollback granular, y desacopla deliverables.

## Note on stray feature

`FEAT-EPIC5-AI` fue creado por error en esta sesion antes de decidir el split. Queda huerfano en el CLI state (sin trabajo hecho, sin documentos). No afecta a este feature.
