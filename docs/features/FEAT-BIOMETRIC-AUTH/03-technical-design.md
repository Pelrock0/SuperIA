# Technical Design: FEAT-BIOMETRIC-AUTH

## Overview

Se integra WebAuthn (FIDO2 / Passkeys) como alternativa al login email+password. El backend expone 4 endpoints (begin/complete registration y begin/complete authentication) que actúan como Relying Party frente al navegador del usuario. Una nueva tabla `webauthn_credentials` almacena las credenciales públicas; JWT sigue siendo el token de sesión emitido tras una assertion exitosa (igual que en login tradicional).

La librería seleccionada es `web-auth/webauthn-lib` — implementación canónica de WebAuthn en PHP, mantenida activamente, usada por Symfony Passkey. Evitamos wrappers de alto nivel (`asbiin/laravel-webauthn`) porque introducen abstracciones que complican la integración con nuestro `AuthService` y `tymon/jwt-auth` existentes.

La feature va detrás del flag `webauthn.enabled` (default `false`). Password + email sigue siendo el flujo principal e inmutable.

## Architecture

### Component Boundaries

| Layer | Responsibilities | Key Classes/Modules |
|-------|-----------------|---------------------|
| Domain | Modelo de credencial, parseo de User-Agent a nombre default | `WebauthnCredential` (nuevo), `UserAgentParser` (nuevo, support class) |
| Services | Orquestación de challenge lifecycle, verificación de assertions, revocación en cascada desde password reset | `WebauthnService` (nuevo), `AuthService` (modificado: nuevo método `loginWithAssertion`), `PasswordResetService` (modificado: revoca credenciales en AC-9) |
| Infrastructure | Almacenamiento temporal de challenges, serialización de `PublicKeyCredentialSource` | `ChallengeStore` (cache Laravel), `CredentialSourceRepository` (nuevo, implementa interface de `web-auth/webauthn-lib`) |
| Controllers/API | 4 endpoints WebAuthn + endpoints CRUD de credenciales | `WebauthnController` (nuevo), `ProfileController` (modificado: añade rutas de credentials) |
| Frontend | Wrapper de `navigator.credentials.*`, UI en Login + Profile, detección de capability | `webauthnApi.js` (nuevo), `LoginPage.jsx` (modificado), `ProfilePage.jsx` (modificado), `WebauthnCredentialsList.jsx` (nuevo), `AuthContext.jsx` (modificado: `loginWithPasskey()`) |

### Data Flow

#### Flujo 1: Registro de credencial (usuario autenticado)

```
ProfilePage → click "Añadir dispositivo biométrico"
  → POST /api/auth/webauthn/register/begin
    → WebauthnController@beginRegistration
      → auth:api middleware (usuario autenticado)
      → WebauthnService::createRegistrationOptions(user)
        → generar challenge random_bytes(32)
        → ChallengeStore::put("webauthn:reg:{user_id}", challenge, ttl=5min)
        → construir PublicKeyCredentialCreationOptions (rp, user, challenge, pubKeyCredParams, excludeCredentials, authenticatorSelection)
      → return JSON options
  → navigator.credentials.create(options) [browser muestra prompt biométrico]
  → POST /api/auth/webauthn/register/complete {name, credential}
    → WebauthnController@completeRegistration
      → WebauthnService::verifyRegistration(user, credential, name)
        → parse AuthenticatorAttestationResponse
        → validar con web-auth/webauthn-lib (challenge match, origin, rpId, attestation=none)
        → ChallengeStore::forget("webauthn:reg:{user_id}")
        → DB::transaction: persistir WebauthnCredential con {user_id, credential_id, public_key, sign_count, transports, aaguid, name}
      → return 201 {id, name, created_at}
```

#### Flujo 2: Login con email (non-discoverable)

```
LoginPage → email + click "Entrar con biometría"
  → POST /api/auth/webauthn/authenticate/begin {email}
    → WebauthnController@beginAuthentication
      → WebauthnService::createAuthenticationOptions(email)
        → buscar user por email; si no existe → devolver allowCredentials=[] (anti-enumeration)
        → challenge = random_bytes(32)
        → ChallengeStore::put("webauthn:auth:{session_id}", {challenge, email}, ttl=5min)
        → construir PublicKeyCredentialRequestOptions (challenge, allowCredentials filtrado por user, userVerification=preferred)
      → return JSON options
  → navigator.credentials.get(options) [browser prompt biométrico]
  → POST /api/auth/webauthn/authenticate/complete {credential}
    → WebauthnController@completeAuthentication
      → WebauthnService::verifyAssertion(credential, sessionId)
        → recuperar challenge de ChallengeStore
        → parse AuthenticatorAssertionResponse
        → validar con web-auth/webauthn-lib (challenge, rpId, signature, signCount)
        → WebauthnCredential::where('credential_id', $credentialId)->first() → obtener user
        → validar signCount > previous (cloning detection → AC-13)
        → update signCount, last_used_at
        → ChallengeStore::forget
      → AuthService::issueTokenForUser(user) (método extraído, existe lógica de JWT)
      → return 200 {token, user}
```

#### Flujo 3: Login discoverable (sin email)

```
LoginPage → click "Entrar con passkey"
  → POST /api/auth/webauthn/authenticate/begin {} (sin email)
    → WebauthnController@beginAuthentication (email opcional)
      → WebauthnService::createAuthenticationOptions(null)
        → allowCredentials = [] (browser muestra todas las del dominio)
        → resto idéntico al flujo 2
      → return options
  → navigator.credentials.get(options) [browser muestra picker de passkeys para superlistia.com]
  → POST /api/auth/webauthn/authenticate/complete {credential}
    → user se identifica por userHandle en response.userHandle
    → resto idéntico
```

#### Flujo 4: Revocación en cascada desde password reset (AC-9)

```
PasswordResetController@resetPassword
  → PasswordResetService::reset(token, newPassword)
    → DB::transaction
      → validar token
      → User::update(password)
      → User::increment jwt_version (invalidación global de sesiones — ya existe)
      → WebauthnService::revokeAllForUser(user) (nuevo)
        → WebauthnCredential::where('user_id', user.id)->delete()
    → fin transaction
```

#### Flujo 5: Cambio de password voluntario (AC-10 — NO revoca)

```
ProfileController@changePassword
  → existing flow (valida password actual, actualiza, incrementa jwt_version)
  → NO llama a WebauthnService::revokeAllForUser (diferencia clave vs flujo 4)
```

### Transaction Boundaries

- **Registro**: Transacción en `WebauthnService::verifyRegistration` — INSERT de la credencial. Rollback si persistencia falla (ej. violación de unique constraint `credential_id`).
- **Autenticación**: Sin transacción explícita; UPDATE de `signCount` y `last_used_at` es atomic por columna. La operación completa es idempotente por challenge single-use.
- **Password reset**: Extiende la transacción existente de `PasswordResetService::reset` para incluir DELETE de credenciales. Rollback conjunto si cualquier paso falla.

## Data Model

### New Table: `webauthn_credentials`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| user_id | BIGINT UNSIGNED | FK → users.id, ON DELETE CASCADE |
| credential_id | VARBINARY(1024) | UNIQUE, NOT NULL — binary ID del authenticator |
| public_key | TEXT | NOT NULL — COSE public key serializado (base64) |
| sign_count | BIGINT UNSIGNED | NOT NULL, DEFAULT 0 |
| transports | JSON | nullable — array: ["usb", "nfc", "internal", "hybrid"] |
| aaguid | CHAR(36) | nullable — identificador del authenticator model |
| attestation_type | VARCHAR(20) | NOT NULL, DEFAULT 'none' |
| name | VARCHAR(50) | NOT NULL |
| last_used_at | TIMESTAMP | nullable |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

**Indexes:**
- UNIQUE(credential_id) — base para lookup en authenticate
- INDEX(user_id) — para listar credenciales del usuario y revocación en cascada

### New Model: `WebauthnCredential`

```php
class WebauthnCredential extends Model {
    protected $fillable = [
        'user_id', 'credential_id', 'public_key', 'sign_count',
        'transports', 'aaguid', 'attestation_type', 'name', 'last_used_at',
    ];
    protected $casts = [
        'transports' => 'array',
        'last_used_at' => 'datetime',
        'sign_count' => 'integer',
    ];

    public function user(): BelongsTo;
    public function toPublicKeyCredentialSource(): PublicKeyCredentialSource; // adapter
    public static function fromPublicKeyCredentialSource(PublicKeyCredentialSource $source, User $user, string $name): self;
}
```

### Modified Models

**User** — nueva relación:
```php
public function webauthnCredentials(): HasMany {
    return $this->hasMany(WebauthnCredential::class);
}
```

### API Contract

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/auth/webauthn/register/begin` | POST | JWT required | Iniciar registro: devuelve `PublicKeyCredentialCreationOptions` |
| `/api/auth/webauthn/register/complete` | POST | JWT required | Completar registro: verifica attestation, persiste credencial |
| `/api/auth/webauthn/authenticate/begin` | POST | Public | Iniciar autenticación: devuelve `PublicKeyCredentialRequestOptions` |
| `/api/auth/webauthn/authenticate/complete` | POST | Public | Completar autenticación: verifica assertion, emite JWT |
| `/api/profile/webauthn-credentials` | GET | JWT required | Listar credenciales del usuario |
| `/api/profile/webauthn-credentials/{id}` | PATCH | JWT required | Renombrar credencial (AC-7) |
| `/api/profile/webauthn-credentials/{id}` | DELETE | JWT required | Revocar credencial (AC-8) |

**Feature-flag behavior**: Cuando `webauthn.enabled=false`, los 7 endpoints devuelven **404** (AC-11), no 403, para no revelar la existencia de la feature.

### Request/Response Examples

```json
// POST /api/auth/webauthn/register/begin
// Response 200:
{
  "data": {
    "challenge": "base64url...",
    "rp": { "id": "superlistia.com", "name": "Superia" },
    "user": { "id": "base64url-user-handle", "name": "user@email.com", "displayName": "Pedro" },
    "pubKeyCredParams": [{"type":"public-key","alg":-7},{"type":"public-key","alg":-257}],
    "timeout": 60000,
    "attestation": "none",
    "authenticatorSelection": { "userVerification": "preferred", "residentKey": "preferred" },
    "excludeCredentials": [{"type":"public-key","id":"base64url...","transports":["internal"]}]
  }
}

// POST /api/auth/webauthn/register/complete
// Request: { "name": "iPhone 14", "credential": { id, rawId, response: {...}, type: "public-key" } }
// Response 201:
{ "data": { "id": 1, "name": "iPhone 14", "created_at": "2026-04-16T..." } }

// POST /api/auth/webauthn/authenticate/complete
// Response 200:
{
  "data": {
    "token": "eyJhbGc...",
    "user": { "id": 1, "name": "Pedro", "email": "pedro@test.com" }
  }
}

// GET /api/profile/webauthn-credentials
// Response 200:
{
  "data": [
    { "id": 1, "name": "iPhone 14", "transports": ["internal"], "last_used_at": "...", "created_at": "..." },
    { "id": 2, "name": "Laptop trabajo", "transports": ["internal"], "last_used_at": null, "created_at": "..." }
  ]
}
```

## Configuration

### New File: `config/webauthn.php`

```php
return [
    'enabled' => env('WEBAUTHN_ENABLED', false),
    'rp' => [
        'id' => env('WEBAUTHN_RP_ID', 'superia.com.local'),
        'name' => env('WEBAUTHN_RP_NAME', 'Superia'),
    ],
    'origin' => env('WEBAUTHN_ORIGIN', 'https://superlistia.com'),
    'challenge_ttl' => 300, // 5 minutes
    'timeout_ms' => 60000,
    'attestation' => 'none',
    'user_verification' => 'preferred',
    'supported_algorithms' => [-7, -257], // ES256, RS256
];
```

### `.env.example` additions

```
WEBAUTHN_ENABLED=false
WEBAUTHN_RP_ID=superia.com.local
WEBAUTHN_RP_NAME=Superia
WEBAUTHN_ORIGIN=https://superlistia.com
```

## Security Considerations

### Authentication
- JWT emission after assertion is identical to password login → same middleware stack, same session lifecycle, same `jwt_version` invalidation on password reset
- `user_verification=preferred` — biometría cuando haya, PIN/passcode como fallback del authenticator

### Authorization
- Todos los endpoints de `/api/profile/webauthn-credentials/*` usan `auth:api` y filtran por `user_id = auth()->id()` — usuario solo gestiona SUS credenciales
- `authorizeOwnership` check en `WebauthnController@update` y `@destroy`

### Input Validation (FormRequests)
- `BeginAuthenticationRequest`: `email` opcional, `email|max:255`
- `CompleteRegistrationRequest`: `name` requerido 1-50 chars alfanum+spaces, `credential` es JSON blob (se parsea con `web-auth/webauthn-lib`)
- `UpdateCredentialRequest`: `name` 1-50 chars

### Anti-enumeration
- `beginAuthentication({email})` con email no registrado devuelve el mismo shape de options con `allowCredentials=[]`. El tiempo de respuesta se normaliza (mismo `DB::table('users')->where` + `random_bytes`) — NO hacer `return early`

### Challenge Lifecycle
- Cada challenge: 32 bytes de `random_bytes`, base64url
- Almacenado en cache (Redis o file según config) con key `webauthn:{type}:{session_or_user_id}` y TTL 5 min
- Single-use: se borra tras verificación (éxito O fallo)
- Cache miss durante verify → 401 (AC-12)

### Signature Counter
- Regla: `newSignCount > storedSignCount` (estricto)
- Si `newSignCount <= storedSignCount` AND `storedSignCount > 0` → 401 + log warning (AC-13)
- Si `newSignCount == 0` AND `storedSignCount == 0` → aceptar (algunos authenticators no implementan counter)

### rpId / Origin Verification
- `web-auth/webauthn-lib` valida automáticamente `origin` y `rpIdHash` contra config
- AC-14 cubierto out-of-the-box por librería

## Performance

### Query Optimization
- Lookup por `credential_id` en authenticate: usa UNIQUE index, O(1)
- Lista de credenciales del usuario: single query con `user_id` index, cardinalidad baja (tipicamente <5 por user)
- Sin N+1: `WebauthnCredential` no tiene relaciones cargadas eagerly en la mayoría de paths

### Caching
- Challenges en cache Laravel (backed por Redis en prod si está configurado). Sin cache-warming específico.
- No se cachea la public key: se lee en cada authenticate (operación rápida y crítica para security)

## Trade-offs

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| `web-auth/webauthn-lib` (direct) | Canonical, maintained by Symfony team, fine control, supports all conformance edge cases | Más código para wire-up | **Selected** — battle-tested, alineado con nuestros patterns de servicios |
| `asbiin/laravel-webauthn` | Higher-level Laravel integration, scaffolding out of the box | Opaque abstractions, diverge de nuestro AuthService existente, menos mantenimiento | Rejected |
| Challenge en sesión Laravel (cookie) | Sin infra extra | Sessions vía cookie añaden overhead y CSRF surface; JWT flow es stateless hoy | Rejected |
| Challenge en tabla DB | Persistente, auditable | Requiere cleanup job, slower que cache, no necesario para single-use data con TTL 5min | Rejected |
| Challenge en cache Laravel | Stateless, TTL automático, usa infra existente | Si cache se limpia en medio, usuario reintenta | **Selected** |
| Eliminar credencial en cascada al password reset (AC-9) | Mayor seguridad en escenario de account takeover | Fricción UX | **Selected** (Opción C del scope) |
| Mantener credenciales siempre | Cero fricción | Pierde defense-in-depth en recovery | Rejected |
| Revocar en TODO password change | Aún más seguro | Sobre-fricción en cambios voluntarios, confunde al usuario | Rejected |

## Frontend Design

### `resources/js/lib/webauthnApi.js` (nuevo)

Responsabilidades:
- `isSupported()` — chequear `navigator.credentials && window.PublicKeyCredential`
- `registerCredential(name)` — llama begin → `navigator.credentials.create` → complete
- `authenticate(email?)` — llama begin → `navigator.credentials.get` → complete → devuelve JWT
- `listCredentials()`, `renameCredential(id, name)`, `deleteCredential(id)` — CRUD
- Helpers de base64url ↔ ArrayBuffer (necesario porque WebAuthn usa binarios)

### `LoginPage.jsx` (modificado)

- Import `isSupported`, `authenticate` de `webauthnApi`
- `useState` para `webauthnEnabled = isSupported() && configFlag`
- Condicional render de:
  - Botón "Entrar con biometría" (requiere email válido, disabled si no)
  - Botón "Entrar con passkey" (sin email)
- On click: wrapper try/catch, update `useState` errors, en éxito llama `authContext.setToken(token)`

### `ProfilePage.jsx` (modificado)

- Nueva sección `<WebauthnCredentialsList />` después de la sección de security
- Render condicional `isSupported() && webauthn.enabled`

### `WebauthnCredentialsList.jsx` (nuevo)

- `useEffect` fetch lista
- Cada credencial: fila editable inline (nombre), última vez usado, botón revocar (con confirm dialog)
- Botón "Añadir dispositivo biométrico" → llama `registerCredential()` → refresca lista

### Capability detection

```js
export function isSupported() {
  return typeof window !== 'undefined'
    && typeof window.PublicKeyCredential !== 'undefined'
    && typeof navigator.credentials?.create === 'function';
}
```

## Risks & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Vulnerabilidad en `web-auth/webauthn-lib` | Critical | Low | Monitorizar advisories vía `composer audit` (ya en security gate). Lockfile committed. Pinear a major version |
| Challenge cache corrupto / TTL muy corto bloquea usuarios | Medium | Low | TTL 5min es estándar industrial. Error claro "Tiempo agotado, reintenta" y UI no bloqueada |
| rpId cambia en dominio (migración / rename) | High | Low | Passkeys existentes dejan de funcionar. Documentar en runbook: comunicar a usuarios + dejar password activo. No es recuperable (característica del protocolo) |
| Conflicto con CSRF de Laravel en endpoints POST | Medium | Medium | Endpoints WebAuthn bajo `auth:api` (stateless JWT) → CSRF exempt. Verificar que el grupo de rutas los incluye |
| signCount no implementado en algunos authenticators (siempre 0) | Low | Medium | Documentado en spec. Lógica: si `stored == 0 && new == 0` → aceptar (permisivo al onboarding) |
| Usuario registra passkey en dispositivo compartido | Low | Low | WebAuthn requiere user verification (PIN/biometría). Es problema de OS, no nuestro. Documentar en FAQ |
| Cross-site scripting inyectando código que llama a `navigator.credentials.get` | High | Very Low | El protocolo por diseño requiere user gesture + prompt del OS. XSS no puede bypassear el prompt físico. Mantener CSP existente |
| Feature flag olvidado activado en prod antes de time | Medium | Low | Default `WEBAUTHN_ENABLED=false` + ownership explícito del .env |

## Implementation Notes

### Dependencies to add (`composer require`)

```bash
composer require web-auth/webauthn-lib:^5.0
```

Esto instala también como transitive deps: `symfony/uid`, `web-auth/cose-lib`, etc.

### Frontend dependencies

Ninguna adicional. Todo se hace con `navigator.credentials` y `fetch`/`axios` existentes. Los helpers base64url ↔ ArrayBuffer son ~20 líneas.

### Implementation Order (sugerencia para S4)

1. Migration + model `WebauthnCredential`
2. Config `webauthn.php` + `.env.example`
3. `WebauthnService` (skeleton con todos los métodos, sin UI aún)
4. `CredentialSourceRepository` (adapter para `web-auth/webauthn-lib`)
5. `WebauthnController` + routes + FormRequests
6. Feature tests backend (registro, login, listar, renombrar, revocar)
7. Integración con `PasswordResetService` (AC-9) + test
8. `webauthnApi.js` frontend
9. `LoginPage.jsx` modificaciones + capability detection
10. `WebauthnCredentialsList.jsx` + `ProfilePage.jsx` integración
11. E2E manual smoke test en dev

### Testing Strategy

- **Unit tests**: `WebauthnService` métodos con authenticator mockeado (librería provee test doubles)
- **Feature tests**: 4 endpoints WebAuthn con assertions sintéticas firmadas (fixtures). El challenge se genera en test, se firma con key de test, se envía y se valida
- **Frontend**: capability detection puede testearse; el flujo real requiere interacción con hardware → S5-UX browser review con chrome-devtools (sin sensor real, pero validamos render y error flows)

## Open Questions

Ninguna. Diseño implementable end-to-end. S4 puede arrancar inmediatamente.

## Transition

- Gate Status: S3 PENDING
- Next Step: STEP 4 — Implementation
- Required Artifacts: 02-prd.md, 03-technical-design.md
