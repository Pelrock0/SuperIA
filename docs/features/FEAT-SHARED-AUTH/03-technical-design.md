# Technical Design: FEAT-SHARED-AUTH

## Overview

Se introduce un modelo `ListCollaborator` que vincula un `user_id` con una `shopping_list_id` y almacena el modo de acceso (`edit`/`read_only`) heredado del share token. Esto permite a usuarios autenticados guardar listas compartidas en su cuenta y acceder a ellas desde su dashboard sin necesitar el token URL.

El sistema coexiste con el acceso anonimo por token: los tokens siguen funcionando igual para usuarios no autenticados. Los usuarios autenticados tienen ademas la opcion de vincular la lista a su cuenta.

La vinculacion retroactiva al registrarse se basa en el `session_uuid` de `list_collaborator_sessions` para identificar listas que el usuario uso como anonimo antes de crear su cuenta.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes |
|-------|-----------------|-------------|
| Domain | Modelo ListCollaborator, enums de permisos | `ListCollaborator`, `ShareTokenMode` (existente) |
| Services | Logica de vinculacion, consulta de listas colaboradas, revocacion en cascada | `ListCollaboratorService` (nuevo), `ShareTokenService` (modificado), `ShoppingListService` (modificado), `RegistrationService` (modificado) |
| Controllers/API | Endpoint vincular, endpoint listar colaboradores, modificar index de listas | `SharedListController` (modificado), `ShoppingListController` (modificado), `ShareTokenController` (modificado) |
| Frontend | Boton guardar en SharedListPage, seccion colaboradas en Dashboard, panel colaboradores en ShareListModal | `SharedListPage.jsx`, `DashboardPage.jsx`, `ShareListModal.jsx` |

### Data Flow

#### Flujo 1: Vincular lista (usuario autenticado en SharedListPage)

```
SharedListPage → POST /api/shared/{tokenParam}/save
  → ValidateShareToken middleware (valida token)
  → auth:api middleware (opcional, valida JWT si presente)
  → SharedListController@saveToAccount
    → ListCollaboratorService::linkUser(user, list, mode, tokenId)
      → DB: INSERT list_collaborators (user_id, shopping_list_id, mode, share_token_id)
      → Return collaborator record
  → 201 Created
```

#### Flujo 2: Dashboard carga listas colaboradas

```
DashboardPage → GET /api/lists
  → ShoppingListController@index
    → ShoppingListService::getListsForUser(user)
      → Query 1: user->shoppingLists (propias)
      → Query 2: user->collaboratedLists (via list_collaborators)
      → Return { active: [...], archived: [...], collaborated: [...] }
```

#### Flujo 3: Acceso directo a lista colaborada

```
DashboardPage → click lista colaborada → /app/listas/:id
  → ListDetailPage (misma pagina que listas propias)
  → GET /api/lists/{id}/items
    → ListItemController@index
      → authorizeAccess(list, user) — propietario O colaborador
      → Return items con permisos segun mode del colaborador
```

#### Flujo 4: Revocacion en cascada

```
ShareListModal → revocar token → DELETE /api/lists/{id}/share-tokens/{tokenId}
  → ShareTokenService::revoke(token)
    → token->update(revoked_at: now())
    → ListCollaborator::where(share_token_id: token.id)->delete()
    → Eliminar sessions
    → syncIsShared(list)
```

#### Flujo 5: Vinculacion retroactiva al registrarse

```
RegisterController@register
  → RegistrationService::register(token, name, password)
    → Crear user
    → ListCollaboratorService::linkRetroactive(user, sessionUuids)
      → Buscar sessions en list_collaborator_sessions por session_uuid
      → Para cada session: crear ListCollaborator con los datos del token
      → Return count de listas vinculadas
```

### Transaction Boundaries

- **Vincular lista**: Transaccion en `ListCollaboratorService::linkUser()` — INSERT con unique constraint (user_id, shopping_list_id). Si ya existe, upsert mode.
- **Revocar token**: Transaccion existente en `ShareTokenService::revoke()` — se extiende para eliminar colaboradores vinculados.
- **Registro retroactivo**: Transaccion en `RegistrationService::register()` — se extiende para incluir vinculacion retroactiva.

## Data Model

### New Table: `list_collaborators`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| user_id | BIGINT UNSIGNED | FK → users.id, ON DELETE CASCADE |
| shopping_list_id | BIGINT UNSIGNED | FK → shopping_lists.id, ON DELETE CASCADE |
| mode | ENUM('edit', 'read_only') | NOT NULL |
| share_token_id | BIGINT UNSIGNED | FK → list_share_tokens.id, ON DELETE SET NULL, nullable |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

**Indexes:**
- UNIQUE(user_id, shopping_list_id) — un usuario solo puede estar vinculado una vez a cada lista
- INDEX(shopping_list_id) — para consultar colaboradores de una lista
- INDEX(share_token_id) — para revocacion en cascada

### New Model: `ListCollaborator`

```php
class ListCollaborator extends Model {
    protected $fillable = ['user_id', 'shopping_list_id', 'mode', 'share_token_id'];
    protected $casts = ['mode' => ShareTokenMode::class];

    public function user(): BelongsTo
    public function shoppingList(): BelongsTo
    public function shareToken(): BelongsTo
}
```

### Modified Models

**User** — nueva relacion:
```php
public function collaboratedLists(): BelongsToMany {
    return $this->belongsToMany(ShoppingList::class, 'list_collaborators')
        ->withPivot('mode', 'share_token_id')
        ->withTimestamps();
}
```

**ShoppingList** — nueva relacion:
```php
public function collaborators(): HasMany {
    return $this->hasMany(ListCollaborator::class);
}
```

### API Changes

| Endpoint | Method | Purpose | Auth |
|----------|--------|---------|------|
| `/api/shared/{tokenParam}/save` | POST | Vincular lista a cuenta del usuario autenticado | JWT + ShareToken |
| `/api/shared/{tokenParam}/save-status` | GET | Consultar si ya esta vinculada | JWT + ShareToken |
| `/api/lists/{id}/collaborators` | GET | Listar colaboradores (solo propietario) | JWT |

### Modified Endpoints

| Endpoint | Change |
|----------|--------|
| `GET /api/lists` | Response incluye campo `collaborated` con listas vinculadas |
| `GET /api/lists/{id}/items` | Permitir acceso a colaboradores (no solo propietario) |
| `POST /api/lists/{id}/items` | Permitir a colaboradores con modo `edit` |
| `PUT /api/lists/{id}/items/{item}` | Permitir a colaboradores con modo `edit` |
| `PATCH /api/lists/{id}/items/{item}/toggle` | Permitir a colaboradores con modo `edit` o `read_only` |
| `DELETE /api/lists/{id}/items/{item}` | Permitir a colaboradores con modo `edit` |

## Authorization Model

Nuevo metodo en `ListItemController` y `ShoppingListController`:

```php
private function authorizeListAccess(ShoppingList $list, ?string $requiredMode = null): void
{
    $userId = auth('api')->id();

    // Propietario tiene acceso total
    if ($list->user_id === $userId) {
        return;
    }

    // Colaborador: verificar vinculacion y modo
    $collaborator = ListCollaborator::where('user_id', $userId)
        ->where('shopping_list_id', $list->id)
        ->first();

    if (!$collaborator) {
        abort(403, 'No tienes acceso a esta lista.');
    }

    if ($requiredMode === 'edit' && !$collaborator->mode->allowsWrite()) {
        abort(403, 'No tienes permisos de edicion en esta lista.');
    }
}
```

## Performance

### Query Optimization

- `GET /api/lists`: Una query extra con `collaboratedLists()->with('user:id,name')->get()`. Indice UNIQUE(user_id, shopping_list_id) hace la query eficiente.
- Acceso a lista colaborada: Una query extra para verificar colaborador. Indice cubre el caso.
- No hay N+1: colaboradores se cargan con eager loading cuando se necesitan.

### Caching

No se requiere cache especifico. El volumen de colaboradores por lista es bajo (< 10 tipico).

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Tabla pivot `list_collaborators` | Relacion directa, queries simples, permisos claros | Tabla extra, migracion | **Selected** — modelo limpio y extensible |
| Reusar `list_collaborator_sessions` con user_id | Sin tabla nueva | Mezcla sesiones anonimas con vinculaciones permanentes, semantica confusa | Rejected — viola SRP |
| Permisos en JSON en ShoppingList | Sin tabla extra | Dificil de consultar, no indexable, no relacional | Rejected — anti-pattern |

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Escalacion de permisos al cambiar token mode | High | Low | Mode se fija al vincular, no se re-evalua dinamicamente |
| Vinculacion retroactiva falla si sessionStorage se borro | Medium | Medium | Es best-effort. Usuario puede re-vincular abriendo el link |
| Colaborador accede a lista eliminada | Low | Low | FK con ON DELETE CASCADE elimina colaboradores |
| Dos colaboradores del mismo token con modos diferentes (imposible hoy, posible si se cambia el token) | Medium | Low | Mode en list_collaborators es independiente del token actual |

## Implementation Notes

### Orden de implementacion sugerido

1. **Migracion** — crear tabla `list_collaborators`
2. **Modelo** — `ListCollaborator` con relaciones
3. **Servicio** — `ListCollaboratorService` (link, unlink, check, listForUser)
4. **Backend: vincular** — endpoint POST `/api/shared/{tokenParam}/save`
5. **Backend: autorización** — modificar `authorizeListOwnership` → `authorizeListAccess` en controllers
6. **Backend: listar** — modificar `ShoppingListService::getListsForUser` para incluir colaboradas
7. **Backend: colaboradores** — endpoint GET `/api/lists/{id}/collaborators`
8. **Backend: revocacion** — extender `ShareTokenService::revoke`
9. **Backend: retroactivo** — extender `RegistrationService::register`
10. **Frontend: SharedListPage** — boton "Guardar en mis listas"
11. **Frontend: DashboardPage** — seccion listas colaboradas con fondo diferenciado
12. **Frontend: ShareListModal** — panel de colaboradores
13. **Tests** — unitarios y feature para cada AC

### Session UUID para vinculacion retroactiva

El frontend ya almacena un session UUID en `sessionStorage` bajo la clave `superia:session:{tokenParam}`. Para la vinculacion retroactiva:

1. Al registrarse, el frontend envia los session UUIDs que tenga en sessionStorage
2. El backend busca en `list_collaborator_sessions` los registros con esos UUIDs
3. Para cada match, crea un `ListCollaborator` con los datos del token asociado
4. Esto es best-effort y solo funciona si el registro se hace desde el mismo navegador

## Open Questions

Ninguno. Todos los requisitos estan definidos en el PRD.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 - Implementation
- Required Artifacts: 02-prd.md, 03-technical-design.md
