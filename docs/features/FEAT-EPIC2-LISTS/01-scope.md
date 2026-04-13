# Scope Analysis: FEAT-EPIC2-LISTS

## Feature Request

Epic 2 — Gestion de Listas de Compra. 5 user stories:

- **HU-201**: Dashboard con listas (tarjetas con nombre, items pendientes/total, fecha, indicador compartida, seccion archivadas, pantalla bienvenida si no hay listas)
- **HU-202**: Crear nueva lista (nombre obligatorio max 60 chars, emoji/icono opcional, categoria opcional: Supermercado/Mercado/Online/Farmacia/Otro, max 3 activas freemium)
- **HU-203**: Editar nombre y categoria (autoguardado, revertir si nombre vacio)
- **HU-204**: Archivar y restaurar (seccion separada, no cuenta en limite freemium)
- **HU-205**: Eliminar lista (confirmacion, aviso si compartida, permanente)

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **MEDIUM** |
| Estimated Effort | 20-25 hours |
| Confidence | High |

## Justification

MEDIUM because:
1. **Database migrations required**: New tables `shopping_lists` (con enum status, categoria, emoji, relacion usuario)
2. **Business logic**: Freemium limit (max 3 active lists), archive/restore state transitions, ownership validation
3. **Multiple UI components**: DashboardPage (new), list cards, create modal, empty state, archived section
4. **Existing flows modified**: Dashboard route `/app` currently renders placeholder — needs full implementation
5. No external integrations. No security-critical new patterns (uses existing JWT auth).

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Low | Standard CRUD with Eloquent. Builds on existing auth middleware. No new patterns. |
| Data | Medium | New `shopping_lists` table. Need to consider cascade behavior on user deletion (connects with HU-105 AccountDeletionService). |
| Security | Low | All endpoints behind existing JWT auth. Ownership checks needed (user can only see/edit own lists). |
| Performance | Low | Simple queries with user_id filter. Index on user_id sufficient at current scale. |
| Operational | Low | Standard migration. No background jobs. No external dependencies. |

## Affected Areas

- **database/migrations/** — New `shopping_lists` table
- **app/Models/** — New `ShoppingList` model with User relation
- **app/Services/** — New `ShoppingListService` (CRUD, freemium limit, archive/restore)
- **app/Http/Controllers/** — New `ShoppingListController` (API endpoints)
- **app/Http/Requests/** — New FormRequests for create/update
- **routes/api.php** — New list endpoints
- **resources/js/pages/DashboardPage.jsx** — New (replaces placeholder)
- **resources/js/components/** — ListCard, CreateListModal, EmptyState
- **app/Services/AccountDeletionService.php** — Update to delete user's lists on account deletion
- **tests/** — Full coverage for all flows

## Resolved Questions

1. **Shared lists indicator on HU-201**: Include as placeholder in model and UI (boolean `is_shared` defaulting to false). Functionality deferred to Epic 4. Note: feature is intentionally incomplete until Epic 4.
2. **Emoji storage**: Store as unicode character directly in DB (`VARCHAR(10)`, utf8mb4 charset). MySQL config confirmed: `utf8mb4` + `utf8mb4_unicode_ci` — full emoji support.

## Open Questions

None. All resolved.

## Recommendation

- [ ] Proceed directly (LOW -> STEP 1b)
- [x] Require PRD (MEDIUM/HIGH -> STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)
