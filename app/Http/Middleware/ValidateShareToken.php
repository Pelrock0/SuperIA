<?php

namespace App\Http\Middleware;

use App\Services\ShareTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateShareToken
{
    public function __construct(
        private ShareTokenService $shareTokens,
    ) {}

    public function handle(Request $request, Closure $next, ?string $requires = null): Response
    {
        $raw = $request->route('tokenParam');

        if (! is_string($raw) || $raw === '') {
            return $this->revoked();
        }

        $context = $this->shareTokens->resolveFromUrlParam($raw);

        if ($context === null) {
            return $this->revoked();
        }

        if ($requires === 'write' && ! $context->allowsWrite()) {
            return response()->json([
                'error' => [
                    'code' => 'READ_ONLY',
                    'message' => 'Este enlace es de solo lectura.',
                ],
            ], 403);
        }

        $request->attributes->set('shareTokenContext', $context);

        return $next($request);
    }

    private function revoked(): Response
    {
        return response()->json([
            'error' => [
                'code' => 'LINK_UNAVAILABLE',
                'message' => 'Este enlace ya no esta activo.',
            ],
        ], 410);
    }
}
