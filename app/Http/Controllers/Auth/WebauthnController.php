<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Webauthn\BeginAuthenticationRequest;
use App\Http\Requests\Auth\Webauthn\CompleteAuthenticationRequest;
use App\Http\Requests\Auth\Webauthn\CompleteRegistrationRequest;
use App\Http\Requests\Auth\Webauthn\UpdateCredentialRequest;
use App\Models\WebauthnCredential;
use App\Services\WebauthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WebauthnController extends Controller
{
    public function __construct(
        private readonly WebauthnService $service,
    ) {}

    public function beginRegistration(): JsonResponse
    {
        $user = auth('api')->user();
        $result = $this->service->createRegistrationOptions($user);

        return response()->json([
            'data' => [
                'handle' => $result['handle'],
                'options' => json_decode($result['options'], true),
            ],
        ]);
    }

    public function completeRegistration(CompleteRegistrationRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        try {
            $credential = $this->service->verifyRegistration(
                user: $user,
                handle: $request->validated('handle'),
                credentialJson: json_encode($request->validated('credential')),
                name: $request->validated('name'),
            );
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WebAuthn registration failed', [
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            $error = [
                'code' => 'WEBAUTHN_REGISTRATION_FAILED',
                'message' => 'No se pudo registrar el dispositivo. Intentalo de nuevo.',
            ];
            if (config('app.debug')) {
                $error['debug'] = $e::class.': '.$e->getMessage();
            }

            return response()->json(['error' => $error], 422);
        }

        return response()->json([
            'data' => [
                'id' => $credential->id,
                'name' => $credential->name,
                'transports' => $credential->transports,
                'created_at' => $credential->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function beginAuthentication(BeginAuthenticationRequest $request): JsonResponse
    {
        $result = $this->service->createAuthenticationOptions($request->validated('email'));

        return response()->json([
            'data' => [
                'handle' => $result['handle'],
                'options' => json_decode($result['options'], true),
            ],
        ]);
    }

    public function completeAuthentication(CompleteAuthenticationRequest $request): JsonResponse
    {
        try {
            $result = $this->service->verifyAssertion(
                handle: $request->validated('handle'),
                credentialJson: json_encode($request->validated('credential')),
            );
        } catch (Throwable $e) {
            return response()->json([
                'error' => [
                    'code' => 'WEBAUTHN_AUTH_FAILED',
                    'message' => 'Autenticacion biometrica fallida.',
                ],
            ], 401);
        }

        $user = $result['user'];

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    public function listCredentials(): JsonResponse
    {
        $user = auth('api')->user();
        $credentials = $this->service->listForUser($user);

        return response()->json([
            'data' => $credentials->map(fn (WebauthnCredential $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'transports' => $c->transports,
                'last_used_at' => $c->last_used_at?->toIso8601String(),
                'created_at' => $c->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function updateCredential(UpdateCredentialRequest $request, WebauthnCredential $webauthnCredential): JsonResponse
    {
        $this->authorizeOwnership($webauthnCredential);

        $updated = $this->service->rename($webauthnCredential, $request->validated('name'));

        return response()->json([
            'data' => [
                'id' => $updated->id,
                'name' => $updated->name,
            ],
        ]);
    }

    public function deleteCredential(WebauthnCredential $webauthnCredential): JsonResponse
    {
        $this->authorizeOwnership($webauthnCredential);

        $this->service->delete($webauthnCredential);

        return response()->json([
            'data' => ['message' => 'Dispositivo revocado.'],
        ]);
    }

    private function authorizeOwnership(WebauthnCredential $credential): void
    {
        if ($credential->user_id !== auth('api')->id()) {
            abort(403, 'No tienes acceso a este dispositivo.');
        }
    }
}
