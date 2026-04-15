<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Models\ShoppingList;
use App\Services\ActivityLogService;
use App\Services\CollaboratorPresenceService;
use App\Services\ListCollaboratorService;
use App\Services\ShoppingListService;
use Illuminate\Http\JsonResponse;

class ShoppingListController extends Controller
{
    public function __construct(
        private ShoppingListService $service,
        private CollaboratorPresenceService $presence,
        private ActivityLogService $activityLog,
        private ListCollaboratorService $collaboratorService,
    ) {}

    public function index(): JsonResponse
    {
        $lists = $this->service->getListsForUser(auth('api')->user());

        return response()->json(['data' => $lists]);
    }

    public function store(CreateListRequest $request): JsonResponse
    {
        try {
            $list = $this->service->create(
                auth('api')->user(),
                $request->validated(),
            );

            return response()->json(['data' => $list], 201);
        } catch (\OverflowException $e) {
            return response()->json([
                'error' => ['code' => 'FREEMIUM_LIMIT', 'message' => $e->getMessage()],
            ], 403);
        }
    }

    public function show(ShoppingList $list): JsonResponse
    {
        $this->authorizeOwnership($list);

        return response()->json(['data' => $list]);
    }

    public function update(UpdateListRequest $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeOwnership($list);

        $list = $this->service->update($list, $request->validated());

        return response()->json(['data' => $list]);
    }

    public function archive(ShoppingList $list): JsonResponse
    {
        $this->authorizeOwnership($list);

        $list = $this->service->archive($list);

        return response()->json(['data' => $list]);
    }

    public function restore(ShoppingList $list): JsonResponse
    {
        $this->authorizeOwnership($list);

        try {
            $list = $this->service->restore($list);

            return response()->json(['data' => $list]);
        } catch (\OverflowException $e) {
            return response()->json([
                'error' => ['code' => 'FREEMIUM_LIMIT', 'message' => $e->getMessage()],
            ], 403);
        }
    }

    public function destroy(ShoppingList $list): JsonResponse
    {
        $this->authorizeOwnership($list);

        $this->service->delete($list);

        return response()->json(['data' => ['message' => 'Lista eliminada correctamente.']]);
    }

    public function collaboratorsCount(ShoppingList $list): JsonResponse
    {
        $this->authorizeOwnership($list);

        return response()->json([
            'data' => ['count' => $this->presence->countActive($list)],
        ]);
    }

    public function activityLog(ShoppingList $list): JsonResponse
    {
        $this->authorizeOwnership($list);

        $entries = $this->activityLog->getRecent($list)->toBase()->map(fn ($entry) => [
            'id' => $entry->id,
            'actor_type' => $entry->actor_type->value,
            'action' => $entry->action->value,
            'item_name' => $entry->item_name,
            'created_at' => $entry->created_at?->toIso8601String(),
        ])->values();

        return response()->json(['data' => ['entries' => $entries]]);
    }

    public function collaborators(ShoppingList $list): JsonResponse
    {
        $this->authorizeOwnership($list);

        return response()->json([
            'data' => $this->collaboratorService->collaboratorsForList($list),
        ]);
    }

    private function authorizeOwnership(ShoppingList $list): void
    {
        if ($list->user_id !== auth('api')->id()) {
            abort(403, 'No tienes acceso a esta lista.');
        }
    }
}
