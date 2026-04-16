<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature flag
    |--------------------------------------------------------------------------
    | When false, all WebAuthn endpoints return 404 and the frontend hides
    | all biometric UI (anti-enumeration). Default false for safe rollout.
    */
    'enabled' => env('WEBAUTHN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Relying Party
    |--------------------------------------------------------------------------
    | rp.id MUST match the effective domain of the app (no scheme, no port).
    | A passkey registered for rp.id=X cannot be used on rp.id=Y.
    */
    'rp' => [
        'id' => env('WEBAUTHN_RP_ID', 'superia.com.local'),
        'name' => env('WEBAUTHN_RP_NAME', 'Superia'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed origins
    |--------------------------------------------------------------------------
    | Origins (scheme+host+port) allowed to initiate WebAuthn ceremonies.
    | Example prod: https://superlistia.com
    */
    'origins' => array_filter(array_map('trim', explode(',', (string) env(
        'WEBAUTHN_ORIGINS',
        'http://superia.com.local,http://localhost,http://127.0.0.1'
    )))),

    /*
    |--------------------------------------------------------------------------
    | Ceremony options
    |--------------------------------------------------------------------------
    */
    'challenge_ttl' => 300, // 5 minutes
    'timeout_ms' => 60000,
    'attestation' => 'none',
    'user_verification' => 'preferred',

    /*
    |--------------------------------------------------------------------------
    | Supported COSE algorithms
    |--------------------------------------------------------------------------
    | -7  = ES256 (ECDSA over P-256 with SHA-256)
    | -257 = RS256 (RSASSA-PKCS1-v1_5 with SHA-256)
    */
    'algorithms' => [-7, -257],
];
