<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComplementQueryRequest;
use App\Models\ShoppingList;
use App\Services\ComplementarySuggestionService;
use Illuminate\Http\JsonResponse;

class ComplementController extends Controller
{
    public function __construct(
        private ComplementarySuggestionService $service,
    ) {}

    public function index(ComplementQueryRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $list = ShoppingList::findOrFail($request->validated('list_id'));

        if ($list->user_id !== $user->id) {
            abort(403, 'No tienes acceso a esta lista.');
        }

        $result = $this->service->suggest(
            $user,
            (string) $request->validated('product'),
            (int) $request->validated('list_id'),
        );

        return response()->json(['data' => $result]);
    }
}
