<?php

namespace App\Services;

use App\Enums\ListStatus;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ListHistoryService
{
    public function __construct(
        private ShoppingListService $shoppingLists,
    ) {}

    public function getHistory(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $lists = $user->shoppingLists()
            ->where('status', ListStatus::Archived)
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        $lists->getCollection()->transform(function (ShoppingList $list) {
            $priceTotal = DB::table('list_items')
                ->where('shopping_list_id', $list->id)
                ->whereNotNull('estimated_price')
                ->sum('estimated_price');

            $list->setAttribute('price_total', $priceTotal > 0 ? round((float) $priceTotal, 2) : null);
            $list->setAttribute('price_source', $priceTotal > 0 ? 'estimated' : null);

            return $list;
        });

        return $lists;
    }

    /**
     * Duplicate an archived list as a new active list.
     * Propagates OverflowException from ShoppingListService when freemium limit hit.
     */
    public function duplicate(User $user, ShoppingList $list): ShoppingList
    {
        if ($list->user_id !== $user->id) {
            abort(404);
        }

        $newList = $this->shoppingLists->create($user, [
            'name' => 'Copia de '.$list->name,
            'emoji' => $list->emoji,
            'category' => $list->category?->value,
        ]);

        $position = 0;
        foreach ($list->items as $item) {
            $newList->items()->create([
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit' => $item->unit?->value,
                'category' => $item->category?->value,
                'is_purchased' => false,
                'position' => $position++,
            ]);
        }

        $newList->update([
            'items_total' => $newList->items()->count(),
            'items_completed' => 0,
        ]);

        return $newList->refresh();
    }
}
