<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\WebauthnException;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialSource;

class WebauthnService
{
    private ?SerializerInterface $serializer = null;

    public function createRegistrationOptions(User $user): array
    {
        $challenge = random_bytes(32);
        $handle = (string) Str::uuid();

        $rpConfig = (array) config('webauthn.rp');
        $rp = PublicKeyCredentialRpEntity::create($rpConfig['name'], $rpConfig['id']);

        $userEntity = PublicKeyCredentialUserEntity::create(
            name: $user->email,
            id: WebauthnCredential::userHandleFor($user->id),
            displayName: $user->name ?: $user->email,
        );

        $pubKeyCredParams = array_map(
            fn (int $alg) => PublicKeyCredentialParameters::create('public-key', $alg),
            (array) config('webauthn.algorithms', [-7, -257]),
        );

        $excludeCredentials = $user->webauthnCredentials()
            ->get()
            ->map(fn (WebauthnCredential $c) => PublicKeyCredentialDescriptor::create(
                'public-key',
                WebauthnCredential::base64UrlDecode($c->credential_id),
                $c->transports ?? [],
            ))
            ->all();

        $authenticatorSelection = AuthenticatorSelectionCriteria::create(
            userVerification: (string) config('webauthn.user_verification', 'preferred'),
            residentKey: 'preferred',
        );

        $options = PublicKeyCredentialCreationOptions::create(
            rp: $rp,
            user: $userEntity,
            challenge: $challenge,
            pubKeyCredParams: $pubKeyCredParams,
            authenticatorSelection: $authenticatorSelection,
            attestation: (string) config('webauthn.attestation', 'none'),
            excludeCredentials: $excludeCredentials,
            timeout: (int) config('webauthn.timeout_ms', 60000),
        );

        $this->storeChallenge("reg:{$handle}", $options);

        return [
            'handle' => $handle,
            'options' => $this->serializer()->serialize($options, 'json', [
                'json_encode_options' => JSON_UNESCAPED_SLASHES,
            ]),
        ];
    }

    public function verifyRegistration(User $user, string $handle, string $credentialJson, string $name): WebauthnCredential
    {
        $options = $this->loadChallenge("reg:{$handle}", PublicKeyCredentialCreationOptions::class);

        /** @var PublicKeyCredential $credential */
        $credential = $this->serializer()->deserialize($credentialJson, PublicKeyCredential::class, 'json');

        if (! $credential->response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Invalid credential response type for registration.');
        }

        $validator = $this->attestationValidator();
        $source = $validator->check(
            authenticatorAttestationResponse: $credential->response,
            publicKeyCredentialCreationOptions: $options,
            host: $this->rpId(),
        );

        $this->forgetChallenge("reg:{$handle}");

        return DB::transaction(function () use ($source, $user, $name) {
            return WebauthnCredential::fromPublicKeyCredentialSource($source, $user, $name);
        });
    }

    public function createAuthenticationOptions(?string $email): array
    {
        $challenge = random_bytes(32);
        $handle = (string) Str::uuid();

        $allowCredentials = [];
        if ($email !== null && $email !== '') {
            $user = User::where('email', $email)->first();
            if ($user !== null) {
                $allowCredentials = $user->webauthnCredentials()
                    ->get()
                    ->map(fn (WebauthnCredential $c) => PublicKeyCredentialDescriptor::create(
                        'public-key',
                        WebauthnCredential::base64UrlDecode($c->credential_id),
                        $c->transports ?? [],
                    ))
                    ->all();
            }
        }

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId(),
            allowCredentials: $allowCredentials,
            userVerification: (string) config('webauthn.user_verification', 'preferred'),
            timeout: (int) config('webauthn.timeout_ms', 60000),
        );

        $this->storeChallenge("auth:{$handle}", $options);

        return [
            'handle' => $handle,
            'options' => $this->serializer()->serialize($options, 'json', [
                'json_encode_options' => JSON_UNESCAPED_SLASHES,
            ]),
        ];
    }

    public function verifyAssertion(string $handle, string $credentialJson): array
    {
        $options = $this->loadChallenge("auth:{$handle}", PublicKeyCredentialRequestOptions::class);

        /** @var PublicKeyCredential $credential */
        $credential = $this->serializer()->deserialize($credentialJson, PublicKeyCredential::class, 'json');

        if (! $credential->response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Invalid credential response type for authentication.');
        }

        $credentialIdBase64 = WebauthnCredential::base64UrlEncode($credential->rawId);
        $stored = WebauthnCredential::where('credential_id', $credentialIdBase64)->first();

        if ($stored === null) {
            Log::warning('WebAuthn assertion for unknown credential', [
                'credential_id_b64' => $credentialIdBase64,
            ]);
            throw new \RuntimeException('Credential not recognized.');
        }

        $user = $stored->user;
        if ($user === null || ! $user->is_active) {
            throw new \RuntimeException('User account not available.');
        }

        $userHandleFromResponse = $credential->response->userHandle;
        $expectedUserHandle = WebauthnCredential::userHandleFor($user->id);

        $source = $stored->toPublicKeyCredentialSource();
        $previousSignCount = $source->counter;

        $validator = $this->assertionValidator();

        try {
            $newSource = $validator->check(
                publicKeyCredentialSource: $source,
                authenticatorAssertionResponse: $credential->response,
                publicKeyCredentialRequestOptions: $options,
                host: $this->rpId(),
                userHandle: $userHandleFromResponse ?: $expectedUserHandle,
            );
        } catch (Throwable $e) {
            Log::warning('WebAuthn assertion verification failed', [
                'user_id' => $user->id,
                'credential_id' => $stored->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $newCount = $newSource->counter;
        if ($previousSignCount > 0 && $newCount <= $previousSignCount) {
            Log::error('Possible WebAuthn credential cloning detected', [
                'user_id' => $user->id,
                'credential_id' => $stored->id,
                'stored_sign_count' => $previousSignCount,
                'presented_sign_count' => $newCount,
            ]);
            throw new \RuntimeException('Credential counter validation failed.');
        }

        $stored->update([
            'sign_count' => $newCount,
            'last_used_at' => now(),
        ]);

        $this->forgetChallenge("auth:{$handle}");

        $token = JWTAuth::fromUser($user);

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function listForUser(User $user): Collection
    {
        return $user->webauthnCredentials()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function rename(WebauthnCredential $credential, string $name): WebauthnCredential
    {
        $credential->update(['name' => $name]);

        return $credential->refresh();
    }

    public function delete(WebauthnCredential $credential): void
    {
        $credential->delete();
    }

    public function revokeAllForUser(User $user): int
    {
        return $user->webauthnCredentials()->delete();
    }

    private function storeChallenge(string $key, $options): void
    {
        Cache::put(
            $this->cacheKey($key),
            $this->serializer()->serialize($options, 'json'),
            (int) config('webauthn.challenge_ttl', 300),
        );
    }

    /**
     * @template T
     * @param class-string<T> $type
     * @return T
     */
    private function loadChallenge(string $key, string $type)
    {
        $json = Cache::get($this->cacheKey($key));
        if ($json === null) {
            throw new \RuntimeException('Challenge expired or not found.');
        }

        return $this->serializer()->deserialize($json, $type, 'json');
    }

    private function forgetChallenge(string $key): void
    {
        Cache::forget($this->cacheKey($key));
    }

    private function cacheKey(string $suffix): string
    {
        return 'webauthn:'.$suffix;
    }

    private function rpId(): string
    {
        return (string) config('webauthn.rp.id');
    }

    private function serializer(): SerializerInterface
    {
        if ($this->serializer === null) {
            $attestationStatementSupportManager = new AttestationStatementSupportManager([
                new NoneAttestationStatementSupport(),
            ]);
            $this->serializer = (new WebauthnSerializerFactory($attestationStatementSupportManager))->create();
        }
        return $this->serializer;
    }

    private function attestationValidator(): AuthenticatorAttestationResponseValidator
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins((array) config('webauthn.origins', []));

        return AuthenticatorAttestationResponseValidator::create($factory->creationCeremony());
    }

    private function assertionValidator(): AuthenticatorAssertionResponseValidator
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins((array) config('webauthn.origins', []));

        return AuthenticatorAssertionResponseValidator::create($factory->requestCeremony());
    }
}
