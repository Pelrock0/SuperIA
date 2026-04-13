<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateShareTokenRequest;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Services\ShareTokenService;
use Illuminate\Http\JsonResponse;

class ShareTokenController extends Controller
{
    public function __construct(
        private ShareTokenService $service,
    ) {}

    public function index(ShoppingList $list): JsonResponse
    {
        $this->authorizeListOwnership($list);

        $tokens = $this->service->activeTokensForList($list)
            ->toBase()
            ->map(fn (ListShareToken $token) => $this->presentToken($token))
            ->values();

        return response()->json(['data' => ['tokens' => $tokens]]);
    }

    public function store(CreateShareTokenRequest $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeListOwnership($list);

        $mode = $request->validated('mode');
        $token = $this->service->generate($list, \App\Enums\ShareTokenMode::from($mode));

        return response()->json([
            'data' => ['token' => $this->presentToken($token)],
        ], 201);
    }

    public function destroy(ShoppingList $list, ListShareToken $token): JsonResponse
    {
        $this->authorizeListOwnership($list);
        $this->authorizeTokenBelongsToList($token, $list);

        $this->service->revoke($token);

        return response()->json(['data' => ['message' => 'Enlace revocado.']]);
    }

    private function presentToken(ListShareToken $token): array
    {
        return [
            'id' => $token->id,
            'mode' => $token->mode->value,
            'url' => $this->service->urlFor($token),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }

    private function authorizeListOwnership(ShoppingList $list): void
    {
        if ($list->user_id !== auth('api')->id()) {
            abort(403, 'No tienes acceso a esta lista.');
        }
    }

    private function authorizeTokenBelongsToList(ListShareToken $token, ShoppingList $list): void
    {
        if ($token->shopping_list_id !== $list->id) {
            abort(404);
        }
    }
}
