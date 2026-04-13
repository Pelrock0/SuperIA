<?php

namespace App\Services;

use App\Models\AiDismissedSuggestion;
use App\Models\User;
use App\Models\UserSilencedProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReplenishmentSuggestionService
{
    public const MAX_SUGGESTIONS = 3;

    public const MIN_ACTIVE_LIST_ITEMS = 3;

    public const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private ProductHistoryStatsService $stats,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        if (! $this->stats->hasActiveListWithMinItems($user, self::MIN_ACTIVE_LIST_ITEMS)) {
            return [];
        }

        return Cache::remember(
            $this->cacheKey($user),
            self::CACHE_TTL_SECONDS,
            fn () => $this->computeCandidates($user),
        );
    }

    public function ignore(User $user, string $productName): void
    {
        AiDismissedSuggestion::create([
            'user_id' => $user->id,
            'producto_nombre' => $productName,
            'dismissed_until' => now()->addHours(24),
            'created_at' => now(),
        ]);

        $this->invalidateCache($user);
    }

    public function silence(User $user, string $productName): void
    {
        UserSilencedProduct::firstOrCreate(
            [
                'user_id' => $user->id,
                'producto_nombre' => $productName,
            ],
            [
                'silenced_at' => now(),
            ],
        );

        $this->invalidateCache($user);
    }

    public function invalidateCache(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function computeCandidates(User $user): array
    {
        $minOccurrences = (int) config('ai.thresholds.min_occurrences', 3);
        $factor = (float) config('ai.thresholds.replenishment_factor', 0.8);

        $rows = DB::table('producto_historial')
            ->selectRaw('
                producto_nombre,
                COUNT(*) AS purchase_count,
                MAX(fecha_compra) AS last_purchased_at,
                DATEDIFF(NOW(), MAX(fecha_compra)) AS days_since_last,
                CASE
                    WHEN COUNT(*) > 1 THEN DATEDIFF(MAX(fecha_compra), MIN(fecha_compra)) / (COUNT(*) - 1)
                    ELSE NULL
                END AS avg_days_between
            ')
            ->where('user_id', $user->id)
            ->groupBy('producto_nombre')
            ->havingRaw('purchase_count >= ?', [$minOccurrences])
            ->havingRaw('avg_days_between IS NOT NULL')
            ->havingRaw('days_since_last > avg_days_between * ?', [$factor])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $activeListProducts = $this->productsInActiveLists($user);
        $silenced = $this->silencedProducts($user);
        $dismissed = $this->dismissedProducts($user);

        $excluded = array_fill_keys(
            array_map('mb_strtolower', array_merge($activeListProducts, $silenced, $dismissed)),
            true,
        );

        $candidates = [];
        foreach ($rows as $row) {
            $key = mb_strtolower($row->producto_nombre);
            if (isset($excluded[$key])) {
                continue;
            }

            $urgencyRatio = (float) $row->avg_days_between > 0
                ? (float) $row->days_since_last / (float) $row->avg_days_between
                : 0.0;

            $candidates[] = [
                'producto_nombre' => $row->producto_nombre,
                'purchase_count' => (int) $row->purchase_count,
                'last_purchased_at' => $row->last_purchased_at,
                'days_since_last' => (int) $row->days_since_last,
                'avg_days_between' => round((float) $row->avg_days_between, 1),
                'urgency_ratio' => round($urgencyRatio, 2),
                'frequency_label' => $this->frequencyLabel($row->producto_nombre, (float) $row->avg_days_between),
                'source' => 'history',
            ];
        }

        usort($candidates, fn ($a, $b) => $b['urgency_ratio'] <=> $a['urgency_ratio']);

        return array_slice($candidates, 0, self::MAX_SUGGESTIONS);
    }

    /**
     * @return array<int, string>
     */
    private function productsInActiveLists(User $user): array
    {
        return DB::table('list_items')
            ->join('shopping_lists', 'list_items.shopping_list_id', '=', 'shopping_lists.id')
            ->where('shopping_lists.user_id', $user->id)
            ->where('shopping_lists.status', 'active')
            ->pluck('list_items.name')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function silencedProducts(User $user): array
    {
        return UserSilencedProduct::where('user_id', $user->id)
            ->pluck('producto_nombre')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function dismissedProducts(User $user): array
    {
        return AiDismissedSuggestion::where('user_id', $user->id)
            ->where('dismissed_until', '>', now())
            ->pluck('producto_nombre')
            ->all();
    }

    private function frequencyLabel(string $productName, float $avgDaysBetween): string
    {
        $days = max(1, (int) round($avgDaysBetween));

        return $days === 1
            ? "Sueles comprar {$productName} cada dia"
            : "Sueles comprar {$productName} cada {$days} dias";
    }

    private function cacheKey(User $user): string
    {
        return "replenishment:user:{$user->id}";
    }
}
