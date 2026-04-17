# Scope Analysis: FEAT-BIOMETRIC-UX

## Feature Request

Rediseñar el flujo de alta y uso de biometría (WebAuthn) para que sea el camino natural tras el primer login, no una tarea manual relegada a ajustes.

**Contexto actual (implementado en `FEAT-BIOMETRIC-AUTH`)**:
- Login por email+password funciona.
- El registro de credencial biométrica sólo ocurre si el usuario entra manualmente a su perfil/ajustes.
- En `LoginPage.jsx` ya existe botón "Entrar con biometría" pero pide email antes (LoginPage.jsx:208) y otro botón passkey sin email (LoginPage.jsx:219) — jerarquía visual invertida respecto al flujo óptimo.
- No hay prompt post-login que sugiera activar biometría en el dispositivo.

**Propuesta acordada con el usuario (conversación previa)**:
1. **Post-login / post-registro**: si `navigator.credentials` soportado y no hay credencial registrada en este dispositivo, mostrar modal "¿Activar biometría en este dispositivo?" con botones *Sí* / *Ahora no*.
2. **Política de re-prompt** (combinación D+C del análisis previo):
   - Si el usuario declina ("Ahora no"): NO volver a mostrar automáticamente el prompt.
   - Soft re-prompt tras ~30 días o al detectar dispositivo nuevo.
   - Botón manual siempre disponible en ajustes (ya existe).
3. **Login page**: promover el botón "Entrar con biometría" (passkey discovery, sin email) como acción principal; relegar email+biometría a "otras opciones".

## Classification

| Attribute | Value |
|-----------|-------|
| Complexity | **MEDIUM** |
| Estimated Effort | 10-16 horas |
| Confidence | Medium |

## Justification

Cumple criterios MEDIUM:

1. **UI/UX redesign**: cambio de flujo visible en al menos 3 pantallas (Login, post-login/register modal nuevo, Profile/Settings).
2. **Multiple UI components affected**: `LoginPage.jsx`, `RegisterPage.jsx`, `DashboardPage.jsx` (host del modal post-login), componente nuevo `BiometricOptInModal`, y posible ajuste en `ProfilePage`.
3. **Business logic modificada**: política de re-prompt (30 días / dispositivo nuevo / declinado-permanente).
4. **Validación no trivial**: detectar "credencial ya existe en este dispositivo" sin falsos positivos cuando el navegador no expone listado.

NO es HIGH porque:
- Reutiliza infraestructura WebAuthn ya entregada (endpoints, librería, modelo, service).
- No introduce nuevos vectores de ataque: el registro sigue los mismos endpoints autenticados existentes.
- No hay migraciones destructivas (a decidir si `user_preferences` necesita columnas nuevas).
- Reversible y con feature flag ya disponible (`webauthn.enabled`).

## Risk Assessment

| Risk Type | Level | Description |
|-----------|-------|-------------|
| Technical | **Medium** | Detectar "este dispositivo ya tiene credencial" es ambiguo en WebAuthn: el navegador no expone `listCredentials()` al JS. Posibles enfoques: (a) marcador en localStorage tras registro exitoso, (b) query al backend "mis credenciales" y matching por `aaguid`+`name`, (c) intentar `get()` con `mediation: 'silent'` — ninguno es perfecto. Decisión pendiente para S3. |
| Data | **Low** | Opcional: columna `webauthn_prompt_declined_at` en `users` (si se persiste server-side) o sólo localStorage (cliente). Sin migraciones destructivas. |
| Security | **Low-Medium** | Sin cambios en el flujo criptográfico. Sin embargo: (a) el modal post-login NO debe ejecutarse sobre sesión no verificada (email no confirmado) para evitar registrar credencial en cuenta fraudulenta, (b) el "ahora no" debe persistirse de forma que no sea trivialmente reseteable por usuario que comparte dispositivo (nice-to-have). |
| Performance | **Low** | Cero impacto. Una query extra opcional al montar modal (listar credenciales del usuario). |
| Operational | **Low** | Feature flag reutilizable (`webauthn.enabled`). Rollback = ocultar modal. Sin cambios en endpoints API. |

## Affected Areas

### Frontend
- `resources/js/pages/LoginPage.jsx` — reordenar jerarquía de botones (passkey-first)
- `resources/js/pages/RegisterPage.jsx` — disparar modal post-registro tras verificación
- `resources/js/pages/DashboardPage.jsx` (o `AppLayout`) — host del modal post-login
- `resources/js/components/auth/BiometricOptInModal.jsx` — componente nuevo
- `resources/js/context/AuthContext.jsx` — flag `shouldPromptBiometric` en el estado post-login
- `resources/js/lib/webauthnApi.js` — helper `hasLocalCredentialMarker()` / `markCredentialRegistered()`
- Tests de componente (`.test.jsx`) para modal y LoginPage

### Backend (opcional, según decisión TBD-1)
- `app/Models/User.php` — campo `webauthn_prompt_declined_at` nullable
- Nueva migración `*_add_webauthn_prompt_declined_at_to_users.php`
- `app/Http/Controllers/Auth/WebauthnController.php` — endpoint `POST /api/auth/webauthn/decline-prompt`
- Feature tests del endpoint

### Sin cambios
- `app/Services/WebauthnService.php` — intacto
- Esquema `webauthn_credentials` — intacto
- `config/webauthn.php` — intacto

## Resolved Decisions (input para S2)

Aceptadas por el usuario en conversación (2026-04-17).

| # | Decision | Value | Notes |
|---|----------|-------|-------|
| 1 | Persistencia "Ahora no" | **localStorage** (`biometric_prompt_declined_at`) | Decisión per-dispositivo. Sin migración, sin endpoint. Sin coherencia multi-dispositivo por diseño. |
| 2 | Detección "credencial en este dispositivo" | **localStorage marker + account check** | Si usuario tiene ≥1 credencial a nivel cuenta AND no existe marker `webauthn_device_registered` en localStorage → dispositivo nuevo → mostrar prompt. Marker se escribe tras registro exitoso. |
| 3 | Prompt tras registro de cuenta nueva | **Sí, tras verificación de email** | No disparar en registro inmediato. Evita vincular credencial a cuenta no verificada. |
| 4 | Trigger de re-prompt | **`(now - declined_at > 30d) OR (localStorage sin marker de dispositivo)`** | OR lógico. "Dispositivo nuevo" reabre prompt incluso si declinó antes en otro navegador. |
| 5 | LoginPage refactor | **Biometría como CTA destacado encima del form email+password** | Sólo si `isWebauthnSupported()` true. No ocultar form email+password. Unificar los dos botones biométricos actuales en uno (passkey discovery primario). |
| 6 | Copy del modal (es-ES) | Título: "¿Activar biometría en este dispositivo?" · Body: "Entra más rápido la próxima vez con Face ID, Touch ID o huella." · Botones: *Activar ahora* / *Ahora no* | Sujeto a ajuste fino en S2/S5-UX. |

## Open Questions

Ninguna. Todas las decisiones de scope están resueltas.

## Recommendation

- [ ] Proceed directly (LOW → STEP 1b)
- [x] **Require PRD (MEDIUM → STEP 2)** — tras cerrar TBDs arriba
- [ ] Escalate to architect

**Justificación**: MEDIUM por afectación multi-componente y política de re-prompt. TBDs cerradas. Listo para PRD formal en S2.

## Transition

- Gate: **S1 PASSED**
- Next Step: STEP 2 (PRD Writing)
