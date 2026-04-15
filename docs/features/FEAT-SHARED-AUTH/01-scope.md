# Scope Analysis: FEAT-SHARED-AUTH

## Feature Request

Cuando un usuario con cuenta en Superia accede a una lista compartida (via token link), deberia poder vincular esa lista a su cuenta y acceder siempre a ella con los permisos que el propietario le ha dado, sin necesitar el link cada vez.

Actualmente el sistema de listas compartidas es 100% anonimo y basado en tokens. No hay vinculacion entre el acceso a una lista compartida y una cuenta de usuario.

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **HIGH** |
| Estimated Effort | TBD (requiere definir alcance exacto en PRD) |
| Confidence | Medium |

## Justification

- **Cross-layer**: Afecta backend (auth, middleware, modelos, servicios), frontend (SharedListPage, DashboardPage, navegacion) y base de datos (nueva tabla de relaciones)
- **Seguridad critica**: Vincula acceso anonimo con cuentas autenticadas; requiere validar que los permisos del propietario se respetan
- **Modelo de datos nuevo**: Necesita tabla pivot o modelo de "collaborator" que vincule user_id + shopping_list_id + permisos
- **Flujos multiples**: Usuario anonimo que crea cuenta, usuario existente que abre link, revocacion de acceso, sincronizacion de permisos

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | High | Nuevo modelo de permisos que coexiste con el sistema de tokens anonimos actual. Dos caminos de acceso a la misma lista (token vs. cuenta) |
| Data | Medium | Nueva tabla de relaciones user-list. Migracion necesaria. Hay que decidir que pasa con el historial de sesiones anonimas |
| Security | High | Un usuario autenticado podria intentar escalar permisos (edit cuando el token era read_only). La revocacion del token debe revocar tambien el acceso vinculado |
| Performance | Low | Queries adicionales para resolver permisos, pero volumen bajo |
| Operational | Medium | La feature cambia el flujo de SharedListPage que ya esta en produccion con usuarios reales |

## Affected Areas

- `SharedListPage.jsx` — detectar si el usuario esta autenticado y ofrecer vincular
- `SharedListController.php` — nuevo endpoint para vincular cuenta
- `ValidateShareToken.php` — permitir acceso por user_id ademas de por token
- `DashboardPage.jsx` — mostrar listas compartidas en las que el usuario es colaborador
- `ShoppingListController.php` — incluir listas colaboradas en el listado
- Nueva tabla `list_collaborators` (user_id, shopping_list_id, mode, invited_via_token_id)
- `ListCollaboratorService.php` — nueva logica de negocio

## Open Questions (TBD)

1. **TBD: Cuando se vincula?** — Al abrir el link estando logueado, ¿se vincula automaticamente o hay un boton "Guardar en mis listas"? boton de guardar en mis listas
2. **TBD: Que pasa si el propietario revoca el token?** — ¿Se revoca tambien el acceso del colaborador vinculado, o mantiene acceso? se revoca
3. **TBD: Puede el propietario ver quien tiene acceso vinculado?** — ¿Panel de gestion de colaboradores? sí
4. **TBD: Puede el propietario cambiar permisos individualmente?** — ¿O solo revocar el token completo? token completo
5. **TBD: Las listas colaboradas aparecen en el dashboard del colaborador?** — ¿Con que UI? aparecen como listas le cambiamos el fondo a un azul o verde clarito algo que tenga que ver con el diseño, pero muy sutil para diferenciarlas de las listas propias
6. **TBD: Si un usuario anonimo crea cuenta despues de usar el link, se vincula retroactivamente?** — ¿O solo desde el momento del registro? retroactivamente

## Recommendation

- [ ] Proceed directly (LOW)
- [x] Require PRD (MEDIUM/HIGH -> STEP 2)
- [ ] Escalate to architect

La feature requiere PRD para resolver los TBDs antes de disenar. Es HIGH por la combinacion de seguridad + nuevo modelo de datos + multiples flujos de usuario.

## Transition

- Gate: S1
- Next Step: STEP 2 (PRD Writing)
