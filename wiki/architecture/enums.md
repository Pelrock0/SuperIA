# Architecture — Enums & Constants

## Backend Enums (PHP)

| Enum | Values | Used In |
|------|--------|---------|
| `ListStatus` | `active`, `archived` | `shopping_lists.status` |
| `ListCategory` | `Supermercado`, `Mercado`, `Online`, `Farmacia`, `Otro` | `shopping_lists.category` |
| `ShareTokenMode` | `read`, `write` | `list_share_tokens.mode`, `list_collaborators.mode` |
| `ProductCategory` | `Frutas y verduras`, `Carnes y pescados`, `Lácteos y huevos`, `Panadería`, `Bebidas`, `Congelados`, `Limpieza y hogar`, `Higiene y salud`, `Mascotas`, `Otros` | `list_items.category`, `producto_historial.categoria` |
| `ItemUnit` | `ud`, `kg`, `g`, `L`, `mL`, `pack`, `docena`, `bote`, `caja`, `bolsa`, `botella`, `lata`, `sobre` | `list_items.unit`, `producto_historial.unidad` |
| `ActorType` | `owner`, `anonymous` | `list_activity_log.actor_type` |
| `ActivityAction` | `item_added`, `item_updated`, `item_deleted`, `item_toggled`, `quantity_incremented` | `list_activity_log.action` |
| `AiOperation` | `suggestion`, `complement`, `replenishment`, `summary`, `generation`, `price_estimation`, `category_inference` | `ai_usage_log.operation` |
| `AiUsageStatus` | `success`, `failed`, `quota_exceeded`, `budget_exceeded`, `circuit_open` | `ai_usage_log.status` |
| `WaitlistStatus` | `pending`, `invited`, `registered` | `waitlist_entries.status` |
| `WeeklySummaryStatus` | `pending`, `generated`, `dispatched`, `failed` | `weekly_summaries.status` |

## Key Constants / Config Values

| Constant | Default | Where |
|----------|---------|-------|
| Freemium active list limit | 3 | `ShoppingListService` |
| JWT access TTL | 15 min | `AuthService` |
| JWT refresh TTL (remember me) | 30 days | `AuthService` |
| Login lockout attempts | 5 | `AuthService` |
| Login lockout duration | 15 min | `AuthService` |
| Share token rate limit | 10/hour per owner | Route middleware |
| Anonymous access rate limit | 60/min per IP | Route middleware |
| AI daily quota (free) | 20/day | `config('ai.daily_quota')` |
| AI monthly budget cap | $50 USD | `config('ai.monthly_budget_cap')` |
| Circuit breaker threshold | 3 failures | `CircuitBreaker` |
| Circuit breaker cooldown | 60s | `CircuitBreaker` |
| Price cache TTL | 30 days | `PriceEstimationService` |
| Replenishment cache TTL | 5 min | `ReplenishmentSuggestionService` |
| Replenishment factor | 0.8 | `ReplenishmentSuggestionService` |
| Co-occurrence threshold | 60% | `ComplementarySuggestionService` |
| Weekly summary opt-in default | false (email) / true (in-app) | `User` model default |
| Activity log rolling limit | 50 per list | `ActivityLogService` |
| Heartbeat session TTL | 5s | Presence query threshold |
| Invitation token expiry | 7 days | `WaitlistService` |
| Account hard-delete delay | 30 days | `AccountDeletionService` |
| RGPD anonymous data retention | 30 days | Cleanup command |
| Duplicate detection threshold | 80% | Frontend `similarText()` |
| Generation per-operation quota | 5/day (free) | `ListGenerationService` |
