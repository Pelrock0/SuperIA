# Architecture — Model Map

All Eloquent models in `app/Models/`.

| Entity | Table | Key Fields | Key Relations | Notes |
|--------|-------|-----------|---------------|-------|
| `User` | `users` | id, email, password, jwt_version, is_active, plan, ai_daily_limit_override, scheduled_hard_delete_at, privacy_accepted_at, weekly_summary_email_opt_in | HasMany ShoppingList, HasMany WebauthnCredential, HasMany AiUsageLog, HasRoles (Spatie) | JWT subject; roles via Spatie; soft deletes |
| `ShoppingList` | `shopping_lists` | id, user_id, name, emoji, category (enum), status (enum), is_shared, items_total, items_completed | BelongsTo User, HasMany ListItem, HasMany ListShareToken | Status: active/archived |
| `ListItem` | `list_items` | id, shopping_list_id, name, quantity, unit (enum), category (enum), estimated_price, is_purchased, position | BelongsTo ShoppingList | Price estimation support; purchase state |
| `ListShareToken` | `list_share_tokens` | id, shopping_list_id, token_id (UUID), mode (enum), revoked_at | BelongsTo ShoppingList, HasMany ListCollaboratorSession | HMAC-signed URL token; revokable |
| `ListCollaborator` | `list_collaborators` | id, user_id, shopping_list_id, mode (enum), share_token_id | BelongsTo User, BelongsTo ShoppingList, BelongsTo ListShareToken | UNIQUE(user_id, shopping_list_id) |
| `ListCollaboratorSession` | `list_collaborator_sessions` | id, list_share_token_id, session_uuid, last_heartbeat_at | BelongsTo ListShareToken | Tab-scoped presence tracking |
| `ListActivityLog` | `list_activity_log` | id, shopping_list_id, list_share_token_id, actor_type (enum), action (enum), item_name | BelongsTo ShoppingList | Rolling-50 per list; anonymous + owner actions |
| `WebauthnCredential` | `webauthn_credentials` | id, user_id, credential_id (base64url), public_key, sign_count, transports, aaguid, attestation_type, name, last_used_at | BelongsTo User | Multiple credentials per user; sign_count for cloning detection |
| `ProductoHistorial` | `producto_historial` | id, user_id, producto_nombre, categoria (enum), cantidad, unidad (enum), precio_real, fecha_compra, lista_id | BelongsTo User, BelongsTo ShoppingList | Append-only; feeds all AI features; RGPD hard-delete only |
| `ProductoCatalogo` | `producto_catalogo` | id, nombre, categoria (enum), unidad_tipica (enum), cantidad_tipica, precio_min, precio_max | — | ~250 Spanish products; price ranges added by Epic 7 |
| `PriceCache` | `price_cache` | id, input_name, precio_min, precio_max, expires_at | — | 30-day TTL; Claude price responses cached |
| `WeeklySummary` | `weekly_summaries` | id, user_id, week_start_date, status (enum), payload_json, claude_cost_usd, dispatched_at, error_message | BelongsTo User | UNIQUE(user_id, week_start_date) for idempotency |
| `AiPrompt` | `ai_prompts` | id, slug, name, description, content, is_active | — | Editable system prompts via Backpack admin; cached |
| `AiUsageLog` | `ai_usage_log` | id, user_id, operation (enum), status (enum), date, estimated_cost_usd, input_tokens, output_tokens | BelongsTo User | No updated_at; append-only cost tracker |
| `AiDismissedSuggestion` | `ai_dismissed_suggestions` | id, user_id, producto_nombre, dismissed_until | BelongsTo User | 24h dismiss or permanent silence |
| `UserSilencedProduct` | `user_silenced_products` | id, user_id, producto_nombre, silenced_at | BelongsTo User | Permanent silence in replenishment alerts |
| `LoginAttempt` | `login_attempts` | id, email, ip_address, attempted_at | — | Failed login tracking; no timestamps |
| `AccountDeletionLog` | `account_deletion_logs` | id, hashed_user_id, reason, deleted_at | — | GDPR audit trail; bcrypt-hashed user ID |
| `WaitlistEntry` | `waitlist_entries` | id, name, email, position, status (enum), invitation_token, invitation_sent_at, invitation_expires_at | — | Pre-launch waitlist with invitation flow |
