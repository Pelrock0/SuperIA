# Tests — Coverage Map

**Total tests:** 878+ | **Backend:** ~608 | **Frontend:** ~280

## Feature Tests (`tests/Feature/`)

| File | Area | Module | Key Scenarios |
|------|------|--------|--------------|
| `Auth/LoginTest.php` | Auth | AuthService | Valid login, lockout (5 attempts), is_active gate, email verification gate, remember me TTL |
| `Auth/RegisterTest.php` | Auth | RegistrationService | Invitation consumption, duplicate prevention, email verification flow |
| `Auth/PasswordResetTest.php` | Auth | AuthService | Reset request, single-use token, jwt_version increment |
| `Auth/ProfileTest.php` | Auth | ProfileController | Name update, password change (requires current) |
| `Auth/ProfileHistoryTest.php` | Auth | ProductHistoryCleanupService | View history, forget product, clear all |
| `Auth/AccountDeletionTest.php` | Auth | AccountDeletionService | Soft delete, jwt_version increment, audit log, no PII |
| `ShoppingListControllerTest.php` | Lists | ShoppingListController | CRUD, freemium limit (race condition), archive/restore, IDOR prevention |
| `ListItemControllerTest.php` | Items | ListItemController | Add/edit/delete, toggle purchased, clear completed, counter sync |
| `IncrementQuantityTest.php` | Items | ListItemController | Increment quantity endpoint, collaborator write access |
| `AutoCategorizationTest.php` | Items | CategoryInferenceService | Catalog lookup, null fallback, async job dispatch |
| `SharedListControllerTest.php` | Collab | SharedListController | Anonymous view, write mutations, read-only enforcement, token validation |
| `ShareTokenControllerTest.php` | Collab | ShareTokenService | Token creation, revocation, mode enforcement, rate limit |
| `CollaborationOwnerViewsTest.php` | Collab | ShoppingListController | Owner sees collaborators, activity log |
| `ListCollaboratorTest.php` | Collab | ListCollaboratorService | Save to account, retroactive linking, revocation cascade |
| `ProductSuggestionControllerTest.php` | AI | ProductSuggestionService | 3-layer search, AI gate, quota exceeded, cross-user isolation |
| `ComplementControllerTest.php` | AI | ComplementarySuggestionService | Co-occurrence, AI fallback, list exclusion |
| `ReplenishmentControllerTest.php` | AI | ReplenishmentSuggestionService | Dashboard alerts, accept/ignore/silence, cache invalidation |
| `ListGenerationControllerTest.php` | AI | ListGenerationService | Generate, preview, confirm-new (freemium), confirm-existing |
| `PriceEstimationControllerTest.php` | Prices | PriceEstimationService | 4-layer pipeline, confirm per-item, confirm total-only |
| `WeeklySummaryEndpointsTest.php` | Summary | WeeklySummaryController | Latest, dismiss, convert-to-list, email opt-in toggle |
| `HistoryControllerTest.php` | History | ListHistoryService | Pagination, price totals, duplicate (freemium check) |
| `StatsControllerTest.php` | Stats | StatsService | Monthly spend, top categories, top products, has_enough_data gate |
| `AdminDashboardTest.php` | Admin | AdminMetricsService | Dashboard metrics, admin-only access |
| `AdminDeactivateUserTest.php` | Admin | UserCrudController | Toggle is_active, deactivated user cannot login |
| `CheckIfAdminMiddlewareTest.php` | Admin | CheckIfAdmin | Role check, JSON 401, redirect for web |
| `WebauthnTest.php` | Auth | WebauthnService | Registration challenge/complete, auth challenge/complete, sign_count validation |
| `SecurityGatesIntegrationTest.php` | Ops | SecurityGates | Psalm subprocess, composer audit subprocess |
| `DispatchWeeklySummaryCommandTest.php` | Summary | WeeklySummaryService | Eligibility filter, per-user failure isolation, idempotency |
| `CleanupExpiredCollaboratorDataTest.php` | Collab | Cleanup | Stale sessions deleted, anonymous logs >30d deleted |
| `CleanupDismissedSuggestionsCommandTest.php` | AI | Cleanup | Expired dismissed suggestions deleted |
| `ResetAiDailyUsageCommandTest.php` | AI | AiUsageTracker | Daily quota reset |
| `SeedProductCatalogCommandTest.php` | Prices | PriceEstimationService | Catalog seeding idempotency |
| `ProductoCatalogoSeederTest.php` | Prices | Seeder | Seeder runs without errors |
| `Api/WaitlistControllerTest.php` | Landing | WaitlistController | Signup, rate limit, duplicate email silent, position obfuscation |

## Unit Tests (`tests/Unit/`)

### Services

| File | Module | Key Scenarios |
|------|--------|--------------|
| `Services/AuthServiceTest.php` | AuthService | Password check, lockout algorithm, jwt_version |
| `Services/RegistrationServiceTest.php` | RegistrationService | Token consumption, user creation, email queue |
| `Services/AccountDeletionServiceTest.php` | AccountDeletionService | Soft delete, hard delete scheduling, audit log hashing |
| `Services/ShoppingListServiceTest.php` | ShoppingListService | Freemium gate (concurrency), archive/restore, collaborated lists |
| `Services/ListItemServiceTest.php` | ListItemService | Counter sync, purchase history write, ShareTokenContext |
| `Services/ListCollaboratorServiceTest.php` | ListCollaboratorService | Link, retroactive, revocation, self-link prevention |
| `Services/ListHistoryServiceTest.php` | ListHistoryService | Pagination, price SUM, duplicate clone |
| `Services/StatsServiceTest.php` | StatsService | Monthly aggregates, top products, has_enough_data |
| `Services/ShareTokenServiceTest.php` | ShareTokenService | Create, resolve, revoke, HMAC integrity |
| `Services/WaitlistServiceTest.php` | WaitlistService | Register, position, invitation, duplicate |
| `Services/AdminMetricsServiceTest.php` | AdminMetricsService | 6 metrics accuracy |
| `Services/ActivityLogServiceTest.php` | ActivityLogService | Rolling-50 limit, anonymous vs owner actor |
| `Services/CollaboratorPresenceServiceTest.php` | CollaboratorPresenceService | Heartbeat upsert, stale session count |
| `Services/ProductSuggestionServiceTest.php` | ProductSuggestionService | Layer priority, dedup, AI gate, quota flow |
| `Services/ComplementarySuggestionServiceTest.php` | ComplementarySuggestionService | Co-occurrence ratio, AI fallback threshold |
| `Services/ReplenishmentSuggestionServiceTest.php` | ReplenishmentSuggestionService | Frequency algorithm, exclusions, cache |
| `Services/PriceEstimationServiceTest.php` | PriceEstimationService | 4-layer pipeline, unit conversion, Claude throttle |
| `Services/ListGenerationServiceTest.php` | ListGenerationService | Quota stack, silent retry, enum validation at insert |
| `Services/WeeklySummaryServiceTest.php` | WeeklySummaryService | Eligibility, idempotency, email opt-in re-read |
| `Services/CategoryInferenceServiceTest.php` | CategoryInferenceService | Catalog lookup, null case |
| `Services/ProductHistoryWeightingServiceTest.php` | ProductHistoryWeightingService | Recency weights, prefix matching |
| `Services/ProductHistoryStatsServiceTest.php` | ProductHistoryStatsService | Frequency calculation, recency |
| `Services/ProductHistoryCleanupServiceTest.php` | ProductHistoryCleanupService | Forget product (RGPD), cross-user isolation |

### AI Support

| File | Class | Key Scenarios |
|------|-------|--------------|
| `Support/Ai/ClaudeClientTest.php` | ClaudeClient | API call, token count extraction, error handling |
| `Support/Ai/BudgetCapTest.php` | BudgetCap | Monthly reset, race condition behavior |
| `Support/Ai/AiUsageTrackerTest.php` | AiUsageTracker | Daily limit, per-user override, cross-user isolation |
| `Support/Ai/CircuitBreakerTest.php` | CircuitBreaker | Failure threshold, cooldown, recovery |
| `Support/Ai/PromptSanitizerTest.php` | PromptSanitizer | 13 injection pattern tests, char limit |
| `Support/Ai/HistoryAnonymizerTest.php` | HistoryAnonymizer | No PII in output (asserts against serialized payload) |

### Other

| File | Class | Key Scenarios |
|------|-------|--------------|
| `Support/ShareTokenSignerTest.php` | ShareTokenSigner | Sign, verify, tamper detection, constant-time |
| `Middleware/JwtVersionCheckTest.php` | JwtVersionCheck | Version match, mismatch → 401, missing claim |
| `Middleware/ValidateShareTokenTest.php` | ValidateShareToken | Valid token, revoked, tampered, mode enforcement |
| `Models/WaitlistEntryTest.php` | WaitlistEntry | Status transitions, invitation fields |
| `Commands/DeleteExpiredAccountsCommandTest.php` | Command | Hard-delete scheduling, cascade |
| `Jobs/InferItemCategoryJobTest.php` | Job | Catalog lookup, null case, no double-dispatch |
