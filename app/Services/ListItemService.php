<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Models\ListItem;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Support\ShareTokenContext;
use Illuminate\Support\Facades\DB;

class ListItemService
{
    public function __construct(
        private ?ActivityLogService $activityLog = null,
        private ?CategoryInferenceService $categoryInference = null,
    ) {
        $this->activityLog ??= new ActivityLogService();
        $this->categoryInference ??= new CategoryInferenceService();
    }

    public function getItemsForList(ShoppingList $list): array
    {
        $items = $list->items()
            ->orderBy('is_purchased')
            ->orderBy('category')
            ->orderBy('position')
            ->orderBy('created_at')
            ->get();

        $grouped = [];
        foreach ($items as $item) {
            $category = $item->category?->value ?? 'otros';
            $grouped[$category][] = $item;
        }

        return [
            'items' => $grouped,
            'counters' => $this->getCounters($list),
        ];
    }

    public function create(ShoppingList $list, array $data, ?ShareTokenContext $context = null): array
    {
        $result = DB::transaction(function () use ($list, $data, $context) {
            $category = $data['category'] ?? null;
            if ($category === null) {
                $inferred = $this->categoryInference->infer($data['name']);
                $category = $inferred?->value;
            }

            $item = $list->items()->create([
                'name' => $data['name'],
                'quantity' => $data['quantity'] ?? null,
                'unit' => $data['unit'] ?? null,
                'category' => $category,
                'estimated_price' => $data['estimated_price'] ?? null,
            ]);

            $counters = $this->syncCounters($list);

            $this->logActivity($list, $context, ActivityAction::ItemAdded, $item->name);

            return ['item' => $item, 'counters' => $counters];
        });

        // If no category was resolved, dispatch async AI inference
        if ($result['item']->category === null) {
            $this->categoryInference->dispatchAiInference($result['item']->id);
        }

        return $result;
    }

    public function update(ListItem $item, array $data, ?ShareTokenContext $context = null): ListItem
    {
        $item->update($data);
        $item = $item->refresh();

        $this->logActivity($item->shoppingList, $context, ActivityAction::ItemEdited, $item->name);

        return $item;
    }

    public function togglePurchased(
        ListItem $item,
        int $userId,
        int $listaId,
        ?ShareTokenContext $context = null,
    ): array {
        return DB::transaction(function () use ($item, $userId, $listaId, $context) {
            $wasPurchased = $item->is_purchased;
            $item->update(['is_purchased' => !$wasPurchased]);

            if (!$wasPurchased) {
                ProductoHistorial::recordPurchase($item, $userId, $listaId);
            }

            $list = $item->shoppingList;
            $counters = $this->syncCounters($list);

            $action = $wasPurchased ? ActivityAction::ItemUnchecked : ActivityAction::ItemChecked;
            $this->logActivity($list, $context, $action, $item->name);

            return ['item' => $item->refresh(), 'counters' => $counters];
        });
    }

    public function delete(ListItem $item, ?ShareTokenContext $context = null): array
    {
        return DB::transaction(function () use ($item, $context) {
            $list = $item->shoppingList;
            $itemName = $item->name;
            $item->delete();
            $counters = $this->syncCounters($list);

            $this->logActivity($list, $context, ActivityAction::ItemDeleted, $itemName);

            return ['counters' => $counters];
        });
    }

    public function clearCompleted(ShoppingList $list, ?ShareTokenContext $context = null): array
    {
        return DB::transaction(function () use ($list, $context) {
            $list->items()->where('is_purchased', true)->delete();
            $counters = $this->syncCounters($list);

            $this->logActivity($list, $context, ActivityAction::ListCleared, $list->name);

            return ['counters' => $counters];
        });
    }

    private function logActivity(
        ShoppingList $list,
        ?ShareTokenContext $context,
        ActivityAction $action,
        string $name,
    ): void {
        $actor = $context ? ActorType::Anonymous : ActorType::Owner;
        $tokenId = $context?->tokenId();

        $this->activityLog->record($list, $actor, $action, $name, $tokenId);
    }

    private function syncCounters(ShoppingList $list): array
    {
        $total = $list->items()->count();
        $completed = $list->items()->where('is_purchased', true)->count();

        $list->update([
            'items_total' => $total,
            'items_completed' => $completed,
        ]);

        return ['items_total' => $total, 'items_completed' => $completed];
    }

    private function getCounters(ShoppingList $list): array
    {
        return [
            'items_total' => $list->items_total,
            'items_completed' => $list->items_completed,
        ];
    }
}
