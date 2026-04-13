<?php

namespace App\Http\Controllers;

use App\Models\ShoppingList;
use App\Services\ListHistoryService;
use Illuminate\Http\JsonResponse;
use OverflowException;

class HistoryController extends Controller
{
    public function __construct(
        private ListHistoryService $service,
    ) {}

    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $history = $this->service->getHistory($user);

        return response()->json([
            'data' => $history->items(),
            'meta' => [
                'current_page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'last_page' => $history->lastPage(),
            ],
        ]);
    }

    public function duplicate(ShoppingList $list): JsonResponse
    {
        $user = auth('api')->user();

        if ($list->user_id !== $user->id) {
            abort(404);
        }

        try {
            $newList = $this->service->duplicate($user, $list);
        } catch (OverflowException $e) {
            return response()->json([
                'error' => ['code' => 'FREEMIUM_LIMIT', 'message' => $e->getMessage()],
            ], 403);
        }

        return response()->json(['data' => $newList], 201);
    }
}
