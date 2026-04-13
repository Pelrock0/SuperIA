<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptReplenishmentRequest;
use App\Http\Requests\DismissReplenishmentRequest;
use App\Models\ShoppingList;
use App\Services\ListItemService;
use App\Services\ReplenishmentSuggestionService;
use Illuminate\Http\JsonResponse;

class ReplenishmentController extends Controller
{
    public function __construct(
        private ReplenishmentSuggestionService $service,
        private ListItemService $listItems,
    ) {}

    public function index(): JsonResponse
    {
        $user = auth('api')->user();

        return response()->json([
            'data' => [
                'suggestions' => $this->service->forUser($user),
            ],
        ]);
    }

    public function accept(AcceptReplenishmentRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $list = ShoppingList::findOrFail($request->validated('list_id'));

        if ($list->user_id !== $user->id) {
            abort(403, 'No tienes acceso a esta lista.');
        }

        $result = $this->listItems->create($list, [
            'name' => $request->validated('producto_nombre'),
        ]);

        $this->service->invalidateCache($user);

        return response()->json(['data' => $result], 201);
    }

    public function ignore(DismissReplenishmentRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $this->service->ignore($user, $request->validated('producto_nombre'));

        return response()->json(['data' => ['message' => 'Sugerencia ignorada 24 horas.']]);
    }

    public function silence(DismissReplenishmentRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $this->service->silence($user, $request->validated('producto_nombre'));

        return response()->json(['data' => ['message' => 'Producto silenciado.']]);
    }
}
