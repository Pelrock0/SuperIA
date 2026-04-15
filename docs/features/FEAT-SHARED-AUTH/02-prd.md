# PRD: FEAT-SHARED-AUTH - Acceso persistente a listas compartidas para usuarios autenticados

## Business Objective

Permitir que usuarios con cuenta en Superia vinculen listas compartidas a su cuenta, de modo que puedan acceder siempre a ellas desde su dashboard sin necesitar el link original. Esto convierte la colaboracion anonima actual en una relacion persistente entre usuario y lista.

**Valor**: Retiene usuarios que llegan via links compartidos. Aumenta engagement al integrar listas colaboradas en la experiencia principal de la app. Reduce friccion de acceso repetido.

## Problem Statement

Actualmente, cuando un usuario con cuenta en Superia abre un link compartido, es tratado como un visitante anonimo. Si pierde el link, pierde acceso a la lista. No hay forma de ver listas en las que colabora desde su dashboard. La experiencia es identica para usuarios registrados y anonimos.

## Scope

### In Scope

1. **Boton "Guardar en mis listas"** en SharedListPage cuando el usuario esta autenticado
2. **Tabla `list_collaborators`** que vincula user_id + shopping_list_id + mode + token de origen
3. **Vinculacion retroactiva**: al crear cuenta, vincular automaticamente listas que el usuario uso como anonimo (basado en session UUID del token)
4. **Dashboard del colaborador**: mostrar listas colaboradas con fondo diferenciado (azul/verde sutil) separadas de las propias
5. **Panel de colaboradores para el propietario**: ver que usuarios estan vinculados a cada lista compartida
6. **Revocacion en cascada**: revocar un share token revoca tambien el acceso de todos los colaboradores vinculados via ese token
7. **Acceso directo**: usuario colaborador accede a la lista desde su dashboard sin necesitar el token URL

### Out of Scope

- Invitaciones por email (el acceso sigue siendo via link compartido)
- Permisos individuales por colaborador (se hereda el modo del token: edit o read_only)
- Notificaciones push o email cuando alguien se vincula
- Chat o comentarios entre colaboradores
- Transferencia de propiedad de la lista
- Cambiar permisos de un colaborador individual (solo revocar token completo)

## Acceptance Criteria

### AC-1: Boton "Guardar en mis listas"

- **Given**: un usuario autenticado abre una lista compartida via token URL
- **When**: ve la SharedListPage
- **Then**: aparece un boton "Guardar en mis listas" en la cabecera (no aparece si ya esta vinculado, ni si es el propietario)

### AC-2: Vinculacion exitosa

- **Given**: un usuario autenticado pulsa "Guardar en mis listas"
- **When**: el backend procesa la peticion
- **Then**: se crea un registro en `list_collaborators` con user_id, shopping_list_id, mode (heredado del token), token_id de origen. El boton cambia a un estado "Guardada" deshabilitado.

### AC-3: Dashboard muestra listas colaboradas

- **Given**: un usuario tiene listas colaboradas vinculadas
- **When**: accede a su dashboard (`/app`)
- **Then**: las listas colaboradas aparecen despues de las propias, con un fondo sutil diferenciado (azul/verde clarito). Muestran el nombre del propietario y un badge "COLABORADOR".

### AC-4: Acceso directo desde dashboard

- **Given**: un usuario tiene una lista colaborada vinculada
- **When**: pulsa sobre ella en el dashboard
- **Then**: navega a `/app/listas/:id` y puede interactuar con los permisos que tiene (edit o read_only). El acceso no requiere token URL.

### AC-5: Revocacion en cascada

- **Given**: un propietario revoca un share token
- **When**: el token se marca como revocado
- **Then**: todos los registros de `list_collaborators` vinculados via ese token se eliminan. Los usuarios afectados dejan de ver la lista en su dashboard.

### AC-6: Panel de colaboradores (propietario)

- **Given**: un propietario abre el modal de compartir lista
- **When**: hay colaboradores vinculados
- **Then**: se muestra una seccion con los nombres/emails de los colaboradores vinculados y su modo de acceso (lectura o edicion).

### AC-7: Vinculacion retroactiva al registrarse

- **Given**: un usuario anonimo uso una lista compartida (tiene session UUID en el navegador)
- **When**: crea una cuenta en Superia desde el mismo navegador
- **Then**: las listas que uso como anonimo se vinculan automaticamente a su nueva cuenta con los permisos del token que uso.

### AC-8: Proteccion de duplicados

- **Given**: un usuario ya esta vinculado a una lista
- **When**: abre de nuevo el link compartido de la misma lista
- **Then**: no se crea un duplicado. El boton muestra "Guardada". Si el token tiene permisos diferentes, se mantienen los permisos mas recientes.

### AC-9: Propietario no se auto-vincula

- **Given**: el propietario de una lista abre su propio link compartido
- **When**: ve la SharedListPage
- **Then**: no aparece el boton "Guardar en mis listas" (ya es su lista).

### AC-10: Permisos respetados en acceso directo

- **Given**: un colaborador con modo `read_only` accede a la lista desde su dashboard
- **When**: intenta anadir o eliminar un item
- **Then**: el backend devuelve 403. La UI no muestra controles de edicion.

## UX Decision

- **UX Designer Required**: YES
- **UX Artifacts**: Pendiente en S5-UX
- **Justificacion**: Nueva UI en SharedListPage (boton guardar), nueva seccion en Dashboard (listas colaboradas con fondo diferenciado), nueva seccion en ShareListModal (panel colaboradores). Tres vistas afectadas.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Dos caminos de acceso (token vs. cuenta) pueden divergir en permisos | Technical | Los permisos del colaborador siempre se derivan del token. Si el token se revoca, el acceso vinculado se elimina. |
| Vinculacion retroactiva depende de sessionStorage que puede borrarse | Data | Es best-effort. Si el sessionStorage se borro, el usuario puede re-vincular abriendo el link de nuevo. |
| Escalacion de permisos: usuario intenta acceder como editor cuando su token era read_only | Security | El mode se almacena en `list_collaborators` y se valida en cada request. No se hereda del token en runtime, se fija al vincular. |
| Revocar token sin avisar a colaboradores | Operational | El colaborador simplemente deja de ver la lista. Sin error. Comportamiento limpio. |

## Assumptions

- El usuario autenticado accede a SharedListPage con su JWT activo (el token esta en localStorage)
- La SharedListPage puede detectar si el usuario esta autenticado leyendo el AuthContext
- El acceso directo desde dashboard usa la misma ListDetailPage pero con permisos de colaborador en vez de propietario
- La vinculacion retroactiva usa el session UUID almacenado en sessionStorage como clave de matcheo

## Open Questions

Ninguno. Todos los TBDs del scope estan resueltos.

## Approval

- [ ] PRD approved
