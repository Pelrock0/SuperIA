# PRD: FEAT-EPIC0-LANDING - Landing y Lista de Espera

## Business Objective
Superia necesita una primera presencia pública para validar demanda antes de abrir el producto. La landing page captura interés, la lista de espera recoge leads cualificados, y el panel de admin permite gestionar invitaciones de acceso controlado.

## Problem Statement
Superia no tiene presencia pública. No hay forma de validar demanda ni capturar usuarios interesados antes del lanzamiento. Sin un sistema de invitaciones, no se puede controlar el acceso gradual al producto.

## Scope

### In Scope
- **HU-001**: Landing page estática con propuesta de valor, 3+ características, responsive, sin cookies/analytics
- **HU-002**: Formulario de waitlist (nombre, email obligatorios; pregunta "con quién compras" opcional), email confirmación con posición aproximada, rate limiting 3/IP/hora
- **HU-003**: Sección compromiso de datos visible en landing + página de política de privacidad completa RGPD
- **HU-004**: Panel admin (`/admin`, solo superadmin) con tabla waitlist, exportar CSV, marcar como invitado con envío automático de email con enlace de registro (expira 7 días), contador total

### Out of Scope
- Registro de usuarios (Épica 1)
- Dashboard de usuario (Épica 2)
- Sistema de pagos o planes
- Analytics o tracking de terceros
- SEO avanzado (solo meta tags básicos)
- Internacionalización (solo español)

## Acceptance Criteria

### AC-1: Landing page visible y responsive
- **Given**: Un visitante anónimo accede a `superia.com.local`
- **When**: La página carga
- **Then**: Se muestra el nombre "Superia", tagline "La compra, más inteligente", sección hero sin scroll, al menos 3 características (IA, listas compartidas, historial), y es responsive en móvil/tablet/escritorio. Carga <2s en conexión móvil estándar. Sin cookies de tracking ni scripts de terceros.

### AC-2: Registro en waitlist exitoso
- **Given**: Un visitante en la landing
- **When**: Rellena nombre y email válido y envía el formulario
- **Then**: Se crea el registro en BD, se muestra mensaje de éxito en pantalla, y recibe email de confirmación con posición aproximada (+/- 5).

### AC-3: Validación de email en waitlist
- **Given**: Un visitante envía el formulario
- **When**: El email tiene formato inválido
- **Then**: Se muestra error de validación sin enviar al servidor.

### AC-4: Email duplicado en waitlist
- **Given**: Un visitante envía un email que ya existe en la waitlist
- **When**: El formulario se procesa
- **Then**: Se muestra mensaje amigable genérico (sin revelar que el email ya existe). No se crea duplicado.

### AC-5: Rate limiting en waitlist
- **Given**: Una IP ha enviado 3 solicitudes en la última hora
- **When**: Intenta enviar una 4a solicitud
- **Then**: Se rechaza con mensaje amigable indicando que intente más tarde.

### AC-6: Pregunta opcional en waitlist
- **Given**: El formulario de waitlist
- **When**: El visitante ve las opciones
- **Then**: Puede seleccionar opcionalmente "Con quién sueles compartir la compra": Solo, Pareja, Familia, Compañeros de piso. El campo es opcional.

### AC-7: Compromiso de datos visible
- **Given**: Un visitante en la landing
- **When**: Hace scroll
- **Then**: Ve sección visible (no solo footer) con compromiso: sin cookies tracking, sin venta de datos, sin publicidad. Enlace a política de privacidad completa.

### AC-8: Política de privacidad RGPD
- **Given**: Un visitante pulsa el enlace de política de privacidad
- **When**: Accede a la página
- **Then**: Se muestran: datos recogidos, finalidad, plazo de conservación, derechos del usuario. Footer con enlaces a política y aviso legal.

### AC-9: Admin ve tabla de waitlist
- **Given**: Un usuario con rol superadmin accede a `/admin`
- **When**: Ve la sección de waitlist
- **Then**: Se muestra tabla con: nombre, email, fecha registro, respuesta pregunta opcional. Total de registros visible.

### AC-10: Admin exporta CSV
- **Given**: Un superadmin en el panel de waitlist
- **When**: Pulsa "Exportar CSV"
- **Then**: Se descarga archivo CSV con todos los registros de la waitlist.

### AC-11: Admin invita usuario
- **Given**: Un superadmin selecciona un registro de la waitlist
- **When**: Lo marca como "invitado"
- **Then**: Se envía automáticamente email con enlace de registro único que expira en 7 días. El registro se marca como "invitado" en la tabla.

### AC-12: Enlace de invitación expirado
- **Given**: Un enlace de invitación generado hace más de 7 días
- **When**: El usuario intenta usarlo
- **Then**: Se muestra mensaje claro de enlace expirado.

### AC-13: Acceso admin protegido
- **Given**: Un usuario sin rol superadmin
- **When**: Intenta acceder a `/admin`
- **Then**: Se deniega el acceso (redirect o 403).

## UX Decision
- **UX Designer Required**: YES
- **UX Artifacts**: Pantallas Stitch MCP → Proyecto "Superia"
  - Landing: `LandingPage.jsx` → ruta `/`
  - Landing form: `WaitlistForm.jsx` → sección en `/`
  - Admin waitlist: parte de `AdminDashboard.jsx` → ruta `/admin`
- **Stitch screens**: Landing, Landing (form), Admin

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| Servicio email no configurado | Technical | Configurar SMTP en `.env`. Laravel Mail abstrae el proveedor. |
| Rate limiting bypass por cambio IP | Security | Rate limit por IP es suficiente para fase waitlist. Podría añadirse fingerprinting en futuro si necesario. |
| Posición waitlist scraping | Data | Posición aproximada (+/- 5) evita scraping exacto. |
| Datos personales RGPD | Security | Solo nombre + email. Política de privacidad conforme. Soft delete disponible. |

## Assumptions
- El dominio `superia.com.local` está configurado para desarrollo local
- Laravel Mail está configurado con SMTP funcional
- No se requiere CDN para assets estáticos en fase waitlist
- El rol superadmin se crea manualmente o por seeder

## Open Questions
Ninguna. Requisitos completos en HU v3.

## Transition
- Gate Status: S2 PASSED
- Next Step: STEP 3 - Technical Design
- Required Artifacts for Next Step: 02-prd.md
