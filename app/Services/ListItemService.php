<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Enums\ItemUnit;
use App\Enums\ProductCategory;
use App\Models\ListItem;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Support\Inflector\SpanishInflector;
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
            $rawUnit = $data['unit'] ?? null;
            $unit = $rawUnit !== null ? ItemUnit::tryFrom((string) $rawUnit)?->value : null;

            $this->deletePurchasedHomonyms($list, (string) $data['name'], $unit);

            $category = $data['category'] ?? null;
            if ($category === null) {
                $inferred = $this->categoryInference->infer($data['name']);
                $category = $inferred?->value;
            }

            $item = $list->items()->create([
                'name' => $data['name'],
                'quantity' => $data['quantity'] ?? null,
                'unit' => $unit,
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

    /**
     * Create a new item, or — if a pending item with the same normalized name and unit
     * already exists in the list — increment its quantity instead.
     *
     * Match rule: trimmed/lowercased `name` AND identical `unit` (string or null) AND `is_purchased = false`.
     * Different units, purchased items, or different normalized names are treated as separate entries.
     *
     * Caller is responsible for wrapping this in a transaction when atomicity matters.
     *
     * @param  array{name:string, quantity?:float|null, unit?:string|null, category?:string|null}  $data
     */
    public function createOrIncrement(ShoppingList $list, array $data): ListItem
    {
        $name = (string) $data['name'];
        $normalized = SpanishInflector::normalize($name);
        $rawUnit = $data['unit'] ?? null;
        $unit = $rawUnit !== null ? ItemUnit::tryFrom((string) $rawUnit)?->value : null;
        $quantityToAdd = isset($data['quantity']) ? (float) $data['quantity'] : 0.0;

        $pendings = $list->items()
            ->where('is_purchased', false)
            ->lockForUpdate()
            ->get();

        $existing = $pendings->first(function (ListItem $item) use ($normalized, $unit): bool {
            return SpanishInflector::normalize((string) $item->name) === $normalized
                && $this->unitMatches($item->unit?->value, $unit);
        });

        if ($existing !== null) {
            $current = (float) ($existing->quantity ?? 0.0);
            $existing->update(['quantity' => $current + $quantityToAdd]);
            return $existing->refresh();
        }

        $this->deletePurchasedHomonyms($list, $name, $unit);

        $rawCategory = $data['category'] ?? null;
        $category = $rawCategory !== null ? ProductCategory::tryFrom((string) $rawCategory)?->value : null;
        if ($category === null) {
            $inferred = $this->categoryInference->infer($name);
            $category = $inferred?->value;
        }

        $maxPosition = (int) ($list->items()->max('position') ?? -1);

        return $list->items()->create([
            'name' => $name,
            'quantity' => $quantityToAdd > 0 ? $quantityToAdd : null,
            'unit' => $unit,
            'category' => $category,
            'is_purchased' => false,
            'position' => $maxPosition + 1,
        ]);
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

    private function deletePurchasedHomonyms(ShoppingList $list, string $name, ?string $unit): void
    {
        $normalized = SpanishInflector::normalize($name);

        $purchased = $list->items()
            ->where('is_purchased', true)
            ->lockForUpdate()
            ->get();

        $matchIds = $purchased
            ->filter(fn (ListItem $item): bool => SpanishInflector::normalize((string) $item->name) === $normalized
                && $this->unitMatches($item->unit?->value, $unit))
            ->pluck('id')
            ->all();

        if ($matchIds !== []) {
            $list->items()->whereIn('id', $matchIds)->delete();
        }
    }

    private function unitMatches(?string $itemUnit, ?string $newUnit): bool
    {
        return $itemUnit === $newUnit;
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
