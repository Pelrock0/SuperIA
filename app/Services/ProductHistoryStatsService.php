<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductHistoryStatsService
{
    public function completedListsCount(User $user): int
    {
        return DB::table('shopping_lists')
            ->where('user_id', $user->id)
            ->where('items_total', '>', 0)
            ->whereColumn('items_total', 'items_completed')
            ->count();
    }

    /**
     * @return array<int, int>
     */
    public function completedListIds(User $user): array
    {
        return DB::table('shopping_lists')
            ->where('user_id', $user->id)
            ->where('items_total', '>', 0)
            ->whereColumn('items_total', 'items_completed')
            ->pluck('id')
            ->all();
    }

    public function hasActiveListWithMinItems(User $user, int $minItems): bool
    {
        return DB::table('shopping_lists')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('items_total', '>=', $minItems)
            ->exists();
    }

    public function distinctProductCount(User $user): int
    {
        return DB::table('producto_historial')
            ->where('user_id', $user->id)
            ->distinct()
            ->count('producto_nombre');
    }
}
