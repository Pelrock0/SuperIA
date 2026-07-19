# App Routes — Full API Reference

## Public Routes

| Method | URL | Handler | Middleware | Notes |
|--------|-----|---------|------------|-------|
| POST | `/api/waitlist` | WaitlistController@store | throttle:3,60 | Waitlist signup |
| GET | `/api/auth/invitation/{token}` | RegisterController@validateToken | — | Validate invitation token |
| POST | `/api/auth/register` | RegisterController@register | throttle:5,60 | User registration |
| GET | `/api/auth/verify-email/{id}/{hash}` | RegisterController@verifyEmail | — | Email verification (signed) |
| POST | `/api/auth/login` | LoginController@login | throttle:10,60 | Login → JWT |
| POST | `/api/auth/forgot-password` | PasswordResetController@forgotPassword | throttle:3,60 | Request reset email |
| POST | `/api/auth/reset-password` | PasswordResetController@resetPassword | throttle:5,60 | Complete reset |
| POST | `/api/auth/webauthn/authenticate/begin` | WebauthnController@beginAuthentication | EnsureWebauthnEnabled, throttle:20,1 | WebAuthn auth phase 1 |
| POST | `/api/auth/webauthn/authenticate/complete` | WebauthnController@completeAuthentication | EnsureWebauthnEnabled, throttle:20,1 | WebAuthn auth phase 2 |

## Shared List Routes (anonymous + authenticated)

| Method | URL | Handler | Middleware | Notes |
|--------|-----|---------|------------|-------|
| GET | `/api/shared/{tokenParam}` | SharedListController@show | ValidateShareToken, throttle:60,1 | View shared list |
| POST | `/api/shared/{tokenParam}/heartbeat` | SharedListController@heartbeat | ValidateShareToken, throttle:60,1 | Presence heartbeat |
| GET | `/api/shared/{tokenParam}/save-status` | SharedListController@saveStatus | ValidateShareToken, throttle:60,1 | Is list saved to account? |
| POST | `/api/shared/{tokenParam}/save` | SharedListController@saveToAccount | ValidateShareToken, throttle:60,1 | Save to authenticated account |
| POST | `/api/shared/{tokenParam}/items` | SharedListController@storeItem | ValidateShareToken:write, throttle:60,1 | Add item (write token only) |
| PUT | `/api/shared/{tokenParam}/items/{item}` | SharedListController@updateItem | ValidateShareToken:write, throttle:60,1 | Update item |
| PATCH | `/api/shared/{tokenParam}/items/{item}/toggle` | SharedListController@toggleItem | ValidateShareToken:write, throttle:60,1 | Toggle purchased |
| DELETE | `/api/shared/{tokenParam}/items/{item}` | SharedListController@destroyItem | ValidateShareToken:write, throttle:60,1 | Delete item |

## Authenticated Routes (auth:api + JwtVersionCheck)

### Profile

| Method | URL | Handler | Middleware | Notes |
|--------|-----|---------|------------|-------|
| POST | `/api/auth/logout` | LoginController@logout | auth:api, JwtVersionCheck | Invalidate token |
| POST | `/api/auth/refresh` | LoginController@refresh | auth:api, JwtVersionCheck, throttle:30,1 | Refresh JWT |
| GET | `/api/profile` | ProfileController@show | auth:api, JwtVersionCheck | Get profile |
| PUT | `/api/profile` | ProfileController@update | auth:api, JwtVersionCheck, throttle:10,1 | Update name |
| PUT | `/api/profile/password` | ProfileController@changePassword | auth:api, JwtVersionCheck, throttle:5,60 | Change password |
| GET | `/api/profile/history` | ProfileController@history | auth:api, JwtVersionCheck | Purchase history list |
| DELETE | `/api/profile/history` | ProfileController@clearHistory | auth:api, JwtVersionCheck | Clear all history |
| DELETE | `/api/profile/history/{producto}` | ProfileController@forgetProduct | auth:api, JwtVersionCheck | Forget specific product |
| GET | `/api/profile/my-data` | DataExportController@show | auth:api, JwtVersionCheck | GDPR data view |
| GET | `/api/profile/export` | DataExportController@export | auth:api, JwtVersionCheck | GDPR data export |
| POST | `/api/auth/delete-account` | AccountDeletionController@destroy | auth:api, JwtVersionCheck, throttle:3,60 | Delete account |

### WebAuthn (authenticated)

| Method | URL | Handler | Middleware | Notes |
|--------|-----|---------|------------|-------|
| POST | `/api/auth/webauthn/register/begin` | WebauthnController@beginRegistration | auth:api, JwtVersionCheck, EnsureWebauthnEnabled, throttle:20,1 | Register credential phase 1 |
| POST | `/api/auth/webauthn/register/complete` | WebauthnController@completeRegistration | auth:api, JwtVersionCheck, EnsureWebauthnEnabled, throttle:20,1 | Register credential phase 2 |
| GET | `/api/profile/webauthn-credentials` | WebauthnController@listCredentials | auth:api, JwtVersionCheck, EnsureWebauthnEnabled, throttle:60,1 | List credentials |
| PATCH | `/api/profile/webauthn-credentials/{id}` | WebauthnController@updateCredential | auth:api, JwtVersionCheck, EnsureWebauthnEnabled, throttle:20,1 | Rename credential |
| DELETE | `/api/profile/webauthn-credentials/{id}` | WebauthnController@deleteCredential | auth:api, JwtVersionCheck, EnsureWebauthnEnabled, throttle:20,1 | Revoke credential |

### Shopping Lists

| Method | URL | Handler | Notes |
|--------|-----|---------|-------|
| GET | `/api/lists` | ShoppingListController@index | All lists (active + archived + collaborated) |
| POST | `/api/lists` | ShoppingListController@store | Create (freemium: max 3 active) |
| GET | `/api/lists/{list}` | ShoppingListController@show | Get list (owner or collaborator) |
| PUT | `/api/lists/{list}` | ShoppingListController@update | Update |
| PATCH | `/api/lists/{list}/archive` | ShoppingListController@archive | Archive |
| PATCH | `/api/lists/{list}/restore` | ShoppingListController@restore | Restore (freemium re-check) |
| DELETE | `/api/lists/{list}` | ShoppingListController@destroy | Permanent delete |
| GET | `/api/lists/{list}/collaborators/count` | ShoppingListController@collaboratorsCount | Real-time presence count |
| GET | `/api/lists/{list}/collaborators` | ShoppingListController@collaborators | Collaborator list (owner only) |
| GET | `/api/lists/{list}/activity` | ShoppingListController@activityLog | Activity log (rolling 50) |

### List Items

| Method | URL | Handler | Notes |
|--------|-----|---------|-------|
| GET | `/api/lists/{list}/items` | ListItemController@index | Items grouped by category |
| POST | `/api/lists/{list}/items` | ListItemController@store | Add item (auto-categorize) |
| DELETE | `/api/lists/{list}/items/completed` | ListItemController@clearCompleted | Clear all purchased |
| PUT | `/api/lists/{list}/items/{item}` | ListItemController@update | Update item |
| PATCH | `/api/lists/{list}/items/{item}/toggle` | ListItemController@toggle | Toggle purchased |
| PATCH | `/api/lists/{list}/items/{item}/increment-quantity` | ListItemController@incrementQuantity | Increment quantity (duplicate action) |
| DELETE | `/api/lists/{list}/items/{item}` | ListItemController@destroy | Delete item |

### Share Tokens

| Method | URL | Handler | Notes |
|--------|-----|---------|-------|
| GET | `/api/lists/{list}/share` | ShareTokenController@index | Get active tokens |
| POST | `/api/lists/{list}/share` | ShareTokenController@store | Create token (throttle:10,60) |
| DELETE | `/api/lists/{list}/share/{token}` | ShareTokenController@destroy | Revoke token (cascades collaborators) |

### AI Features

| Method | URL | Handler | Notes |
|--------|-----|---------|-------|
| GET | `/api/suggestions` | ProductSuggestionController@index | Autocomplete (3-layer + optional AI) |
| GET | `/api/suggestions/complements` | ComplementController@index | Complementary suggestions |
| GET | `/api/dashboard/replenishment` | ReplenishmentController@index | Replenishment alerts (cached 5min) |
| POST | `/api/replenishment/accept` | ReplenishmentController@accept | Accept suggestion → add to list |
| POST | `/api/replenishment/ignore` | ReplenishmentController@ignore | Dismiss 24h |
| POST | `/api/replenishment/silence` | ReplenishmentController@silence | Silence permanently |
| POST | `/api/generate-list` | ListGenerationController@generate | Generate list from description |
| POST | `/api/generate-list/confirm-new` | ListGenerationController@confirmNew | Confirm → new list |
| POST | `/api/generate-list/confirm-existing` | ListGenerationController@confirmExisting | Confirm → add to existing |
| POST | `/api/lists/{list}/estimate-prices` | PriceEstimationController@estimate | Estimate prices (4-layer) |
| POST | `/api/lists/{list}/confirm-prices` | PriceEstimationController@confirmPrices | Record real prices |

### Weekly Summary

| Method | URL | Handler | Notes |
|--------|-----|---------|-------|
| GET | `/api/weekly-summary/latest` | WeeklySummaryController@latest | Current week's summary |
| POST | `/api/weekly-summary/dismiss` | WeeklySummaryController@dismiss | Dismiss in-app banner |
| POST | `/api/weekly-summary/{summary}/save` | WeeklySummaryController@save | Save selected recommendations to list (new or existing) |
| POST | `/api/settings/weekly-summary-email` | WeeklySummaryEmailController@update | Toggle email opt-in |

### History & Stats

| Method | URL | Handler | Notes |
|--------|-----|---------|-------|
| GET | `/api/history` | HistoryController@index | Paginated archived lists (20/page) |
| POST | `/api/lists/{list}/duplicate` | HistoryController@duplicate | Clone archived list |
| GET | `/api/stats` | StatsController@index | Monthly spend + top categories/products |

## Web Routes

| Method | URL | Handler | Middleware | Notes |
|--------|-----|---------|------------|-------|
| GET | `/unsubscribe/weekly-summary/{user}` | UnsubscribeWeeklySummaryController@handle | signed | 30-day signed URL unsubscribe |
| GET | `/{any}` | (SPA catch-all) | — | Serves React/Next.js SPA |
