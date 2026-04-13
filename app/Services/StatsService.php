<?php

namespace App\Services;

use App\Enums\ListStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatsService
{
    public function getStats(User $user): array
    {
        $totalCompleted = $user->shoppingLists()
            ->where('status', ListStatus::Archived)
            ->count();

        $hasEnoughData = $totalCompleted >= 3;

        return [
            'total_lists_completed' => $totalCompleted,
            'has_enough_data' => $hasEnoughData,
            'monthly_spend' => $hasEnoughData ? $this->monthlySpend($user) : [],
            'top_categories' => $hasEnoughData ? $this->topCategories($user) : [],
            'top_products' => $hasEnoughData ? $this->topProducts($user) : [],
        ];
    }

    private function monthlySpend(User $user): array
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth();

        return DB::table('shopping_lists as sl')
            ->join(
                DB::raw('(SELECT shopping_list_id, SUM(estimated_price) as total FROM list_items WHERE estimated_price IS NOT NULL GROUP BY shopping_list_id) as item_totals'),
                'item_totals.shopping_list_id',
                '=',
                'sl.id',
            )
            ->where('sl.user_id', $user->id)
            ->where('sl.status', ListStatus::Archived->value)
            ->where('sl.updated_at', '>=', $sixMonthsAgo)
            ->selectRaw("DATE_FORMAT(sl.updated_at, '%Y-%m') as month, ROUND(SUM(item_totals.total), 2) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => ['month' => $row->month, 'total' => (float) $row->total])
            ->all();
    }

    private function topCategories(User $user): array
    {
        $total = DB::table('producto_historial')
            ->where('user_id', $user->id)
            ->count();

        if ($total === 0) {
            return [];
        }

        return DB::table('producto_historial')
            ->where('user_id', $user->id)
            ->whereNotNull('categoria')
            ->selectRaw('categoria as category, COUNT(*) as count')
            ->groupBy('categoria')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'count' => (int) $row->count,
                'percentage' => round(((int) $row->count / $total) * 100, 1),
            ])
            ->all();
    }

    private function topProducts(User $user): array
    {
        return DB::table('producto_historial')
            ->where('user_id', $user->id)
            ->selectRaw('producto_nombre as name, COUNT(*) as count')
            ->groupBy('producto_nombre')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->count])
            ->all();
    }
}
