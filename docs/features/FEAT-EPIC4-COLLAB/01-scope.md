# Scope Analysis: FEAT-EPIC4-COLLAB

## Feature Request

Epic 4 — Colaboracion y Listas Compartidas. 3 user stories:

- **HU-401**: Compartir lista mediante enlace (token HMAC-SHA256, dos links independientes edit/read-only, revocacion por token, share via WhatsApp/email/copy).
- **HU-402**: Acceder a lista compartida sin cuenta (ver/marcar/editar segun permisos del link, banner informativo con nombre propietario, consentimiento RGPD, CTA registro, manejo de link revocado).
- **HU-403**: Ver colaboradores activos y log de modificaciones (contador "N personas viendo ahora" via heartbeat, log ultimas 50 acciones, polling 10s).

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | 40-50 hours |
| Confidence | High |

## Justification

HIGH because:
1. **Seguridad critica**: Acceso anonimo a datos de usuario via token. Rompe el modelo JWT-only establecido en Epic 1. Nuevo vector de ataque (enumeracion de tokens, brute force, token leakage). HMAC-SHA256 + rate limiting + revocacion son NON-NEGOTIABLE.
2. **Nuevo modelo de autorizacion**: Coexisten dos contextos (usuario autenticado JWT vs. anonimo con token). Todas las operaciones de `ListItem` necesitan distinguir actor y validar permisos segun el modo del link (edit/read-only).
3. **RGPD con usuarios no registrados**: Procesamiento de actividad de personas sin cuenta requiere banner de consentimiento, retencion limitada (30 dias), purga automatica tras revocacion. Compliance implications.
4. **Migraciones nuevas**: 3 tablas (`list_share_tokens`, `list_collaborator_sessions`, `list_activity_log`). Afectan queries de Epic 3.
5. **Presencia en tiempo real (soft)**: Heartbeat + polling 10s. Ciclo de limpieza de sesiones expiradas (30s timeout). Garbage collection de tokens revocados + datos RGPD.
6. **Multiple UI components**: ShareListModal, SharedListPage, CollaboratorIndicator, ActivityLogView, ConsentBanner, RevokedLinkPage. Flujo anonimo completo independiente del autenticado.
7. **Cross-layer impact**: Middleware de auth nuevo (token-based), rutas publicas nuevas, cambios en `ListDetailPage`, cambios en servicios de `ListItem` para logging de actividad.

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | Medium | Coexistencia JWT + token anonimo en el mismo controller. Heartbeat + polling requiere ciclo de limpieza fiable. Garbage collection de tokens revocados y datos RGPD. |
| Data | High | 3 tablas nuevas con retencion variable (tokens persistentes, sesiones 30s, log 50 entradas rolling, datos anonimos 30d). Purga selectiva post-revocacion. Schema debe soportar edit + read-only sin duplicar listas. |
| Security | **High** | Token HMAC debe ser impredecible (min 32 bytes entropia). Rate limit 60 req/min/IP. No leak de informacion en endpoint de token invalido (mismo tiempo de respuesta). Read-only realmente read-only a nivel servicio, no solo UI. Cross-tenant isolation (token A no puede ver lista B). |
| Performance | Medium | Polling cada 10s por cada colaborador activo. Query de contador activos debe ser O(1) con indice. Log de actividad con limite 50 requiere cleanup on write (no cron). |
| Operational | Medium | Nuevo job/comando para cleanup RGPD (30d) y tokens huerfanos. Rate limiter de Laravel. Sin websockets (polling ok), sin dependencias externas nuevas. |

## Affected Areas

- **database/migrations/** — 3 nuevas: `list_share_tokens`, `list_collaborator_sessions`, `list_activity_log`
- **app/Models/** — New `ListShareToken`, `ListCollaboratorSession`, `ListActivityLog`. Update `ShoppingList` (hasMany tokens/sessions/activity)
- **app/Enums/** — New `ShareTokenMode` (edit, read_only), `ActivityAction` (item_added, item_checked, item_unchecked, item_edited, item_deleted, list_cleared), `ActorType` (owner, anonymous)
- **app/Services/** — New `ShareTokenService` (generate/revoke/validate), `CollaboratorPresenceService` (heartbeat/count), `ActivityLogService` (write rolling 50). Update `ListItemService` para escribir log en cada mutacion.
- **app/Http/Middleware/** — New `ValidateShareToken` middleware (token validation, rate limit, read-only enforcement)
- **app/Http/Controllers/** — New `SharedListController` (acceso anonimo), `ShareTokenController` (owner: generar/revocar). Update `ListItemController` para aceptar contexto anonimo.
- **app/Http/Requests/** — New FormRequests para generar/revocar token, para heartbeat
- **app/Console/Commands/** — New `CleanupExpiredCollaboratorData` command (datos anonimos >30d, sesiones expiradas, tokens revocados huerfanos)
- **routes/api.php** — Owner: `POST /api/lists/{id}/share`, `DELETE /api/lists/{id}/share/{tokenId}`, `GET /api/lists/{id}/activity`, `GET /api/lists/{id}/collaborators/count`. Publico: `GET /api/shared/{token}`, `POST /api/shared/{token}/items`, `PATCH /api/shared/{token}/items/{itemId}`, `DELETE /api/shared/{token}/items/{itemId}`, `POST /api/shared/{token}/heartbeat`
- **resources/js/pages/** — New `SharedListPage.jsx` (flujo anonimo completo), update `ListDetailPage.jsx` (boton Compartir, indicador colaboradores, log actividad)
- **resources/js/components/collab/** — `ShareListModal.jsx`, `CollaboratorIndicator.jsx`, `ActivityLogView.jsx`, `ConsentBanner.jsx`, `RevokedLinkView.jsx`
- **resources/js/services/** — `shareApi.js`, `sharedListApi.js` (sin JWT, con token en path)
- **resources/js/app.jsx** — New ruta `/shared/:token`
- **config/** — Rate limiter config para endpoint publico

## Resolved Questions

1. **Identificacion colaborador anonimo (HU-403)**: Sin tracking individual. Se muestra solo "N personas viendo ahora" via contador de sesiones activas (heartbeat). Consistente con filosofia de privacidad; evita cookies/localStorage y zona gris RGPD.

2. **Tipos de enlace (HU-401 crit. 6)**: Dos links independientes por lista — uno `edit`, uno `read_only`. Coexisten. Regenerar uno no afecta al otro. Predecible para el propietario y no rompe colaboradores que ya tienen su enlace.

3. **Revocacion (HU-401 crit. 5)**: Revocar invalida el token concreto. El propietario puede generar uno nuevo inmediatamente bajo demanda. Granular por token (no "desactiva la funcion").

4. **Detection "activo" (HU-403 crit. 2)**: Polling frontend cada 10s envia heartbeat. Sesion se considera inactiva tras 30s sin heartbeat (3 ciclos perdidos). Cleanup en cada query de contador (lazy) o via comando programado.

5. **Log de modificaciones (HU-403 crit. 3)**:
   - Tabla nueva `list_activity_log`
   - Campos: `id`, `list_id`, `actor_type` (owner/anonymous), `action` (enum `ActivityAction`), `item_name` (string, snapshot, no FK), `created_at`
   - Todas las acciones mutativas de items (add/check/uncheck/edit/delete/clear)
   - Rolling 50 entradas por lista: al insertar numero 51, se borra la mas antigua (cleanup on write, no cron)

6. **RGPD acceso anonimo**:
   - Se muestra nombre del propietario en banner (HU-402 crit. 5 lo exige)
   - Banner de consentimiento al primer acceso: "Esta lista es de [nombre]. Al usarla aceptas que registramos tu actividad en esta lista durante 30 dias solo como proposito de utilidad no con fines publicitarios."
   - Retencion 30 dias de `list_collaborator_sessions` y entradas de `list_activity_log` con `actor_type=anonymous`
   - Tras revocar link, los datos anonimos asociados se purgan en el siguiente ciclo (comando `CleanupExpiredCollaboratorData`)

7. **Rate limiting tokens**: 60 req/min por IP en endpoint `/api/shared/{token}/*`. Respuesta 429 con header `Retry-After`. Laravel RateLimiter. Tiempo de respuesta constante para token valido vs invalido (evitar timing oracle).

8. **Freemium interaccion**:
   - La lista compartida **SI** cuenta en el slot del propietario (limite 3 activas Free se mantiene)
   - Usuario Free **SI** puede compartir (colaboracion es el principal vector de crecimiento viral; bloquearlo elimina adquisicion organica)

## Open Questions

None. All resolved.

## Recommendation

- [ ] Proceed directly (LOW -> STEP 1b)
- [x] Require PRD (MEDIUM/HIGH -> STEP 2)
- [ ] Escalate to architect

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)
