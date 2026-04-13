# PRD: FEAT-EPIC5C-SUMMARY - Resumen semanal de reposición inteligente (HU-505)

## Business Objective

Cerrar Epic 5 (IA y aprendizaje) entregando HU-505: el primer canal proactivo del producto que recuerda al usuario "esto es lo que probablemente necesitas comprar esta semana" sin que tenga que abrir la app. Es la primera feature que combina **scheduler periódico + email saliente programado + Claude API** en una sola pieza, y por tanto sienta el patrón operacional para todas las features futuras de notificaciones programadas (Epic 7 precios, Epic 8 duplicados, etc.).

El valor está en la retención: usuarios con ≥3 semanas de historial son los activos consolidados; un nudge semanal personalizado los mantiene en hábito sin presión. La hipótesis (no validable en V1) es que reduce el churn de "olvidé hacer la compra" y aumenta la frecuencia de listas creadas por usuario activo.

Es también la primera vez que el sistema **envía emails de tipo no transaccional** — implica decisiones de RGPD (opt-in explícito por email, link de unsubscribe firmado), que pasan a ser plantilla para cualquier comunicación marketing-style futura.

## Problem Statement

- **Usuarios habituales pierden el hábito sin nudges**: hoy el sistema solo reacciona cuando el usuario abre la app. No hay ningún canal que diga "oye, esta semana suelen comprar X, ¿quieres?". Las features 5A/5B (autocompletado, reposición, complementos) requieren que el usuario ya esté creando una lista. HU-505 es el primer "outbound" del producto.
- **El historial de consumo está infrautilizado para predicción**: la tabla `producto_historial` lleva 3 epics alimentándose y solo se consume on-demand (autocompletado, banner de reposición). No hay ningún proceso periódico que mire el historial agregado y razone sobre patrones semanales/estacionales.
- **No existe pipeline de email programado**: los 6 mailables existentes (`WaitlistConfirmationMail`, `PasswordResetMail`, `AccountDeletionMail`, `InvitationMail`, `VerificationMail`, `BudgetCapExceededAlert`) son todos transaccionales — disparados por una acción del usuario o un evento del sistema. No hay precedente de "envía email a N usuarios el lunes a las 8:00". Esta feature crea ese patrón.
- **Estacionalidad de productos sin aprovechar**: el mes del año (octubre = setas, diciembre = turrón, julio = sandía) es señal gratis que el sistema no usa hoy. Claude puede inferirlo del prompt sin coste adicional ni mantenimiento de listas hardcoded.

## Scope

### In Scope

#### Backend
- **Migración**: nueva tabla `weekly_summaries` con columnas `id`, `user_id`, `week_start_date` (DATE, lunes de la semana ISO), `status` (enum: pending/dispatched/failed), `payload_json` (longText, contenido renderizado del resumen), `claude_cost_usd` (decimal), `dispatched_at` (timestamp nullable), `error_message` (text nullable), `created_at`, `updated_at`. **Unique constraint** sobre `(user_id, week_start_date)` — fuente de verdad para idempotencia.
- **Migración**: nueva columna `users.weekly_summary_email_opted_in` BOOLEAN default `false` (decisión #3: email es opt-in explícito). La opción in-app no necesita columna porque es default-on para todos los usuarios elegibles.
- **Migración**: nueva columna `users.weekly_summary_in_app_dismissed_at` TIMESTAMP nullable, para que el banner persistente sea dismissable sin re-aparición hasta la próxima semana.
- **Modelo**: `App\Models\WeeklySummary` con relación `belongsTo(User)`, fillable, casts para `status` (enum), `payload_json` (array), `dispatched_at` (datetime).
- **Enum**: `App\Enums\WeeklySummaryStatus` (Pending, Dispatched, Failed).
- **Enum extension**: `App\Enums\AiOperation::WeeklySummary` añadido (decisión #6 — quota compartida con resto de operaciones AI).
- **Service**: `App\Services\WeeklySummaryService` con métodos:
  - `eligibleUsers(): Collection<User>` — query construida con scope hooks (excluye soft-deleted, no verificados, `last_login_at < now-60d`, < 3 semanas de historial).
  - `generateForUser(User $user): WeeklySummary` — orquesta budget check, sanitización, llamada a Claude, persistencia (con dedup por unique key), record de uso.
  - `dispatchEmailFor(WeeklySummary $summary): void` — envía mailable si el usuario tiene `weekly_summary_email_opted_in = true`. Si no, el resumen queda solo in-app.
  - `markDismissed(User $user): void` — actualiza `weekly_summary_in_app_dismissed_at`.
  - `convertToList(User $user, WeeklySummary $summary): ShoppingList` — crea una `ShoppingList` con items del payload, respetando límite freemium (lanza `OverflowException` si ya tiene 3 listas activas).
- **Console command**: `App\Console\Commands\DispatchWeeklySummary` registrado como `ai:dispatch-weekly-summary`. Itera `eligibleUsers()`, llama `generateForUser` + `dispatchEmailFor` con per-user try/catch (decisión #11 — failure isolation, continue on error). Loguea métricas por run: `processed`, `succeeded`, `email_sent`, `failed`, `total_cost_usd`. Respeta el kill switch `config('ai.weekly_summary.enabled')` (decisión #13).
- **Scheduler**: nueva entrada en `routes/console.php`: `Schedule::command('ai:dispatch-weekly-summary')->mondays()->at('08:00')->timezone('Europe/Madrid')->withoutOverlapping(60)`.
- **Mailable**: `App\Mail\WeeklySummaryMail` con template Blade `resources/views/emails/weekly-summary.blade.php` (Spanish, decisión #8). Incluye: saludo, lista de productos sugeridos, link a "convertir en lista" (deep-link al frontend), link de unsubscribe firmado (`URL::signedRoute('weekly-summary.unsubscribe', $user, now()->addDays(30))`).
- **AI client extension**: nuevo método en la interfaz:
  ```php
  public function generateWeeklySummary(array $context): array;
  // returns: ['products' => [...], 'estimated_cost_usd' => float]
  ```
  Implementación en `ClaudeClient` (real) y `FakeClaudeClient` (test). El context incluye: 4 semanas de historial anonimizado, lista activa actual si existe, mes del año (entero 1-12).
- **Config**: nueva sección en `config/ai.php`:
  ```php
  'weekly_summary' => [
      'enabled' => env('AI_WEEKLY_SUMMARY_ENABLED', true),
      'model' => env('AI_WEEKLY_SUMMARY_MODEL', 'claude-haiku-4-5-20251001'),
      'max_tokens' => 1500,
      'history_weeks' => 4,
      'min_history_weeks' => 3,
      'inactivity_cutoff_days' => 60,
      'unsubscribe_token_ttl_days' => 30,
  ],
  ```
- **Endpoints**:
  - `GET /api/weekly-summary/latest` (auth) — devuelve el resumen más reciente del user (esta semana) o 404 si no hay.
  - `POST /api/weekly-summary/dismiss` (auth) — marca el banner como dismissed para esta semana.
  - `POST /api/weekly-summary/{id}/convert-to-list` (auth) — convierte a lista. Devuelve la nueva lista o 403 con `FREEMIUM_LIMIT` si ya hay 3 activas (decisión #5).
  - `POST /api/settings/weekly-summary-email` (auth) — toggle del opt-in de email (`{ "enabled": true|false }`).
  - `GET /unsubscribe/weekly-summary/{user}` (PUBLIC, signed URL middleware) — flip `weekly_summary_email_opted_in = false`, vista HTML simple "Te has dado de baja". Idempotente.
- **FormRequests**: validación para los 4 endpoints autenticados.
- **Routes**: añadir las 4 routes API + 1 web (unsubscribe).

#### Frontend
- **Page**: `resources/js/pages/WeeklySummaryPage.{tsx}` en ruta `/app/resumen` — fetch del último resumen, render del payload, botón "Convertir en lista", manejo de error si no hay resumen esta semana, respeto del estado dismissed. Diseño fetched via MCP del Stitch screen `resumen_semanal` (decisión #12).
- **Component**: `WeeklySummaryBanner` en dashboard — visible si hay un resumen esta semana y no está dismissed; click → navega a `/app/resumen`; botón X → llama a `dismiss` endpoint.
- **Component**: toggle "Recibir resumen semanal por email" en la página de Settings (existente o nueva).
- **API client**: `resources/js/api/weeklySummary.ts` con los 4 endpoints autenticados.
- **Tests**: vitest para los 3 componentes (banner, page, settings toggle) + el api client.

#### Tests (backend)
- Unit: `WeeklySummaryServiceTest` — eligibility filter (incluye soft-deleted, no verificados, inactivos, <3 semanas), generate happy path, generate respecta unique constraint (segunda llamada en la misma semana → no duplica), generate respeta budget cap, generate respeta kill switch, dispatchEmailFor envía solo si opted-in, markDismissed, convertToList happy + freemium overflow.
- Feature: `DispatchWeeklySummaryCommandTest` — happy path con N usuarios (mock Claude + Mail::fake), per-user error isolation, kill switch a false → 0 dispatches, withoutOverlapping (smoke test).
- Feature: tests de los 4 endpoints + el unsubscribe public.
- Feature: signed URL test (válido, expirado, manipulado, replay → no idempotency issues porque la operación es idempotente por naturaleza).
- Factory + seeder helper: `producto_historial` con 3+ semanas para los tests.

### Out of Scope

- **Bounce handling y suppression list**: V1 confía en SMTP estándar. Si un email rebota, no hay procesamiento automático del bounce ni suppression list. (Decisión #1 — bounce handling fuera de scope V1.)
- **Mail provider que no sea SMTP genérico**: no se evalúa AWS SES, Resend, Postmark, Mailgun, ni APIs propietarias. Solo `MAIL_MAILER=smtp` con credenciales en env. (Decisión #1.)
- **In-app notifications via push browser, service workers, websockets**: la notificación in-app es exclusivamente un banner persistente en el dashboard. (Decisión #2.)
- **i18n**: el email y los componentes son Spanish-only. No se implementa Laravel Localization ni archivos `lang/`. (Decisión #8.)
- **Cuota AI separada para weekly summary**: usa el pool compartido `AiUsageTracker`. Si el usuario ya gastó su cuota en autocompletado, no recibe resumen esta semana. (Decisión #6.)
- **Cap de coste por run del cron**: solo aplica el `BudgetCap` global existente. (Decisión #14.)
- **Token de unsubscribe single-use o stateful**: se usa `URL::signedRoute` con TTL 30 días, stateless. (Decisión #10.)
- **Lista hardcoded de productos estacionales por mes**: Claude infiere estacionalidad a partir del entero `month` en el prompt. (Decisión #9.)
- **Retroactive backfill**: usuarios existentes no reciben resúmenes de semanas pasadas. La feature arranca prospectivamente desde el primer lunes tras el deploy.
- **Métricas de retención / dashboards de éxito**: validar la hipótesis ("aumenta retención") es out of scope. Solo se entregan logs estructurados por run; un dashboard se construirá en una feature futura si se decide instrumentar.
- **Notificación al admin si el run del cron falla globalmente**: out of scope V1. Solo se loguea localmente.
- **Editar el resumen antes de convertirlo en lista**: la conversión es 1:1 — todos los productos del payload se añaden a la nueva lista sin selección granular. La edición posterior se hace en la lista creada, con la UX existente.
- **Permitir múltiples conversiones del mismo resumen**: convertToList no es idempotente al lado del usuario — si llama dos veces, crea dos listas (cada una restada de su cuota freemium). El frontend deshabilita el botón tras el primer click.
- **Configurar día/hora del envío por usuario**: el cron es global (lunes 08:00 Europa/Madrid). Personalización del horario es feature futura.
- **Resumen para usuarios sin historial suficiente con prompt fallback**: si <3 semanas, el usuario simplemente no recibe nada. No hay "resumen genérico para nuevos usuarios".
- **Backpack admin panel para inspeccionar resúmenes generados**: out of scope V1.

## Acceptance Criteria

### AC-1: Cron dispatches summary on Mondays at 08:00 Europe/Madrid

- **Given**: el scheduler está activo y `config('ai.weekly_summary.enabled') = true`
- **When**: llega el lunes a las 08:00 en zona Europa/Madrid
- **Then**: Laravel Scheduler ejecuta `ai:dispatch-weekly-summary` exactamente una vez (gracias a `withoutOverlapping(60)`); el comando itera sobre los usuarios elegibles y procesa cada uno individualmente.

### AC-2: Eligibility filter excludes ineligible users

- **Given**: la base de datos contiene usuarios en distintos estados (soft-deleted, sin email_verified_at, last_login_at hace 90 días, con 2 semanas de historial, con 4 semanas de historial y activos)
- **When**: `WeeklySummaryService::eligibleUsers()` se ejecuta
- **Then**: solo se incluyen usuarios que cumplen TODOS estos criterios: no soft-deleted, `email_verified_at IS NOT NULL`, `last_login_at >= now()-60 days`, ≥3 semanas distintas con al menos 1 row en `producto_historial`.

### AC-3: Idempotency via unique constraint on (user_id, week_start_date)

- **Given**: el comando `ai:dispatch-weekly-summary` ya fue ejecutado y persistió un `WeeklySummary` para `(user=42, week_start=2026-04-13)` con status `dispatched`
- **When**: el comando se ejecuta de nuevo en la misma semana (e.g. retry tras outage)
- **Then**: el segundo intento NO crea una segunda fila; el insert es bloqueado por la unique constraint; el comando captura la excepción y la loguea como "skipped (already dispatched)" sin propagar el error; el usuario recibe exactamente UN email.

### AC-4: Failure isolation per user

- **Given**: el comando procesa 100 usuarios elegibles y Claude devuelve un error para el usuario #37
- **When**: el comando termina su run
- **Then**: los 99 usuarios restantes son procesados con éxito; el usuario #37 queda con un row `WeeklySummary` con `status=failed` y `error_message` poblado; el comando registra `processed=100, succeeded=99, failed=1` en logs; el exit code del comando es 0 (no aborta).

### AC-5: Email sent only if user opted in

- **Given**: el usuario A tiene `weekly_summary_email_opted_in = true`, el usuario B tiene `weekly_summary_email_opted_in = false`, ambos elegibles
- **When**: el comando ejecuta y genera ambos resúmenes
- **Then**: el usuario A recibe un email vía SMTP; el usuario B NO recibe email (su resumen queda accesible solo via in-app banner y `/app/resumen`); ambos `WeeklySummary` rows tienen `status=dispatched`.

### AC-6: In-app banner appears for users with current-week summary

- **Given**: el usuario tiene un `WeeklySummary` con `week_start_date = lunes de esta semana` y `weekly_summary_in_app_dismissed_at IS NULL`
- **When**: el frontend hace `GET /api/weekly-summary/latest`
- **Then**: la respuesta devuelve el resumen con HTTP 200 y el componente `WeeklySummaryBanner` se renderiza en el dashboard.

### AC-7: Banner can be dismissed and stays dismissed for the week

- **Given**: el usuario ve el banner del resumen de la semana en curso
- **When**: el usuario hace click en el botón X del banner
- **Then**: el frontend llama `POST /api/weekly-summary/dismiss`, el campo `users.weekly_summary_in_app_dismissed_at` se actualiza a `now()`; tras refrescar el dashboard el banner NO reaparece esta semana; el lunes siguiente, cuando se genere un nuevo resumen, el campo se ignora (porque el resumen es de otra semana).

### AC-8: Convert to list creates a new list with summary items

- **Given**: el usuario tiene un `WeeklySummary` con 5 productos en `payload_json` y solo 1 lista activa (bajo el límite freemium de 3)
- **When**: el usuario llama `POST /api/weekly-summary/{id}/convert-to-list`
- **Then**: se crea una nueva `ShoppingList` con nombre por defecto (e.g. "Resumen semanal del DD/MM"), todos los 5 productos como items; la respuesta devuelve la nueva lista con HTTP 201; el usuario ahora tiene 2 listas activas.

### AC-9: Convert to list respects freemium limit

- **Given**: el usuario ya tiene 3 listas activas (en el límite freemium) y un `WeeklySummary` válido
- **When**: el usuario llama `POST /api/weekly-summary/{id}/convert-to-list`
- **Then**: la respuesta es HTTP 403 con `{ "error": { "code": "FREEMIUM_LIMIT", "message": "..." } }`; no se crea ninguna lista nueva; el resumen original queda intacto.

### AC-10: Email opt-in toggle persists user preference

- **Given**: el usuario A tiene `weekly_summary_email_opted_in = false` (default)
- **When**: el usuario llama `POST /api/settings/weekly-summary-email` con `{ "enabled": true }`
- **Then**: la columna se actualiza a `true`; en el siguiente run del cron, el usuario A recibe email.

### AC-11: Unsubscribe link disables email opt-in without auth

- **Given**: el usuario recibió un email con un link `https://app/unsubscribe/weekly-summary/{user}?signature=...`
- **When**: el usuario hace click en el link sin estar logueado
- **Then**: la signed URL es validada por el middleware `signed`; `weekly_summary_email_opted_in` se actualiza a `false`; se renderiza una vista HTML simple "Te has dado de baja del resumen semanal"; un click subsiguiente al mismo link es idempotente (no error).

### AC-12: Unsubscribe link expires after 30 days

- **Given**: un email enviado con unsubscribe link generado con TTL 30 días (`now()->addDays(30)`)
- **When**: el usuario hace click 31 días después
- **Then**: la signed URL es rechazada por el middleware `signed` con HTTP 403; se muestra un mensaje "Este enlace ha caducado" o equivalente.

### AC-13: Tampered unsubscribe link is rejected

- **Given**: un atacante modifica el `{user}` segment del path o el query `signature`
- **When**: el atacante visita la URL manipulada
- **Then**: el middleware `signed` rechaza la request con HTTP 403; ninguna columna se modifica.

### AC-14: Kill switch prevents any dispatch

- **Given**: `AI_WEEKLY_SUMMARY_ENABLED=false` en `.env`
- **When**: el comando `ai:dispatch-weekly-summary` se ejecuta (manual o por scheduler)
- **Then**: el comando detecta el flag, loguea "weekly summary disabled by config", termina con exit 0 sin crear ningún `WeeklySummary` row ni enviar ningún email.

### AC-15: AiUsageTracker shared budget blocks summary if quota spent

- **Given**: el usuario A ya gastó su cuota diaria de `AiUsageTracker` con autocompletado
- **When**: el comando intenta generar un resumen para el usuario A
- **Then**: `AiUsageTracker::canUse(A, AiOperation::WeeklySummary)` devuelve false; no se llama a Claude; el `WeeklySummary` row se crea con `status=failed` y `error_message="user quota exceeded"`; los demás usuarios no se ven afectados.

### AC-16: Claude prompt includes 4 weeks of history, current list, and month

- **Given**: un usuario elegible con historial e idealmente una lista activa
- **When**: `WeeklySummaryService::generateForUser` invoca a Claude
- **Then**: el `context` enviado al `ClaudeClientInterface::generateWeeklySummary` contiene: (a) historial anonimizado de las últimas 4 semanas vía `HistoryAnonymizer`, (b) los items de la lista activa actual si existe (sino array vacío), (c) `month` como entero 1-12. El user query/freeform input es **NULL** — esto es una operación batch, no responde a un input en tiempo real. `PromptSanitizer` se aplica a los nombres de productos del historial antes de enviarlos.

### AC-17: 100% backend test coverage on new code

- **Given**: la suite de tests del proyecto
- **When**: se ejecuta `php artisan test` tras la implementación
- **Then**: cada nuevo método de `WeeklySummaryService`, el comando, el mailable, los 4 endpoints autenticados y el unsubscribe público tienen al menos un happy path test, un failure path test (donde aplique) y un edge case test (donde aplique). Cobertura de líneas y branches al 100% sobre el código nuevo. La suite total pasa al 100% (sin regresiones a los 479 tests preexistentes).

### AC-18: Frontend tests for the new components

- **Given**: la suite de tests vitest
- **When**: se ejecuta `npm test`
- **Then**: hay tests para `WeeklySummaryBanner` (renderiza si hay summary, no renderiza si está dismissed, dispara `dismiss` al click X), `WeeklySummaryPage` (renderiza payload, botón convert habilitado/deshabilitado tras click), settings toggle (toggle persiste optimisticamente, revierte si falla). La suite total pasa sin regresiones a los 208 tests preexistentes.

### AC-19: Stitch screen consumed via MCP before implementing the page

- **Given**: el Stitch project "Superia" contiene la pantalla `resumen_semanal`
- **When**: el frontend de S4 va a generar `WeeklySummaryPage`
- **Then**: el desarrollador (o agente) consulta el screen via MCP (`mcp__stitch__get_screen`) antes de escribir cualquier JSX, y el resultado se referencia en `04-implementation-notes.md` como input del componente.

### AC-20: Scheduler entry uses withoutOverlapping to prevent concurrent runs

- **Given**: el run de las 08:00 del lunes está en curso y dura más de 1 hora (caso degenerado)
- **When**: por algún motivo el scheduler dispara un segundo intento (no debería con `weeklyOn`, pero contemplar el caso)
- **Then**: `withoutOverlapping(60)` asegura que el segundo intento no arranca; se loguea el skip; el primer run termina normalmente.

## UX Decision

- **UX Designer Required**: **NO** (con condición — ver nota)
- **UX Artifacts**: Stitch project "Superia" → screen `resumen_semanal`. Será fetched via MCP en S4 (AC-19). No se genera `ux-wireframes.html` separado.
- **Basic UX Notes**:
  - Banner persistente en dashboard, dismissable con X. Color/estilo coherente con `ReplenishmentBanner` existente (Epic 5B).
  - Página `/app/resumen`: lista vertical de productos sugeridos (mismo patrón visual que las listas de compra existentes), CTA grande "Convertir en lista" arriba, link discreto "Volver" abajo.
  - Settings: nuevo toggle "Recibir resumen semanal por email" en la sección de notificaciones (o equivalente — ver Stitch).
  - Email: template Spanish, plain HTML simple, header con logo Superia, lista de productos, dos CTAs (ver en app + convertir en lista deep link), footer con link de unsubscribe.
- **⚠️ Aviso a S5-UX**: aunque no requiero UX Designer ahora (porque Stitch ya tiene el diseño), **S5-UX SÍ se ejecutará** porque la feature introduce nuevos componentes visibles al usuario (banner + page + settings toggle + email). El reviewer S5-UX validará que la implementación coincide con el diseño Stitch fetched via MCP y que el email se renderiza correctamente.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| SMTP provider rate limit golpeado en run masivo (e.g. Mailtrap free tier es 100/mes) | Operational | V1 asume que el provider de prod admite el volumen esperado. Para el primer deploy, monitorizar el log del cron y, si hay > N usuarios elegibles, validar con el admin de prod antes del primer run. Documented limit: cap soft a 1000 usuarios/run en V1; si se supera, se programará una feature de chunking. |
| Claude outage durante el run del cron deja muchos usuarios sin resumen | Operational | Failure isolation por usuario (AC-4) garantiza que el run continúa. Los usuarios fallidos quedan con `status=failed`; un re-run manual del comando los puede reintentar (la unique constraint los protege de duplicación si entremedias el primer intento se completó). |
| Coste de Claude por run inesperadamente alto | Performance | Modelo por defecto `claude-haiku-4-5` (más barato que sonnet/opus). `BudgetCap` global existente bloquea si se llega al cap. Estimación V1: ~$0.005/usuario × 1000 usuarios = $5/run × 52 weeks = $260/año. |
| Prompt injection vía nombres de productos en el historial | Security | `PromptSanitizer` se aplica a cada nombre antes de meterlo en el contexto (AC-16). El prompt usa delimitadores estructurados; los nombres de producto van como elementos de array JSON, no concatenados a la system prompt. |
| Email enumeration via `/api/weekly-summary/latest` (un atacante podría inferir si un user ID existe) | Security | El endpoint es authenticated; solo devuelve el resumen del user autenticado. No acepta `user_id` query param. Devuelve 404 si el user autenticado no tiene resumen — no leak de existencia de otros users. |
| Unsubscribe link interceptado y replayed | Security | TTL 30 días limita la ventana. La operación es idempotente (toggle a false); replay solo confirma el opt-out. Si el user re-opta-in vía settings, el link viejo no puede revertirlo (el link siempre desactiva, nunca activa). Riesgo aceptado. |
| Race condition entre cron y opt-out: usuario opt-out el lunes 07:59, cron arranca 08:00 con una snapshot vieja | Operational | El check de `weekly_summary_email_opted_in` se hace al momento del `dispatchEmailFor` (no al `eligibleUsers`), reduciendo la ventana a microsegundos. Aceptado: en el peor caso un usuario recibe un último email no deseado tras opt-out. |
| `ShoppingList` con freemium limit causa errores en la conversión | Technical | AC-9 documenta el comportamiento (HTTP 403 FREEMIUM_LIMIT). El frontend muestra un mensaje claro y ofrece archivar una lista existente para hacer espacio. |
| El kill switch en config no se respeta porque está cacheado | Operational | `config:cache` en prod cachea el .env. Documentar en README que cambiar `AI_WEEKLY_SUMMARY_ENABLED` requiere `php artisan config:clear` o redeploy. |
| Test fixtures con historial de 3 semanas son lentos de crear | Technical | Factory helper específico que inserta 3 rows directamente en `producto_historial` con timestamps backdateados, en vez de simular el flujo completo. Documented en AC-17. |
| Dispatch a las 08:00 lunes coincide con pico de carga matutina del servidor | Performance | `withoutOverlapping(60)` previene runs concurrentes. Si el run dura >5 min, considerar mover a un horario más tranquilo en V2. V1 acepta el riesgo. |
| Claude infiere estacionalidad incorrecta (ej. "diciembre = sandía" por confusión hemisferio sur) | Quality | El prompt incluye explícitamente "España" como contexto geográfico. Si el modelo falla, los productos sugeridos serán ignorados por el usuario — no es un fallo crítico. Aceptado por decisión #9. |
| El banner persistente molesta a usuarios que no quieren la feature pero aún no saben cómo apagarla | Quality | El botón X dismissea por la semana entera (AC-7). El opt-out completo está en settings. Documentar en el primer email cómo darse de baja. |
| Migración añade columnas a `users` table en producción con N filas grandes | Operational | Las dos columnas nuevas (`weekly_summary_email_opted_in`, `weekly_summary_in_app_dismissed_at`) son nullable / con default — no requieren backfill. ALTER TABLE en MySQL 8 con `ALGORITHM=INSTANT` debería ser instantáneo. Documentar en la migración. |

## Assumptions

- **Producción de email funciona vía SMTP genérico**: el devops ya configurará `MAIL_HOST/PORT/USERNAME/PASSWORD` en el `.env` de producción antes del deploy. Esta feature NO toca la configuración de mail (solo asume que está).
- **El timezone "Europe/Madrid" está disponible** en el servidor de producción (lo está, Laravel lo bundle).
- **`HistoryAnonymizer` y `PromptSanitizer` siguen siendo apropiados** para esta nueva operación AI. Si se descubre durante S4 que el contexto requerido por el resumen necesita PII no anonimizable, se renegociará el scope.
- **`config:cache` no está activo en dev**: el kill switch funciona inmediatamente en dev sin clear cache. En prod sí requiere clear (documentado en risks).
- **El frontend usa el mismo patrón de estado (Zustand) y el mismo cliente HTTP** que las features existentes de Epic 5B.
- **El Stitch screen `resumen_semanal` es accesible via MCP** con las credenciales del proyecto. Si el MCP server no responde durante S4, se escala como blocker.
- **Los usuarios elegibles cabrán en una sola transacción/loop sin paginar**: V1 asume <10k usuarios elegibles. Si se supera, se programa chunking en V2.
- **`mondays()` de Laravel Scheduler usa el lunes ISO** (que es el comportamiento estándar).
- **`week_start_date` siempre se calcula como `Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString()`** en zona Europa/Madrid, no UTC.

## Open Questions

Ninguna. Las 15 open questions de S1 fueron resueltas por el usuario; las decisiones están documentadas en `01-scope.md` § "Resolved Decisions (S1, 2026-04-12)" y son inputs vinculantes a este PRD.

## Approval

- [ ] PRD approved by [user] on [date]

## Transition

- Gate Status: S2 PENDING
- Next Step: STEP 3 — Technical Design
- Required Artifacts for Next Step: `01-scope.md`, `02-prd.md`
