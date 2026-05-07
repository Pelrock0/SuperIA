# Architecture — Service Map

All service classes in `app/Services/`.

| Service | Class | Purpose |
|---------|-------|---------|
| Auth | `AuthService` | Login, lockout, logout, JWT refresh |
| Registration | `RegistrationService` | Invitation consumption, user creation, email verification |
| Account deletion | `AccountDeletionService` | Soft-delete, scheduled hard-delete, audit log |
| Shopping lists | `ShoppingListService` | CRUD, freemium gate, archive/restore, collaborator lists |
| List items | `ListItemService` | Item CRUD, purchase toggle, counter sync, activity logging |
| List collaborators | `ListCollaboratorService` | Link/unlink collaborators, retroactive linking, access check |
| List history | `ListHistoryService` | Pagination of archived lists, list duplication |
| Stats | `StatsService` | Monthly spend, top categories, top products aggregations |
| Share token | `ShareTokenService` | Create, resolve, revoke share tokens (HMAC signing) |
| Waitlist | `WaitlistService` | Registration, position calculation, invitation generation |
| Admin metrics | `AdminMetricsService` | Dashboard aggregate queries (5 metrics) |
| Activity log | `ActivityLogService` | Rolling-50 activity log entries per list |
| Collaborator presence | `CollaboratorPresenceService` | Heartbeat upsert, active count query |
| Product suggestions | `ProductSuggestionService` | 3-layer search (history+list+catalog), AI fallback |
| Complementary suggestions | `ComplementarySuggestionService` | Co-occurrence query, AI fallback |
| Replenishment suggestions | `ReplenishmentSuggestionService` | Frequency algorithm, cache, silence/ignore |
| Price estimation | `PriceEstimationService` | 4-layer pipeline (history, catalog, fuzzy, cache+Claude) |
| List generation | `ListGenerationService` | Claude-powered list from free-text description |
| Weekly summary | `WeeklySummaryService` | Eligibility, Claude generation, email dispatch, unsubscribe |
| Category inference | `CategoryInferenceService` | Catalog lookup + async AI fallback via job |
| Product history weighting | `ProductHistoryWeightingService` | Recency-weighted query over `producto_historial` |
| Product history stats | `ProductHistoryStatsService` | Purchase frequency and recency aggregations |
| Product history cleanup | `ProductHistoryCleanupService` | RGPD forget-product hard delete |
| WebAuthn | `WebauthnService` | Challenge generation, credential registration/verification |

## AI Support Layer (`app/Support/Ai/`)

| Class | Purpose |
|-------|---------|
| `ClaudeClient` | HTTP calls to Anthropic API; returns typed responses with token counts |
| `FakeClaudeClient` | Test double; captures prompt payload for assertion |
| `BudgetCap` | Global monthly spend cap (DB-backed, default $50 USD) |
| `AiUsageTracker` | Per-user daily quota (DB-backed, shared pool across all AI operations) |
| `CircuitBreaker` | 3 failures → 60s open (cache-backed) |
| `PromptSanitizer` | Strips injection patterns (13 regex), enforces char limit |
| `HistoryAnonymizer` | Returns `string[]` product names only; strips all user PII |

## Other Support (`app/Support/`)

| Class | Purpose |
|-------|---------|
| `ShareTokenSigner` | Pure value class: `sign()` and `verify()` via HMAC-SHA256 |
