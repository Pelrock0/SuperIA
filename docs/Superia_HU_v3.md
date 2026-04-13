# SUPERIA — Historias de Usuario v3.0
> **La compra, más inteligente**  
> Documento para Sofia4Builders · Stack: FastAPI · React · MySQL · Claude API · Railway  
> 11 épicas · 45 historias de usuario · Abril 2026

---

## 🔧 Guía Técnica para Agentes Sofia4Builders

### Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel (PHP) |
| Frontend | React · generado desde diseños de Google Stitch via MCP |
| Base de datos | MySQL · alojado en Dinahosting |
| IA | Claude API (Anthropic) · nunca expuesta al frontend |
| Autenticación | JWT · access token 15min · refresh token 30 días |
| Precios | Historial usuario → Dataset estático → Open Food Facts → Claude API |
| Jobs background | jobs de laravel (resumen semanal HU-505) |
| Dominio | superia.app o usesuperia.com (pendiente confirmar) |

---

### 🎨 Diseño Frontend — Google Stitch via MCP

Todas las pantallas están diseñadas en el proyecto **"Superia"** de Google Stitch.  
El agente de frontend **DEBE** conectarse a Stitch via MCP antes de generar cualquier componente React.

**Configuración MCP:**
```bash
claude mcp add stitch --transport http https://stitch.googleapis.com/mcp \
  --header "X-Goog-Api-Key: TU_API_KEY" -s user
```

**Instrucción al agente de frontend:**
```
Conecta al proyecto Superia en Stitch via MCP. Para cada HU con pantalla 
asociada, lee la pantalla indicada en las notas técnicas y genera el 
componente React correspondiente conectado al endpoint FastAPI definido en la HU.
```

### Mapa de Pantallas Stitch → Componentes React

| HU | Pantalla Stitch | Componente React | Ruta |
|----|----------------|-----------------|------|
| HU-001 | Landing | `LandingPage` | `/` |
| HU-002 | Landing (form) | `WaitlistForm` | `/` |
| HU-101 | Registro | `RegisterPage` | `/register` |
| HU-102 | Login | `LoginPage` | `/login` |
| HU-201 | Dashboard | `DashboardPage` | `/app` |
| HU-301 | Detalle lista | `ListDetailPage` | `/app/listas/:id` |
| HU-302/304 | Añadir ítem | `AddItemSheet` | (bottom sheet) |
| HU-401 | Compartir lista | `ShareListModal` | (modal) |
| HU-402 | Vista compartida | `SharedListPage` | `/shared/:token` |
| HU-501 | Autocompletado | `ItemAutocomplete` | (componente) |
| HU-601 | Generar con IA | `AIGeneratePage` | `/app/generar` |
| HU-503 | Reposición | `ReplenishmentBanner` | (componente dashboard) |
| HU-505 | Resumen semanal | `WeeklySummaryPage` | `/app/resumen` |
| HU-901 | Historial | `HistoryPage` | `/app/historial` |
| HU-1001 | Admin | `AdminDashboard` | `/admin` |

---

### 🤖 Arquitectura IA — Reglas para Agentes

- **Nunca** exponer la Claude API key al frontend. Todas las llamadas pasan por FastAPI.
- **Sugerencias (HU-501):** 3 capas — historial personal MySQL → catálogo precargado local → Claude API solo tras 2s de pausa. Sin lag visible nunca.
- **Precios (HU-701):** historial usuario → dataset estático mensual → Open Food Facts API → Claude API como último recurso.
- **Historial consumo (HU-502):** tabla `producto_historial` independiente de las listas. Se alimenta al marcar ítems como comprados en CUALQUIER lista del usuario.
- **Rate limiting:** implementar middleware FastAPI antes de cada llamada Claude. Respetar límites por HU.
- **Respuestas Claude:** siempre en JSON estructurado. Validar esquema antes de devolver al frontend. Reintentar una vez si JSON inválido.
- **Jobs background (HU-505):** APScheduler dentro de FastAPI. No usar Celery.

---

## Índice de Épicas

- [Épica 0 — Landing y Lista de Espera](#épica-0--landing-y-lista-de-espera)
- [Épica 1 — Autenticación y Gestión de Usuarios](#épica-1--autenticación-y-gestión-de-usuarios)
- [Épica 2 — Gestión de Listas de Compra](#épica-2--gestión-de-listas-de-compra)
- [Épica 3 — Ítems dentro de una Lista](#épica-3--ítems-dentro-de-una-lista)
- [Épica 4 — Colaboración y Listas Compartidas](#épica-4--colaboración-y-listas-compartidas)
- [Épica 5 — IA: Sugerencias y Reposición Inteligente](#épica-5--ia-sugerencias-y-reposición-inteligente)
- [Épica 6 — IA: Generación de Lista por Contexto](#épica-6--ia-generación-de-lista-por-contexto)
- [Épica 7 — IA: Estimación de Precio Total](#épica-7--ia-estimación-de-precio-total)
- [Épica 8 — IA: Detección de Duplicados y Agrupación](#épica-8--ia-detección-de-duplicados-y-agrupación)
- [Épica 9 — Historial y Estadísticas](#épica-9--historial-y-estadísticas)
- [Épica 10 — Administración SaaS](#épica-10--administración-saas)

---

## Épica 0 — Landing y Lista de Espera
> Primera presencia pública de Superia. Valida demanda antes de abrir el producto.

---

### HU-001 — Ver landing page de Superia
**Actor:** Visitante anónimo | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como visitante anónimo, quiero ver una landing page atractiva que explique qué es Superia y por qué es diferente, para decidir si me apunto a la lista de espera.

**Criterios de aceptación:**
1. La página muestra el nombre Superia y el tagline 'La compra, más inteligente'.
2. Hay una sección hero con una propuesta de valor clara visible sin hacer scroll.
3. Se muestran al menos 3 características clave de la app (IA, listas compartidas, historial).
4. La página es completamente responsive en móvil, tablet y escritorio.
5. No se usan cookies de tracking ni scripts de terceros analíticos.
6. La velocidad de carga es inferior a 2 segundos en conexión móvil estándar.

**📌 Notas técnicas:**
- Sin frameworks de analytics. Sin Google Analytics. Sin Meta Pixel.
- STITCH: Proyecto 'Superia' → pantalla 'Landing'. Leer via MCP antes de generar `LandingPage.jsx`.

---

### HU-002 — Registrarse en la lista de espera
**Actor:** Visitante anónimo | **Prioridad:** Alta | **Estimación:** S

**Narrativa:**  
Como visitante interesado, quiero dejar mi email en la lista de espera, para ser de los primeros en acceder cuando Superia abra.

**Criterios de aceptación:**
1. El formulario solicita nombre y email como campos obligatorios.
2. Pregunta opcional: '¿Con quién sueles compartir la compra?' con opciones: Solo, Pareja, Familia, Compañeros de piso.
3. Al enviar, el sistema valida que el email tiene formato correcto.
4. Si el email ya existe, muestra mensaje amigable sin revelar que está registrado.
5. El usuario recibe un email de confirmación automático.
6. El email de confirmación muestra su posición aproximada en la lista (ej: 'Eres el número 47').
7. Se muestra un mensaje de éxito en pantalla tras el envío.

**📌 Notas técnicas:**
- Backend: endpoint `POST /api/waitlist` con rate limiting (max 3 intentos por IP/hora).
- El contador de posición puede ser aproximado (+/- 5) para evitar scraping.
- STITCH: Proyecto 'Superia' → pantalla 'Landing' (sección formulario) → componente `WaitlistForm.jsx`.

---

### HU-003 — Ver política de privacidad y compromiso de datos
**Actor:** Visitante anónimo | **Prioridad:** Alta | **Estimación:** S

**Narrativa:**  
Como visitante, quiero ver claramente el compromiso de Superia con mis datos, para decidir si confío en la app con mi información.

**Criterios de aceptación:**
1. La landing incluye una sección visible (no solo pie de página) con el compromiso de datos.
2. El texto especifica: sin cookies de tracking, sin venta de datos, sin publicidad.
3. Hay un enlace a la política de privacidad completa conforme al RGPD.
4. La política de privacidad incluye: datos que se recogen, finalidad, plazo de conservación, derechos del usuario.
5. El pie de página incluye enlace a política de privacidad y aviso legal.

**📌 Notas técnicas:**
- Texto sugerido: "Tus listas son tuyas. Nunca las venderemos, nunca las usaremos para publicidad."

---

### HU-004 — Gestionar lista de espera desde administración
**Actor:** Administrador | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como administrador, quiero ver y gestionar los registros de la lista de espera, para poder enviar invitaciones cuando decida abrir el acceso.

**Criterios de aceptación:**
1. El panel de admin muestra tabla con: nombre, email, fecha de registro, respuesta a la pregunta opcional.
2. Se puede exportar la lista completa en CSV.
3. Se puede marcar a un usuario como 'invitado' para enviarle acceso.
4. Al marcar como invitado, el sistema envía automáticamente un email con enlace de registro único.
5. El enlace de invitación expira en 7 días.
6. Se puede ver el total de registros en lista de espera.

**📌 Notas técnicas:**
- El panel de admin es una ruta protegida `/admin` accesible solo con rol superadmin.

---

## Épica 1 — Autenticación y Gestión de Usuarios
> Registro seguro, login, perfil y cumplimiento RGPD.

---

### HU-101 — Registrarse con invitación
**Actor:** Usuario invitado | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como usuario invitado, quiero registrarme en Superia usando el enlace de invitación que recibí, para crear mi cuenta y empezar a usar la app.

**Criterios de aceptación:**
1. El enlace de invitación lleva a un formulario de registro con email pre-rellenado.
2. El formulario solicita: nombre, contraseña (mín. 8 caracteres, 1 mayúscula, 1 número) y confirmación.
3. Si el enlace ha expirado (>7 días), muestra mensaje claro y opción de volver a la lista de espera.
4. Tras el registro exitoso, el usuario recibe email de verificación de cuenta.
5. La cuenta no queda activa hasta verificar el email.
6. Tras verificar, el usuario es redirigido al dashboard con un mensaje de bienvenida.
7. Se muestra casilla de aceptación de política de privacidad, obligatoria para continuar.

**📌 Notas técnicas:**
- JWT con refresh token. Access token expira en 15 minutos. Refresh token en 30 días.
- Passwords hasheados con bcrypt, coste mínimo 12.
- STITCH: Proyecto 'Superia' → pantalla 'Registro'. Leer via MCP antes de generar `RegisterPage.jsx`.

---

### HU-102 — Iniciar sesión
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** S

**Narrativa:**  
Como usuario registrado, quiero iniciar sesión con mi email y contraseña, para acceder a mis listas de compra.

**Criterios de aceptación:**
1. El formulario solicita email y contraseña.
2. Si las credenciales son incorrectas, muestra mensaje genérico sin especificar cuál campo falló.
3. Tras 5 intentos fallidos consecutivos, la cuenta se bloquea durante 15 minutos.
4. El usuario puede marcar 'Recuérdame' para mantener la sesión 30 días.
5. Tras login exitoso, el usuario va al dashboard.
6. Hay enlace a recuperación de contraseña.

**📌 Notas técnicas:**
- STITCH: Proyecto 'Superia' → pantalla 'Login'. Leer via MCP antes de generar `LoginPage.jsx`.

---

### HU-103 — Recuperar contraseña
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** S

**Narrativa:**  
Como usuario que olvidó su contraseña, quiero recibir un enlace para restablecerla, para recuperar el acceso a mi cuenta.

**Criterios de aceptación:**
1. El usuario introduce su email en un formulario de recuperación.
2. El sistema siempre muestra el mismo mensaje de confirmación independientemente de si el email existe.
3. Si el email existe, se envía un enlace de restablecimiento válido 1 hora.
4. El enlace lleva a un formulario donde el usuario introduce la nueva contraseña dos veces.
5. Tras restablecer, la sesión anterior queda invalidada.
6. El enlace solo puede usarse una vez.

---

### HU-104 — Ver y editar perfil
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** S

**Narrativa:**  
Como usuario, quiero ver y editar mi información de perfil, para mantener mis datos actualizados.

**Criterios de aceptación:**
1. El usuario puede editar: nombre y contraseña.
2. Para cambiar la contraseña debe introducir la actual.
3. El email no es editable (es el identificador de cuenta).
4. Los cambios se confirman con mensaje de éxito.

**📌 Notas técnicas:**
- STITCH: Proyecto 'Superia' → pantalla 'Perfil'. Leer via MCP antes de generar `ProfilePage.jsx`.

---

### HU-105 — Eliminar cuenta y datos (RGPD)
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como usuario, quiero poder eliminar mi cuenta y todos mis datos permanentemente, para ejercer mi derecho al olvido conforme al RGPD.

**Criterios de aceptación:**
1. Hay una opción de eliminación de cuenta en el perfil, claramente visible.
2. El sistema pide confirmación con la contraseña actual antes de proceder.
3. Se muestra advertencia clara: 'Esta acción eliminará todas tus listas, ítems e historial de forma permanente e irreversible.'
4. Tras confirmar, se eliminan: cuenta, listas propias, ítems, historial, datos de lista de espera.
5. Las listas compartidas transfieren la propiedad o se eliminan si era el único miembro.
6. El usuario recibe email de confirmación de eliminación.
7. El proceso completo de eliminación se completa en máximo 30 días (conforme a RGPD).

**📌 Notas técnicas:**
- Soft delete inicial con hard delete batch a los 30 días.
- Guardar log de eliminación para auditoría RGPD sin datos personales.

---

## Épica 2 — Gestión de Listas de Compra
> Crear, organizar y gestionar múltiples listas personales.

---

### HU-201 — Ver dashboard con mis listas
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como usuario, quiero ver todas mis listas de compra en un dashboard claro, para tener una visión rápida de todo lo que tengo pendiente.

**Criterios de aceptación:**
1. El dashboard muestra todas las listas del usuario en formato tarjeta.
2. Cada tarjeta muestra: nombre, ítems pendientes/total, fecha última modificación, indicador si es compartida.
3. Las listas activas aparecen primero, las archivadas en sección separada.
4. Hay un botón destacado para crear nueva lista.
5. Si no hay listas, se muestra pantalla de bienvenida con CTA para crear la primera.
6. El dashboard es responsive y funciona bien en móvil.

**📌 Notas técnicas:**
- STITCH: Proyecto 'Superia' → pantalla 'Dashboard'. Leer via MCP antes de generar `DashboardPage.jsx`.
- Incluir componente `ReplenishmentBanner` (HU-503) en el dashboard.

---

### HU-202 — Crear nueva lista de compra
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** S

**Narrativa:**  
Como usuario, quiero crear una nueva lista de compra con nombre y opcionalmente una categoría, para organizar mis compras por contexto.

**Criterios de aceptación:**
1. El usuario puede crear una lista introduciendo un nombre (obligatorio, máx. 60 caracteres).
2. Puede seleccionar opcionalmente un emoji o icono para identificar la lista visualmente.
3. Puede seleccionar una categoría opcional: Supermercado, Mercado, Online, Farmacia, Otro.
4. La lista se crea vacía y el usuario queda en la vista de detalle de la lista.
5. El nombre de la lista no puede estar vacío.
6. Un usuario puede tener máximo 3 listas activas en plan gratuito (límite freemium).

**📌 Notas técnicas:**
- El límite freemium se controla en backend. Frontend muestra mensaje de upgrade al alcanzarlo.

---

### HU-203 — Editar nombre y categoría de una lista
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** XS

**Narrativa:**  
Como usuario, quiero editar el nombre, icono o categoría de una lista existente, para mantenerla organizada según mis necesidades.

**Criterios de aceptación:**
1. El usuario puede editar nombre, emoji e icono desde la vista de detalle.
2. Los cambios se guardan automáticamente (sin botón de guardar explícito).
3. Si el nombre queda vacío, se revierte al nombre anterior.

---

### HU-204 — Archivar y restaurar una lista
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** S

**Narrativa:**  
Como usuario, quiero archivar listas que ya completé sin borrarlas, para mantener el historial sin saturar el dashboard.

**Criterios de aceptación:**
1. Desde el menú de opciones de una lista, el usuario puede archivarla.
2. Las listas archivadas se mueven a sección 'Archivadas' en el dashboard.
3. El usuario puede restaurar una lista archivada al estado activo.
4. El contenido de la lista se conserva íntegro al archivar y restaurar.
5. Las listas archivadas no cuentan en el límite freemium de listas activas.

---

### HU-205 — Eliminar una lista
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** XS

**Narrativa:**  
Como usuario, quiero eliminar una lista que ya no necesito, para mantener mi dashboard limpio.

**Criterios de aceptación:**
1. El usuario puede eliminar una lista desde el menú de opciones.
2. Se muestra diálogo de confirmación antes de eliminar.
3. Si la lista está compartida, se avisa que los colaboradores perderán el acceso.
4. La eliminación es permanente e inmediata.
5. Tras eliminar, el usuario regresa al dashboard.

---

## Épica 3 — Ítems dentro de una Lista
> Añadir, gestionar y completar productos en una lista de compra.

---

### HU-301 — Ver detalle de una lista con sus ítems
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como usuario, quiero ver todos los ítems de una lista organizada, para saber qué tengo que comprar de un vistazo.

**Criterios de aceptación:**
1. Los ítems pendientes aparecen arriba y los marcados como comprados abajo.
2. Cada ítem muestra: nombre, cantidad, unidad y categoría de producto.
3. Los ítems están agrupados por categoría para facilitar el recorrido por el súper.
4. Hay un contador de progreso visible: 'X de Y ítems comprados'.
5. La vista funciona bien en móvil con gestos táctiles.

**📌 Notas técnicas:**
- STITCH: Proyecto 'Superia' → pantalla 'Detalle lista'. Leer via MCP antes de generar `ListDetailPage.jsx`.

---

### HU-302 — Añadir ítem a una lista
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como usuario, quiero añadir productos a mi lista de compra de forma rápida, para construir mi lista sin fricciones.

**Criterios de aceptación:**
1. El usuario puede añadir un ítem escribiendo el nombre en un campo de entrada siempre visible.
2. Puede especificar opcionalmente: cantidad, unidad (kg, g, L, ml, ud, pack), categoría.
3. Al pulsar Enter o el botón añadir, el ítem se agrega al final de su categoría.
4. El campo de entrada se limpia y queda listo para el siguiente ítem.
5. El nombre del ítem es obligatorio (máx. 80 caracteres).
6. Puede añadir también un precio estimado opcional para calcular el total.

**📌 Notas técnicas:**
- STITCH: Proyecto 'Superia' → pantalla 'Añadir ítem'. Leer via MCP antes de generar `AddItemSheet.jsx`.

---

### HU-303 — Marcar ítem como comprado
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** S

**Narrativa:**  
Como usuario haciendo la compra, quiero marcar ítems como comprados con un toque, para saber qué me queda por coger.

**Criterios de aceptación:**
1. El usuario puede marcar/desmarcar un ítem con un toque en el checkbox.
2. Los ítems marcados se muestran con tachado visual y bajan al final de la lista.
3. El cambio es instantáneo y no requiere confirmación.
4. Si todos los ítems están marcados, se muestra mensaje de felicitación.
5. El marcado se sincroniza en tiempo real si la lista es compartida.

**📌 Notas técnicas:**
- Al marcar como comprado, registrar en tabla `producto_historial` (alimenta HU-502, HU-503, HU-504, HU-505).

---

### HU-304 — Editar un ítem
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** S

**Narrativa:**  
Como usuario, quiero editar un ítem ya añadido, para corregir errores o actualizar cantidad y unidad.

**Criterios de aceptación:**
1. Al pulsar sobre un ítem (sin ser el checkbox), se abre un panel de edición.
2. Se pueden editar: nombre, cantidad, unidad, categoría y precio estimado.
3. Los cambios se guardan al cerrar el panel o pulsar guardar.
4. Se puede cancelar sin guardar cambios.

---

### HU-305 — Eliminar un ítem
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** XS

**Narrativa:**  
Como usuario, quiero eliminar un ítem de la lista, para quitar productos que ya no necesito.

**Criterios de aceptación:**
1. El usuario puede eliminar un ítem con gesto de deslizamiento (swipe left) en móvil.
2. También hay opción de eliminar desde el panel de edición del ítem.
3. No se pide confirmación para agilizar el flujo.
4. Hay opción de deshacer durante 5 segundos tras la eliminación (snackbar con 'Deshacer').

---

### HU-306 — Limpiar ítems comprados
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** XS

**Narrativa:**  
Como usuario, quiero borrar de una vez todos los ítems ya marcados como comprados, para preparar la lista para la próxima compra.

**Criterios de aceptación:**
1. Hay una acción 'Limpiar comprados' en el menú de opciones de la lista.
2. Se muestra confirmación antes de ejecutar.
3. Solo se eliminan los ítems marcados. Los pendientes permanecen.
4. Tras limpiar, el progreso se resetea.

---

## Épica 4 — Colaboración y Listas Compartidas
> Compartir listas con otras personas con o sin cuenta.

---

### HU-401 — Compartir una lista mediante enlace
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como usuario, quiero compartir una lista de compra con otra persona enviándole un enlace, para que pueda colaborar sin necesidad de tener cuenta.

**Criterios de aceptación:**
1. Desde el detalle de una lista hay un botón 'Compartir'.
2. Al pulsarlo se genera un enlace único para esa lista.
3. El usuario puede copiar el enlace o compartirlo directamente por WhatsApp, email, etc.
4. El enlace permite a quien lo reciba ver y modificar la lista sin registro.
5. El propietario puede revocar el enlace en cualquier momento.
6. Se puede generar un enlace de solo lectura (ver pero no modificar).

**📌 Notas técnicas:**
- El token del enlace es HMAC-SHA256 con expiración configurable (por defecto sin expiración).
- Un usuario sin cuenta que accede por enlace puede marcar ítems pero no crear listas propias.
- STITCH: Proyecto 'Superia' → pantalla 'Compartir lista'. Leer via MCP antes de generar `ShareListModal.jsx`.

---

### HU-402 — Acceder a una lista compartida sin cuenta
**Actor:** Colaborador sin cuenta | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como persona que recibió un enlace, quiero acceder a la lista de compra directamente, para colaborar sin tener que registrarme.

**Criterios de aceptación:**
1. Al abrir el enlace, se muestra la lista sin requerir login.
2. El colaborador puede ver todos los ítems y su estado.
3. Puede marcar/desmarcar ítems como comprados.
4. Si el enlace es de edición, puede también añadir y eliminar ítems.
5. Se muestra un banner informando que es una lista compartida y quién es el propietario.
6. Se invita sutilmente a registrarse para crear sus propias listas.
7. Si el enlace ha sido revocado, muestra mensaje claro.

**📌 Notas técnicas:**
- STITCH: Proyecto 'Superia' → pantalla 'Vista compartida'. Leer via MCP antes de generar `SharedListPage.jsx`.

---

### HU-403 — Ver quién está colaborando en una lista
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** S

**Narrativa:**  
Como propietario de una lista compartida, quiero ver quién está accediendo a mi lista, para saber que la colaboración funciona.

**Criterios de aceptación:**
1. En el detalle de una lista compartida, se muestra indicador de colaboradores activos.
2. Si un colaborador está viendo la lista en ese momento, se muestra un indicador visual.
3. El propietario puede ver el historial de últimas modificaciones con timestamp.
4. Los cambios de colaboradores se sincronizan en tiempo real (polling cada 10 segundos como mínimo).

---

## Épica 5 — IA: Sugerencias y Reposición Inteligente
> Autocompletado instantáneo, aprendizaje de hábitos y reposición proactiva.

---

### HU-501 — Recibir sugerencias de productos al escribir 🤖
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** L

**Narrativa:**  
Como usuario añadiendo ítems, quiero recibir sugerencias inteligentes mientras escribo, para añadir productos rápidamente sin tener que escribirlos completo.

**Criterios de aceptación:**
1. Al escribir 2 o más caracteres, aparecen hasta 5 sugerencias en menos de 50ms.
2. Las sugerencias incluyen nombre, unidad habitual y categoría.
3. Las sugerencias priorizan productos del historial personal del usuario.
4. Si el historial no devuelve 3+ resultados, se busca en el catálogo precargado de supermercado español.
5. Al seleccionar una sugerencia, el ítem se añade con todos sus atributos pre-rellenados.
6. Si el usuario hace pausa de 2 segundos y los resultados locales son escasos, se lanza consulta a Claude API en background.
7. Si no hay sugerencias relevantes, no muestra nada (sin lista vacía).

**📌 Notas técnicas:**
- **ARQUITECTURA DE 3 CAPAS — sin lag visible:**
  - **Capa 1** (<20ms): búsqueda full-text en historial personal MySQL.
  - **Capa 2** (<50ms): búsqueda en catálogo precargado (~2.500 productos españoles). Generado con Claude UNA VEZ en setup. Actualización mensual. NUNCA llamada en tiempo real.
  - **Capa 3** (diferida, solo tras 2s de pausa): Claude API en background si capas 1+2 devuelven <3 resultados.
- Rate limit capa 3: máximo 20 llamadas Claude/día en plan gratuito.
- STITCH: Proyecto 'Superia' → pantalla 'Autocompletado'. Leer via MCP antes de generar `ItemAutocomplete.jsx`.

---

### HU-502 — El sistema aprende de mis productos habituales 🤖
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** M

**Narrativa:**  
Como usuario recurrente, quiero que las sugerencias mejoren con el tiempo aprendiendo de lo que compro habitualmente, para que la app se adapte a mis hábitos.

**Criterios de aceptación:**
1. Cada vez que el usuario marca un ítem como comprado, se registra en su tabla `producto_historial` personal.
2. El registro incluye: producto, categoría, cantidad, fecha, lista de origen.
3. Los productos marcados más frecuentemente aparecen primero en las sugerencias.
4. El historial pondera más los productos recientes (últimos 30 días tienen más peso).
5. El usuario puede ver y limpiar su historial de productos desde el perfil.

**📌 Notas técnicas:**
- Tabla `producto_historial`: `user_id`, `producto_nombre`, `categoria`, `cantidad`, `unidad`, `fecha_compra`, `lista_id`.
- El historial es **independiente de las listas**. Agrega consumo de TODAS las listas del usuario.
- Es el combustible de HU-503, HU-504 y HU-505.

---

### HU-503 — Recibir alertas de productos habituales no añadidos 🤖
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** M

**Narrativa:**  
Como usuario, quiero que la app me avise cuando tengo una lista activa y me faltan productos que compro habitualmente, para no olvidar nada antes de ir al súper.

**Criterios de aceptación:**
1. Cuando el usuario tiene una lista activa con al menos 3 ítems, el sistema analiza su historial de consumo.
2. Si detecta productos habituales no presentes en ninguna lista activa, muestra sugerencia no intrusiva.
3. La sugerencia se muestra como banner en el dashboard: 'Sueles comprar leche cada semana. ¿La añadimos?'
4. El usuario puede aceptar, ignorar o silenciar ese producto.
5. Solo se sugieren productos con frecuencia mínima de 3 veces en el historial.
6. No se muestran más de 3 sugerencias simultáneas.

**📌 Notas técnicas:**
- Lógica sin IA para frecuencia simple: si producto comprado >3 veces y última compra hace >X días según frecuencia media, sugerir.
- Claude API solo para patrones ambiguos.
- STITCH: Proyecto 'Superia' → pantalla 'Reposición'. Leer via MCP antes de generar `ReplenishmentBanner.jsx`.

---

### HU-504 — Sugerencias de ingredientes complementarios 🤖
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** L

**Narrativa:**  
Como usuario, quiero que la app me sugiera productos que habitualmente compro juntos, para no llegar a casa sin un ingrediente clave.

**Criterios de aceptación:**
1. Cuando el usuario añade un producto, el sistema analiza qué productos suele comprar junto a ese.
2. Si detecta un complementario habitual no presente en la lista, sugiere añadirlo.
3. Ejemplo: añade 'pasta' → sugiere 'tomate frito' si los compra juntos el 80% de las veces.
4. La sugerencia aparece como chip debajo del ítem recién añadido.
5. El usuario puede aceptar o ignorar con un toque.
6. Solo se activa si hay al menos 5 listas completadas en el historial.

**📌 Notas técnicas:**
- Co-ocurrencia calculada en MySQL: pares de productos que aparecen juntos en >60% de listas del usuario.
- Claude API para inferir complementarios cuando el historial propio es insuficiente (usuario nuevo).
- Prompt Claude: "El usuario añade X a su lista de compra española. ¿Qué 2 productos complementarios suele necesitarse junto a X? Responde en JSON."

---

### HU-505 — Resumen semanal de reposición inteligente 🤖
**Actor:** Usuario registrado | **Prioridad:** Baja | **Estimación:** M

**Narrativa:**  
Como usuario habitual, quiero recibir una sugerencia semanal con lo que probablemente necesito comprar esta semana, para planificar mi compra sin esfuerzo.

**Criterios de aceptación:**
1. Una vez a la semana (lunes por la mañana), el sistema genera un resumen personalizado.
2. El resumen se muestra como notificación en la app y opcionalmente por email.
3. Incluye: productos con reposición pendiente + sugerencias basadas en época del año.
4. El usuario puede convertir el resumen en una lista nueva con un toque.
5. El usuario puede desactivar esta funcionalidad desde ajustes.
6. Solo se activa si el usuario tiene al menos 3 semanas de historial.

**📌 Notas técnicas:**
- Resumen generado con Claude API una vez/semana por usuario activo.
- Prompt incluye: historial consumo últimas 4 semanas + lista actual si existe + mes del año para estacionalidad.
- Job semanal con **APScheduler** dentro de FastAPI. No usar Celery.
- STITCH: Proyecto 'Superia' → pantalla 'Resumen semanal'. Leer via MCP antes de generar `WeeklySummaryPage.jsx`.

---

## Épica 6 — IA: Generación de Lista por Contexto
> Crear listas completas a partir de una descripción en lenguaje natural.

---

### HU-601 — Generar lista automática por contexto 🤖
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** L

**Narrativa:**  
Como usuario, quiero describir en lenguaje natural qué necesito y que la IA genere la lista de compra completa, para ahorrar tiempo planificando.

**Criterios de aceptación:**
1. Hay una opción 'Generar con IA' al crear una nueva lista.
2. El usuario escribe una descripción libre: 'Cena de cumpleaños para 8 personas', 'Semana de dieta mediterránea'.
3. La IA genera una lista de ítems con nombre, cantidad, unidad y categoría.
4. El usuario ve los ítems generados en una pantalla de previsualización antes de confirmar.
5. Puede aceptar todos, eliminar ítems individuales o editar cantidades antes de añadir.
6. Al confirmar, los ítems se añaden a una lista nueva o a una existente.
7. El proceso completo tarda menos de 10 segundos.

**📌 Notas técnicas:**
- Llamada a Claude API con prompt estructurado. Respuesta en JSON con array de ítems.
- Rate limit: máximo 5 generaciones por día en plan gratuito.
- Si Claude no devuelve JSON válido, reintentar una vez antes de mostrar error amigable.
- STITCH: Proyecto 'Superia' → pantalla 'Generar con IA'. Leer via MCP antes de generar `AIGeneratePage.jsx`.

---

### HU-602 — Ajustar lista generada por número de personas 🤖
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** M

**Narrativa:**  
Como usuario, quiero que la IA ajuste las cantidades de la lista generada según el número de personas, para que la compra sea proporcional.

**Criterios de aceptación:**
1. Al generar una lista, hay un campo opcional 'Número de personas' (por defecto: 2).
2. La IA ajusta las cantidades de todos los ítems proporcionalmente.
3. El usuario puede cambiar el número y regenerar las cantidades sin volver a describir el contexto.
4. Las cantidades se muestran redondeadas a unidades comerciales lógicas.

---

## Épica 7 — IA: Estimación de Precio Total
> Estimar el coste de la compra basándose en múltiples fuentes de datos.

---

### HU-701 — Ver estimación de precio total de una lista 🤖
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** L

**Narrativa:**  
Como usuario, quiero ver una estimación del coste total de mi lista de compra, para planificar mi presupuesto antes de ir al supermercado.

**Criterios de aceptación:**
1. En el detalle de una lista, hay un indicador de precio estimado total visible.
2. El precio se muestra con rango: 'Estimación: 35€ — 45€'.
3. El precio se recalcula automáticamente al añadir o eliminar ítems.
4. Hay un indicador claro de que es una estimación, no un precio exacto.
5. El desglose por ítem es accesible pulsando sobre el total.
6. Cada ítem muestra su precio estimado individual en el desglose.

**📌 Notas técnicas — Arquitectura de precios por 4 capas:**
- **Capa 1:** Historial personal del usuario — precios reales confirmados. Fuente más precisa. Se consulta siempre primero.
- **Capa 2:** Dataset estático de precios medios — tabla generada con Claude UNA VEZ y actualizada mensualmente. Ej: 'leche entera 1L: 0.85€ — 1.20€'. Sin coste en tiempo real.
- **Capa 3:** Open Food Facts API — fuente pública gratuita para identificar productos y categorías.
- **Capa 4 (fallback):** Claude API en tiempo real — solo para productos muy específicos no encontrados en capas anteriores. Rate limit: 10 llamadas/día plan gratuito.

---

### HU-702 — Confirmar precio real tras la compra
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** M

**Narrativa:**  
Como usuario que acaba de hacer la compra, quiero confirmar el precio real que pagué, para que la app mejore sus estimaciones futuras.

**Criterios de aceptación:**
1. Al completar una lista, se muestra opción '¿Cuánto pagaste?'
2. El usuario puede introducir el precio total real o desglosado por ítem.
3. Los precios confirmados alimentan la Capa 1 de la arquitectura de precios.
4. Esta acción es completamente opcional, no bloquea el flujo.

---

## Épica 8 — IA: Detección de Duplicados y Agrupación
> Evitar duplicados y organizar ítems semánticamente.

---

### HU-801 — Detectar ítems duplicados o similares 🤖
**Actor:** Usuario registrado | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como usuario, quiero que la app me avise cuando añado un producto que ya tengo en la lista, para evitar duplicados accidentales.

**Criterios de aceptación:**
1. Al añadir un ítem, el sistema compara semánticamente con los ya existentes en la lista.
2. Si detecta similitud alta, muestra aviso no bloqueante.
3. El aviso propone: 'Ya tienes Tomates en la lista. ¿Quieres añadir otro o aumentar la cantidad?'
4. El usuario puede ignorar el aviso o incrementar la cantidad del existente.
5. La detección es semántica, no solo textual.
6. El aviso aparece en menos de 1 segundo.

**📌 Notas técnicas:**
- Capa 1: comparación local por similitud de texto (Levenshtein). >90% = duplicado obvio, sin llamar a Claude.
- Capa 2: Claude API solo si similitud entre 50%-90%.

---

### HU-802 — Agrupar ítems por categoría automáticamente 🤖
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** M

**Narrativa:**  
Como usuario, quiero que los ítems de mi lista estén agrupados por categoría de supermercado, para hacer la compra de forma más eficiente.

**Criterios de aceptación:**
1. Al añadir un ítem sin categoría, la IA infiere la categoría automáticamente.
2. Categorías: Frutas y verduras, Carnes y pescados, Lácteos y huevos, Panadería, Bebidas, Congelados, Limpieza, Higiene personal, Conservas, Otros.
3. La categoría inferida se muestra al usuario y puede corregirse manualmente.
4. Los ítems se agrupan visualmente por categoría en la vista de lista.
5. El usuario puede reordenar el orden de las categorías.

---

## Épica 9 — Historial y Estadísticas
> Consultar compras pasadas y entender patrones de consumo.

---

### HU-901 — Ver historial de listas completadas
**Actor:** Usuario registrado | **Prioridad:** Media | **Estimación:** M

**Narrativa:**  
Como usuario, quiero ver el historial de mis listas completadas anteriores, para consultar qué compré en el pasado y reutilizar listas.

**Criterios de aceptación:**
1. El historial muestra listas completadas ordenadas por fecha (más reciente primero).
2. Cada entrada muestra: nombre de lista, fecha, número de ítems y precio total si se confirmó.
3. El usuario puede ver el detalle completo de cualquier lista pasada.
4. Puede duplicar una lista pasada para usarla como base de una nueva.
5. El historial se conserva indefinidamente mientras la cuenta esté activa.

**📌 Notas técnicas:**
- STITCH: Proyecto 'Superia' → pantalla 'Historial'. Leer via MCP antes de generar `HistoryPage.jsx`.

---

### HU-902 — Ver estadísticas de gasto y consumo
**Actor:** Usuario registrado | **Prioridad:** Baja | **Estimación:** L

**Narrativa:**  
Como usuario, quiero ver estadísticas de mis compras, para entender mis hábitos de consumo.

**Criterios de aceptación:**
1. Hay una sección de estadísticas accesible desde el menú principal.
2. Se muestra gasto mensual estimado en gráfico de barras (últimos 6 meses).
3. Se muestran las 5 categorías más compradas en gráfico de tarta.
4. Se listan los 10 productos más frecuentes del usuario.
5. Las estadísticas solo se muestran si hay al menos 3 listas completadas.
6. Se aclara que los importes son estimaciones salvo confirmación real.

---

## Épica 10 — Administración SaaS
> Panel de control para monitorizar usuarios, uso de IA y salud del sistema.

---

### HU-1001 — Ver panel de administración general
**Actor:** Administrador | **Prioridad:** Alta | **Estimación:** L

**Narrativa:**  
Como administrador, quiero ver métricas clave del sistema en un panel centralizado, para monitorizar el estado y crecimiento de Superia.

**Criterios de aceptación:**
1. El panel muestra: usuarios totales, usuarios activos últimos 7 días, listas creadas hoy, listas creadas total.
2. Muestra consumo de IA: llamadas Claude API hoy, este mes y coste estimado.
3. Muestra usuarios en lista de espera pendientes de invitar.
4. Las métricas se actualizan al recargar la página.
5. El panel es accesible solo para usuarios con rol 'superadmin'.

**📌 Notas técnicas:**
- STITCH: Proyecto 'Superia' → pantalla 'Admin'. Leer via MCP antes de generar `AdminDashboard.jsx`.

---

### HU-1002 — Gestionar usuarios desde administración
**Actor:** Administrador | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como administrador, quiero ver y gestionar todos los usuarios registrados, para mantener control sobre el acceso a la plataforma.

**Criterios de aceptación:**
1. Tabla de usuarios con: nombre, email, fecha de registro, último acceso, número de listas, consumo IA.
2. Se puede buscar por email o nombre.
3. Se puede desactivar/activar una cuenta de usuario.
4. Se puede ver el detalle de consumo IA de cada usuario.
5. Se puede cambiar el plan de un usuario (gratuito / premium).

---

### HU-1003 — Monitorizar consumo de IA por usuario
**Actor:** Administrador | **Prioridad:** Alta | **Estimación:** M

**Narrativa:**  
Como administrador, quiero ver el consumo de Claude API desglosado por usuario y funcionalidad, para detectar abusos y controlar costes.

**Criterios de aceptación:**
1. Tabla de consumo: usuario, funcionalidad IA usada, número de llamadas, tokens consumidos, coste estimado.
2. Se puede filtrar por rango de fechas.
3. Se alerta automáticamente si un usuario supera el 80% de su límite diario.
4. Si un usuario supera el límite, las llamadas IA se bloquean hasta el día siguiente con mensaje amigable.
5. El administrador puede ajustar el límite diario de llamadas IA de forma individual.

---

### HU-1004 — Ver logs de errores del sistema
**Actor:** Administrador | **Prioridad:** Media | **Estimación:** M

**Narrativa:**  
Como administrador, quiero ver los errores del sistema registrados, para detectar y solucionar problemas antes de que afecten a los usuarios.

**Criterios de aceptación:**
1. Se muestran logs de errores de las últimas 24 horas, 7 días y 30 días.
2. Cada log incluye: timestamp, tipo de error, endpoint afectado, usuario si aplica, mensaje.
3. Se puede filtrar por severidad (warning, error, critical).
4. Los errores críticos generan una notificación por email al administrador.
5. Los logs no contienen datos personales sensibles.

---

## Apéndice — Resumen de Historias de Usuario

| Épica | ID | Título | Prioridad | Est. | IA |
|-------|----|--------|-----------|------|----|
| 0 | HU-001 | Ver landing page | Alta | M | |
| 0 | HU-002 | Registrarse en lista de espera | Alta | S | |
| 0 | HU-003 | Política de privacidad | Alta | S | |
| 0 | HU-004 | Gestionar lista de espera (admin) | Alta | M | |
| 1 | HU-101 | Registrarse con invitación | Alta | M | |
| 1 | HU-102 | Iniciar sesión | Alta | S | |
| 1 | HU-103 | Recuperar contraseña | Alta | S | |
| 1 | HU-104 | Ver y editar perfil | Media | S | |
| 1 | HU-105 | Eliminar cuenta y datos (RGPD) | Alta | M | |
| 2 | HU-201 | Ver dashboard con listas | Alta | M | |
| 2 | HU-202 | Crear nueva lista | Alta | S | |
| 2 | HU-203 | Editar nombre y categoría | Media | XS | |
| 2 | HU-204 | Archivar y restaurar lista | Media | S | |
| 2 | HU-205 | Eliminar lista | Media | XS | |
| 3 | HU-301 | Ver detalle de lista con ítems | Alta | M | |
| 3 | HU-302 | Añadir ítem a una lista | Alta | M | |
| 3 | HU-303 | Marcar ítem como comprado | Alta | S | |
| 3 | HU-304 | Editar ítem | Media | S | |
| 3 | HU-305 | Eliminar ítem | Media | XS | |
| 3 | HU-306 | Limpiar ítems comprados | Media | XS | |
| 4 | HU-401 | Compartir lista por enlace | Alta | M | |
| 4 | HU-402 | Acceder a lista compartida sin cuenta | Alta | M | |
| 4 | HU-403 | Ver colaboradores activos | Media | S | |
| 5 | HU-501 | Sugerencias al escribir (catálogo precargado) | Alta | L | 🤖 |
| 5 | HU-502 | Aprendizaje de hábitos + tabla historial | Media | M | 🤖 |
| 5 | HU-503 | Alertas de productos habituales no añadidos | Media | M | 🤖 |
| 5 | HU-504 | Ingredientes complementarios por historial | Media | L | 🤖 |
| 5 | HU-505 | Resumen semanal de reposición inteligente | Baja | M | 🤖 |
| 6 | HU-601 | Generar lista por contexto | Alta | L | 🤖 |
| 6 | HU-602 | Ajustar cantidades por personas | Media | M | 🤖 |
| 7 | HU-701 | Estimación de precio (4 capas) | Media | L | 🤖 |
| 7 | HU-702 | Confirmar precio real | Media | M | |
| 8 | HU-801 | Detectar duplicados | Alta | M | 🤖 |
| 8 | HU-802 | Agrupar por categoría | Media | M | 🤖 |
| 9 | HU-901 | Ver historial de listas | Media | M | |
| 9 | HU-902 | Estadísticas de gasto | Baja | L | |
| 10 | HU-1001 | Panel de administración | Alta | L | |
| 10 | HU-1002 | Gestionar usuarios | Alta | M | |
| 10 | HU-1003 | Monitorizar consumo IA | Alta | M | |
| 10 | HU-1004 | Ver logs de errores | Media | M | |

---

*Superia HU v3.0 · Sofia4Builders · TX APPS · Abril 2026*
