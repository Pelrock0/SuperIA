<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateItemRequest;
use App\Http\Requests\HeartbeatRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\ListItem;
use App\Services\CollaboratorPresenceService;
use App\Services\ListCollaboratorService;
use App\Services\ListItemService;
use App\Support\ShareTokenContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedListController extends Controller
{
    public function __construct(
        private ListItemService $items,
        private CollaboratorPresenceService $presence,
        private ListCollaboratorService $collaborators,
    ) {}

    public function show(Request $request, string $tokenParam): JsonResponse
    {
        $context = $this->context($request);
        $list = $context->list;
        $list->loadMissing('user');

        $data = $this->items->getItemsForList($list);

        return response()->json([
            'data' => [
                'list' => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'emoji' => $list->emoji,
                    'owner_name' => $list->user?->name,
                ],
                'mode' => $context->mode->value,
                'items' => $data['items'],
                'counters' => $data['counters'],
            ],
        ]);
    }

    public function storeItem(CreateItemRequest $request, string $tokenParam): JsonResponse
    {
        $context = $this->context($request);
        $this->requireWrite($context);

        $result = $this->items->create($context->list, $request->validated(), $context);

        return response()->json(['data' => $result], 201);
    }

    public function updateItem(UpdateItemRequest $request, string $tokenParam, ListItem $item): JsonResponse
    {
        $context = $this->context($request);
        $this->requireWrite($context);
        $this->assertItemBelongs($item, $context);

        $updated = $this->items->update($item, $request->validated(), $context);

        return response()->json(['data' => ['item' => $updated]]);
    }

    public function toggleItem(Request $request, string $tokenParam, ListItem $item): JsonResponse
    {
        $context = $this->context($request);
        $this->requireWrite($context);
        $this->assertItemBelongs($item, $context);

        $result = $this->items->togglePurchased(
            $item,
            $context->list->user_id,
            $context->list->id,
            $context,
        );

        return response()->json(['data' => $result]);
    }

    public function destroyItem(Request $request, string $tokenParam, ListItem $item): JsonResponse
    {
        $context = $this->context($request);
        $this->requireWrite($context);
        $this->assertItemBelongs($item, $context);

        $result = $this->items->delete($item, $context);

        return response()->json(['data' => $result]);
    }

    public function heartbeat(HeartbeatRequest $request, string $tokenParam): JsonResponse
    {
        $context = $this->context($request);
        $this->presence->heartbeat($context, $request->validated('session_uuid'));

        return response()->json(['data' => ['status' => 'ok']]);
    }

    public function saveToAccount(Request $request, string $tokenParam): JsonResponse
    {
        $context = $this->context($request);
        $user = auth('api')->user();

        if (! $user) {
            abort(401, 'Debes iniciar sesion para guardar esta lista.');
        }

        if ($context->list->user_id === $user->id) {
            abort(409, 'Ya eres el propietario de esta lista.');
        }

        $collaborator = $this->collaborators->linkUser($user, $context);

        return response()->json([
            'data' => [
                'linked' => true,
                'mode' => $collaborator->mode->value,
            ],
        ], 201);
    }

    public function saveStatus(Request $request, string $tokenParam): JsonResponse
    {
        $context = $this->context($request);
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['data' => ['linked' => false, 'authenticated' => false]]);
        }

        $isOwner = $context->list->user_id === $user->id;
        $isLinked = $this->collaborators->isLinked($user->id, $context->list->id);

        return response()->json([
            'data' => [
                'authenticated' => true,
                'is_owner' => $isOwner,
                'linked' => $isLinked,
            ],
        ]);
    }

    private function context(Request $request): ShareTokenContext
    {
        $context = $request->attributes->get('shareTokenContext');

        if (! $context instanceof ShareTokenContext) {
            abort(410);
        }

        return $context;
    }

    private function requireWrite(ShareTokenContext $context): void
    {
        if (! $context->allowsWrite()) {
            abort(403, 'Este enlace es de solo lectura.');
        }
    }

    private function assertItemBelongs(ListItem $item, ShareTokenContext $context): void
    {
        if ($item->shopping_list_id !== $context->list->id) {
            abort(404);
        }
    }
}
