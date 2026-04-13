<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtVersionCheck
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if (!$user) {
            return response()->json([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'No autenticado.'],
            ], 401);
        }

        $payload = JWTAuth::parseToken()->getPayload();
        $tokenVersion = $payload->get('jwt_version', 0);

        if ((int) $tokenVersion !== $user->jwt_version) {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'error' => ['code' => 'TOKEN_INVALIDATED', 'message' => 'Sesión invalidada. Inicia sesión de nuevo.'],
            ], 401);
        }

        return $next($request);
    }
}
