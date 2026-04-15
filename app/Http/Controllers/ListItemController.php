<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateItemRequest;
use App\Http\Requests\IncrementQuantityRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\ListCollaborator;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Services\ListItemService;
use Illuminate\Http\JsonResponse;

class ListItemController extends Controller
{
    public function __construct(
        private ListItemService $service,
    ) {}

    public function index(ShoppingList $list): JsonResponse
    {
        $this->authorizeListOwnership($list);

        $data = $this->service->getItemsForList($list);

        return response()->json(['data' => $data]);
    }

    public function store(CreateItemRequest $request, ShoppingList $list): JsonResponse
    {
        $this->authorizeListWrite($list);

        $result = $this->service->create($list, $request->validated());

        return response()->json(['data' => $result], 201);
    }

    public function update(UpdateItemRequest $request, ShoppingList $list, ListItem $item): JsonResponse
    {
        $this->authorizeListWrite($list);
        $this->authorizeItemBelongsToList($item, $list);

        $item = $this->service->update($item, $request->validated());

        return response()->json(['data' => ['item' => $item]]);
    }

    public function toggle(ShoppingList $list, ListItem $item): JsonResponse
    {
        $this->authorizeListOwnership($list);
        $this->authorizeItemBelongsToList($item, $list);

        $result = $this->service->togglePurchased(
            $item,
            auth('api')->id(),
            $list->id,
        );

        return response()->json(['data' => $result]);
    }

    public function destroy(ShoppingList $list, ListItem $item): JsonResponse
    {
        $this->authorizeListWrite($list);
        $this->authorizeItemBelongsToList($item, $list);

        $result = $this->service->delete($item);

        return response()->json(['data' => $result]);
    }

    public function clearCompleted(ShoppingList $list): JsonResponse
    {
        $this->authorizeListWrite($list);

        $result = $this->service->clearCompleted($list);

        return response()->json(['data' => $result]);
    }

    public function incrementQuantity(IncrementQuantityRequest $request, ShoppingList $list, ListItem $item): JsonResponse
    {
        $this->authorizeListWrite($list);
        $this->authorizeItemBelongsToList($item, $list);

        $current = (float) ($item->quantity ?? 0);
        $item->update(['quantity' => $current + (float) $request->validated('quantity')]);

        return response()->json(['data' => ['item' => $item->refresh()]]);
    }

    private function authorizeListOwnership(ShoppingList $list): void
    {
        $userId = auth('api')->id();

        if ($list->user_id === $userId) {
            return;
        }

        $collaborator = ListCollaborator::where('user_id', $userId)
            ->where('shopping_list_id', $list->id)
            ->first();

        if (! $collaborator) {
            abort(403, 'No tienes acceso a esta lista.');
        }
    }

    private function authorizeListWrite(ShoppingList $list): void
    {
        $userId = auth('api')->id();

        if ($list->user_id === $userId) {
            return;
        }

        $collaborator = ListCollaborator::where('user_id', $userId)
            ->where('shopping_list_id', $list->id)
            ->first();

        if (! $collaborator) {
            abort(403, 'No tienes acceso a esta lista.');
        }

        if (! $collaborator->mode->allowsWrite()) {
            abort(403, 'No tienes permisos de edicion en esta lista.');
        }
    }

    private function authorizeItemBelongsToList(ListItem $item, ShoppingList $list): void
    {
        if ($item->shopping_list_id !== $list->id) {
            abort(404);
        }
    }
}
