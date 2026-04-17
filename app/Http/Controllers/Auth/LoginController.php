<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class LoginController extends Controller
{
    public function __construct(
        private AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
            $request->boolean('remember'),
        );

        if (!$result['success']) {
            $status = $result['error'] === 'ACCOUNT_LOCKED' ? 429 : 401;

            return response()->json([
                'error' => ['code' => $result['error'], 'message' => $result['message']],
            ], $status);
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'email_verified_at' => $result['user']->email_verified_at,
                ],
                'token' => $result['token'],
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'data' => ['message' => 'Sesion cerrada correctamente.'],
        ]);
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = $this->authService->refresh();

            return response()->json([
                'data' => ['token' => $token],
            ]);
        } catch (TokenExpiredException $e) {
            return response()->json([
                'error' => ['code' => 'TOKEN_EXPIRED', 'message' => 'La sesión ha expirado. Inicia sesión de nuevo.'],
            ], 401);
        } catch (TokenInvalidException|JWTException $e) {
            return response()->json([
                'error' => ['code' => 'TOKEN_REFRESH_FAILED', 'message' => 'No se pudo renovar la sesión.'],
            ], 401);
        }
    }
}
