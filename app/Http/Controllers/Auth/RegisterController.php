<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function __construct(
        private RegistrationService $registrationService,
    ) {}

    public function validateToken(string $token): JsonResponse
    {
        $entry = $this->registrationService->validateInvitationToken($token);

        if (!$entry) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'El enlace de invitacion es invalido o ha expirado.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'email' => $entry->email,
                'name' => $entry->name,
            ],
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->registrationService->register(
                $request->validated('token'),
                $request->validated('name'),
                $request->validated('password'),
            );

            return response()->json([
                'data' => ['message' => 'Registro exitoso. Revisa tu email para verificar tu cuenta.'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => ['code' => 'REGISTRATION_FAILED', 'message' => $e->getMessage()],
            ], 422);
        }
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_SIGNATURE',
                        'message' => 'El enlace de verificacion es invalido o ha expirado.',
                    ],
                ], 403);
            }
            return redirect('/login?verified=error');
        }

        try {
            $this->registrationService->verifyEmail($id, $hash);

            if ($request->wantsJson()) {
                return response()->json([
                    'data' => ['message' => 'Email verificado correctamente. Ya puedes iniciar sesión.'],
                ]);
            }
            return redirect('/login?verified=success');
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => ['code' => 'VERIFICATION_FAILED', 'message' => $e->getMessage()],
                ], 422);
            }
            return redirect('/login?verified=error');
        }
    }
}
