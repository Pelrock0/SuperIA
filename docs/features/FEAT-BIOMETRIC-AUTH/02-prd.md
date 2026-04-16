# PRD: FEAT-BIOMETRIC-AUTH - Acceso biométrico mediante WebAuthn / Passkeys

## Business Objective

Permitir que el usuario acceda a Superia usando biometría del dispositivo (Touch ID, Face ID, Windows Hello, huella Android) en lugar de email + password. Esto reduce fricción de login en accesos repetidos desde dispositivos confiables, refuerza la seguridad frente a phishing y credential stuffing, y posiciona a Superia al nivel de las apps modernas que esperan los usuarios.

**Valor**: (1) Mayor retención por reducción de fricción en login, (2) menor superficie de ataque (passkeys son resistentes a phishing por diseño), (3) menor carga sobre el flujo de password reset.

## Problem Statement

Actualmente el único acceso a Superia es email + password. En contextos móviles (mobile-first), escribir el password en cada sesión es lento y los usuarios tienden a usar passwords débiles o reusados. Tampoco aprovechamos la biometría disponible en el 100% de los smartphones modernos y la mayoría de laptops, lo cual es una expectativa estándar para apps consumer en 2026.

## Scope

### In Scope

1. **Registro de credencial WebAuthn** desde la página de perfil (usuario ya autenticado con email+password puede añadir passkeys)
2. **Login con WebAuthn** desde la página de login con dos modalidades:
   - **Con email previo** (`allowCredentials` filtrado): usuario escribe email → click "Entrar con biometría" → autenticación biométrica
   - **Sin email previo** (discoverable credentials / passkey): click "Entrar con passkey" → el navegador muestra las passkeys disponibles → autenticación biométrica
3. **Multi-dispositivo**: un usuario puede registrar varias credenciales (ej: iPhone, laptop, tablet)
4. **Nombrar credenciales**: usuario asigna un nombre legible a cada passkey ("iPhone 14", "Laptop trabajo"). Default = parseo del User-Agent
5. **Revocación de credenciales**: usuario puede eliminar credenciales individuales desde el perfil
6. **Password reset por email revoca passkeys** (Opción C confirmada): cuando un usuario hace password reset vía email recovery, todas sus credenciales WebAuthn se revocan automáticamente. Cambios de password voluntarios desde el perfil (con password actual) NO revocan passkeys
7. **Feature flag**: `webauthn.enabled` en config controla si la funcionalidad está visible. Permite shipping detrás de flag y rollout gradual
8. **Email + password sigue funcionando como fallback principal** sin cambios

### Out of Scope

- App móvil nativa (esto es web)
- Eliminar el login con email + password (sigue siendo el camino principal y el único en dispositivos sin authenticator)
- 2FA / MFA combinado (ej. password + biometría). WebAuthn AQUÍ es alternativa al password, no segundo factor
- Recuperación de cuenta sin email (si pierdes email + dispositivos, queda fuera de scope; soporte manual)
- Hardware security keys (YubiKey, etc.) explícitamente promocionados — funcionarán técnicamente porque WebAuthn los soporta, pero la UI dice "biometría", no "llave de seguridad"
- Compartir passkeys entre dispositivos del mismo usuario vía iCloud Keychain / Google Password Manager (esto lo gestiona el OS automáticamente, sin código de Superia)
- Sincronización custom de passkeys entre dispositivos
- Passkey migration / cross-device authentication (CTAP2 hybrid transport) — explicitly: si funciona out-of-the-box por el navegador, bien; no construimos nada custom

## Acceptance Criteria

### AC-1: Registrar primera credencial desde perfil

- **Given**: un usuario autenticado con email+password está en `/app/profile` y tiene `webauthn.enabled=true`
- **When**: pulsa "Añadir dispositivo biométrico", el navegador muestra el prompt biométrico, y el usuario completa la verificación con éxito
- **Then**: se crea un registro en `webauthn_credentials` con `user_id`, `credential_id`, `public_key`, `sign_count=0`, `transports`, `aaguid`, `name` (default = parseo de User-Agent), `created_at`. La UI muestra el nuevo dispositivo en la lista

### AC-2: Registro falla por cancelación o timeout

- **Given**: el usuario inicia el registro de una credencial
- **When**: cancela el prompt biométrico, hay timeout, o el authenticator no soporta los algoritmos pedidos
- **Then**: la UI muestra mensaje de error claro ("Registro cancelado", "Tu dispositivo no soporta biometría", "Tiempo agotado"). No se crea ningún registro en DB. El estado anterior se mantiene

### AC-3: Login con email + biometría (modo non-discoverable)

- **Given**: un usuario tiene al menos una credencial WebAuthn registrada y está en `/login`
- **When**: escribe su email y pulsa "Entrar con biometría", el navegador muestra el prompt biométrico, y el usuario completa la verificación
- **Then**: el backend valida assertion (challenge match, signature válida, signCount > previous), incrementa `sign_count`, actualiza `last_used_at`, y emite JWT. La sesión se inicia exactamente como con login email+password

### AC-4: Login con passkey sin email (modo discoverable)

- **Given**: un usuario tiene al menos una credencial registrada como `residentKey` y está en `/login`
- **When**: pulsa "Entrar con passkey" sin escribir email, el navegador muestra las passkeys disponibles para `superlistia.com`, y el usuario selecciona una y completa la biometría
- **Then**: el backend identifica el usuario por `userHandle` en la assertion, valida la firma, emite JWT. Login exitoso sin haber escrito email

### AC-5: Login WebAuthn falla — usuario puede usar password como fallback

- **Given**: el usuario intentó login con biometría y falló (cancelación, signature inválida, credencial no encontrada)
- **When**: el flujo WebAuthn termina con error
- **Then**: la UI muestra mensaje de error y mantiene el campo de password visible para que el usuario pueda hacer login tradicional. El error no bloquea ni "lockea" al usuario

### AC-6: Multi-dispositivo — usuario registra varias credenciales

- **Given**: un usuario ya tiene una credencial registrada (ej. "iPhone")
- **When**: añade otra credencial desde un dispositivo distinto (ej. "Laptop trabajo")
- **Then**: ambas credenciales coexisten en la lista del perfil. El usuario puede usar cualquiera para login. El nombre default refleja el User-Agent parseado del nuevo dispositivo

### AC-7: Renombrar credencial

- **Given**: el usuario tiene una credencial registrada con nombre default ("iPhone — Safari")
- **When**: pulsa el icono de editar en la lista de credenciales y cambia el nombre a "Mi iPhone personal"
- **Then**: el nombre se actualiza en DB. La lista refleja el cambio. Validación: 1-50 caracteres, sin HTML

### AC-8: Revocar credencial

- **Given**: el usuario tiene 2 credenciales registradas
- **When**: pulsa "Revocar" en una de ellas y confirma en el dialog
- **Then**: la credencial se elimina de DB. La lista la quita inmediatamente. Si era la última, la sección muestra empty state "No tienes dispositivos biométricos registrados"

### AC-9: Password reset por email revoca todas las passkeys (Opción C)

- **Given**: un usuario tiene 3 credenciales WebAuthn registradas
- **When**: dispara el flujo "He olvidado mi contraseña" → recibe email → completa password reset
- **Then**: todas las credenciales WebAuthn del usuario se eliminan de DB. La próxima vez que entre, debe registrar de nuevo sus dispositivos. La UI del flujo de reset informa de esto: "Por seguridad, tus dispositivos biométricos se han desvinculado"

### AC-10: Cambio de password voluntario (desde profile, con password actual) NO revoca passkeys

- **Given**: un usuario autenticado con 2 credenciales WebAuthn registradas
- **When**: cambia su password desde `/app/profile` proporcionando el password actual
- **Then**: las credenciales WebAuthn se mantienen intactas. El usuario puede seguir usando biometría tras el cambio

### AC-11: Feature flag desactivado oculta toda la UI

- **Given**: el config `webauthn.enabled=false`
- **When**: el usuario navega al login o al profile
- **Then**: no se muestra ningún botón ni sección relacionada con biometría. Los endpoints WebAuthn devuelven 404 (no 403 — para no revelar la existencia)

### AC-12: Replay attack rechazado

- **Given**: un atacante intercepta una assertion válida del usuario
- **When**: el atacante intenta reusar la misma assertion
- **Then**: el backend la rechaza (401) por challenge no match (cada challenge es single-use). Se loguea el intento con nivel `warning`

### AC-13: Credential cloning detectado por signCount

- **Given**: una credencial registrada con `sign_count=5` que se ha clonado
- **When**: llega una assertion con `sign_count <= 5` (la copia se queda atrás)
- **Then**: el backend rechaza (401) y loguea el evento como `error` "possible credential cloning detected for credential_id=X". La credencial NO se elimina automáticamente (decisión: alertar pero no romper, evitar self-DoS)

### AC-14: rpId mismatch rechazado

- **Given**: una assertion firmada para un `rpId` distinto al configurado (ej. el atacante crea un sitio fake con rpId malicioso)
- **When**: la assertion llega al backend
- **Then**: la verificación falla y devuelve 401. Loguea como `warning` con el rpId recibido vs esperado

### AC-15: Browser sin soporte WebAuthn

- **Given**: un usuario en un navegador antiguo sin `navigator.credentials`
- **When**: navega al login con `webauthn.enabled=true`
- **Then**: el botón "Entrar con biometría" no se muestra (detección capability). El login email+password funciona normal. Sin errores en consola

## UX Decision

- **UX Designer Required**: NO
- **UX Artifacts**: N/A
- **Justificación**: La feature añade UI siguiendo patrones ya existentes en la app (botones, listas, dialogs de confirmación). No hay redesign visual ni nueva design language. El sistema de diseño actual cubre lo necesario.

### UX Notes (inline)

**Página de Login (`/login`)**:
- Bajo el formulario actual, añadir un divider con texto "o" y dos botones secundarios:
  - "Entrar con biometría" (icono fingerprint, requiere haber escrito email primero — disabled hasta que email sea válido)
  - "Entrar con passkey" (icono key, sin email previo)
- Si el navegador no soporta WebAuthn: ambos botones ocultos
- Si feature flag desactivado: ambos botones ocultos
- Estados de loading: botón muestra spinner mientras el navegador procesa el prompt biométrico
- Errores: alert inline rojo bajo los botones

**Página de Profile (`/app/profile`)**:
- Nueva sección "Dispositivos biométricos" después de la sección de seguridad/password
- Si el usuario tiene 0 credenciales: empty state con CTA "Añadir mi primer dispositivo"
- Si tiene N credenciales: lista con cada credencial mostrando:
  - Icono según `transports` (móvil / laptop / llave externa)
  - Nombre editable inline
  - Última vez usado ("hace 3 días")
  - Botón "Revocar" (con confirmación)
- Botón "Añadir otro dispositivo" al pie de la lista

**S5-UX será requerido** para validar visualmente con MCP chrome-devtools en login + profile.

## Risks & Mitigations

| Risk | Type | Mitigation |
|------|------|------------|
| WebAuthn implementation bugs → bypass de auth | Security (Critical) | Usar librería battle-tested (`web-auth/webauthn-lib`), no implementar criptografía manualmente. S5-SEC review obligatorio. Tests de assertion verification con vectors conocidos |
| Replay attacks por challenge no único | Security (High) | Cada challenge se genera con `random_bytes(32)` y se almacena en sesión / cache con TTL 5 min, single-use. Cubierto por AC-12 |
| Credential cloning | Security (Medium) | Validar `signCount` estrictamente creciente (AC-13). Loguear pero no romper para evitar self-DoS |
| rpId mal configurado en prod → passkeys de dev no funcionan en prod (y viceversa) | Operational | `rpId` por env var. Documentar en `.env.example`. Tests del happy path en cada entorno antes de release |
| Lockout: usuario pierde dispositivo Y password | Operational | Mantener password reset por email funcionando siempre. AC-9 lo refuerza |
| Account enumeration vía endpoint de login WebAuthn | Security (Low) | El endpoint de begin-authentication para email previo NO debe revelar si el email existe — devuelve `allowCredentials=[]` si no hay user, mismo timing |
| Discoverable credentials filtran info entre cuentas en mismo dispositivo | Privacy | Es comportamiento del browser/OS, no del backend. Documentar en política |
| Shipping con feature flag activado por error | Operational | Default `webauthn.enabled=false` en config. Activación explícita por env var en cada entorno |
| Tests no pueden ejercitar el sensor biométrico real | Technical | Mockear el flujo WebAuthn en frontend con Cypress/Playwright. Backend testeado con assertions sintéticas firmadas |

## Assumptions

- Producción servirá Superia en `https://superlistia.com` (HTTPS válido). WebAuthn requiere HTTPS — no aplica en localhost por excepción del estándar
- Los usuarios objetivo tienen dispositivos modernos con biometría (iPhone con Touch/Face ID, Android moderno con huella, laptops con Windows Hello / Touch ID Mac). Usuarios sin biometría siguen usando password sin pérdida funcional
- El backend usa `tymon/jwt-auth` actual; se emite JWT igual que en login email+password tras assertion exitosa
- Sesiones de challenge se almacenan en cache (Laravel default cache driver, ya configurado)
- El nombre por defecto de credencial será un parseo simple del User-Agent (ej. "Safari — iPhone"). No se hace UA-fingerprinting agresivo, solo browser + plataforma básico

## Open Questions

Ninguna. Todas las decisiones están resueltas:
- rpId: `superlistia.com` (prod) / `superia.com.local` (dev)
- User Verification: `preferred`
- Attestation: `none`
- Naming: usuario nombra (default = UA parseado)
- Recovery: password reset por email (existente) + revocación passkeys solo en este caso (Opción C)
- Discoverable: ambos modos soportados
- Feature flag: `webauthn.enabled`

## Approval

- [ ] PRD approved
