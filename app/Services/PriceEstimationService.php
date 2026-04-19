<?php

namespace App\Services;

use App\Models\ListItem;
use App\Models\PriceCache;
use App\Models\ProductoCatalogo;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Price\ListPriceEstimate;
use App\Support\Price\PriceEstimate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceEstimationService
{
    private const CLAUDE_DAILY_LIMIT = 50;

    private const PRICE_CACHE_TTL_DAYS = 30;

    public function __construct(private readonly ClaudeClientInterface $claude) {}

    /**
     * Estimate price for a single item using the 3-layer pipeline.
     * Layer 1: user's personal price history (most recent precio_real).
     * Layer 2: static catalog exact match (precio_min/precio_max).
     * Layer 3a: catalog LIKE fuzzy match.
     * Layer 3b: price_cache lookup.
     * Layer 3c: Claude direct estimation (throttled, writes to price_cache).
     */
    public function estimateForItem(User $user, ListItem $item): ?PriceEstimate
    {
        $name = trim($item->name);
        if ($name === '') {
            return null;
        }

        // Layer 1: personal history
        $historyPrice = ProductoHistorial::query()
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(producto_nombre) = LOWER(?)', [$name])
            ->whereNotNull('precio_real')
            ->orderByDesc('fecha_compra')
            ->value('precio_real');

        if ($historyPrice !== null) {
            $price = (float) $historyPrice;
            return new PriceEstimate($name, $price, $price, 'history');
        }

        // Layer 2: static catalog
        $catalog = ProductoCatalogo::query()
            ->whereRaw('LOWER(nombre) = LOWER(?)', [$name])
            ->whereNotNull('precio_min')
            ->first(['precio_min', 'precio_max']);

        if ($catalog !== null) {
            return new PriceEstimate(
                $name,
                (float) $catalog->precio_min,
                (float) $catalog->precio_max,
                'catalog',
            );
        }

        // Layer 3a: fuzzy LIKE match in catalog (OR across significant words)
        $words = array_values(array_filter(explode(' ', mb_strtolower($name)), fn ($w) => mb_strlen($w) > 2));
        if (! empty($words)) {
            $fuzzy = ProductoCatalogo::query()
                ->whereNotNull('precio_min')
                ->where(function ($q) use ($words) {
                    foreach ($words as $word) {
                        $q->orWhere('nombre', 'LIKE', '%'.$word.'%');
                    }
                })
                ->first(['nombre', 'precio_min', 'precio_max']);

            if ($fuzzy !== null) {
                return new PriceEstimate(
                    $name,
                    (float) $fuzzy->precio_min,
                    (float) $fuzzy->precio_max,
                    'catalog_fuzzy',
                );
            }
        }

        // Layer 3b: price_cache lookup
        $cached = PriceCache::query()
            ->whereRaw('LOWER(input_name) = LOWER(?)', [$name])
            ->first();

        if ($cached !== null && ! $cached->isExpired() && $cached->precio_min !== null) {
            return new PriceEstimate(
                $name,
                $cached->precio_min,
                $cached->precio_max,
                'cache',
            );
        }

        // Layer 3c: Claude fallback (per-user daily throttle)
        if ($this->withinThrottle($user->id)) {
            try {
                $result = $this->claude->estimateItemPrice($name);
                $this->incrementThrottle($user->id);

                PriceCache::updateOrCreate(
                    ['input_name' => mb_strtolower($name)],
                    [
                        'precio_min' => $result['precio_min'],
                        'precio_max' => $result['precio_max'],
                        'expires_at' => now()->addDays(self::PRICE_CACHE_TTL_DAYS),
                    ],
                );

                return new PriceEstimate(
                    $name,
                    $result['precio_min'],
                    $result['precio_max'],
                    'ai',
                );
            } catch (ClaudeException $e) {
                Log::warning('price.layer3.claude_failed', ['item' => $name, 'error' => $e->getMessage()]);
            }
        }

        return null;
    }

    private function withinThrottle(int $userId): bool
    {
        $key = "price_throttle:{$userId}:".now()->toDateString();
        return (int) Cache::get($key, 0) < self::CLAUDE_DAILY_LIMIT;
    }

    private function incrementThrottle(int $userId): void
    {
        $key = "price_throttle:{$userId}:".now()->toDateString();
        $ttl = now()->endOfDay()->diffInSeconds(now());
        Cache::add($key, 0, $ttl);
        Cache::increment($key);
    }

    /**
     * Estimate prices for all items in a list. Persists estimated_price per item.
     */
    public function estimateForList(User $user, ShoppingList $list): ListPriceEstimate
    {
        $totalMin = 0.0;
        $totalMax = 0.0;
        $items = [];
        $resolved = 0;
        $unresolved = 0;

        foreach ($list->items as $item) {
            $estimate = $this->estimateForItem($user, $item);
            $quantity = max(1, (float) ($item->quantity ?? 1));

            if ($estimate !== null) {
                $itemMin = round($estimate->min * $quantity, 2);
                $itemMax = round($estimate->max * $quantity, 2);
                $totalMin += $itemMin;
                $totalMax += $itemMax;
                $resolved++;

                $item->update(['estimated_price' => round(($itemMin + $itemMax) / 2, 2)]);

                $items[] = [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'min' => $itemMin,
                    'max' => $itemMax,
                    'source' => $estimate->source,
                ];
            } else {
                $unresolved++;
                $item->update(['estimated_price' => null]);

                $items[] = [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'min' => null,
                    'max' => null,
                    'source' => null,
                ];
            }
        }

        return new ListPriceEstimate(
            round($totalMin, 2),
            round($totalMax, 2),
            $items,
            $resolved,
            $unresolved,
        );
    }

    /**
     * Record real per-item prices into producto_historial (feeds Layer 1).
     *
     * @param  array<int, array{item_id: int, price: float}>  $itemPrices
     */
    public function recordItemPrices(User $user, ShoppingList $list, array $itemPrices): int
    {
        $updated = 0;

        DB::transaction(function () use ($user, $list, $itemPrices, &$updated) {
            foreach ($itemPrices as $entry) {
                $item = $list->items()->find($entry['item_id']);
                if ($item === null) {
                    continue;
                }

                $historyRow = ProductoHistorial::query()
                    ->where('user_id', $user->id)
                    ->whereRaw('LOWER(producto_nombre) = LOWER(?)', [$item->name])
                    ->orderByDesc('fecha_compra')
                    ->first();

                if ($historyRow !== null) {
                    $historyRow->update(['precio_real' => (float) $entry['price']]);
                    $updated++;
                }

                $item->update(['estimated_price' => (float) $entry['price']]);
            }
        });

        return $updated;
    }

    /**
     * Log a total-only price (no per-item distribution).
     */
    public function recordTotalPrice(User $user, ShoppingList $list, float $total): void
    {
        // Informational log only — no Layer 1 feed per decision #8
        \Illuminate\Support\Facades\Log::info('price.total_confirmed', [
            'user_id' => $user->id,
            'list_id' => $list->id,
            'total' => $total,
        ]);
    }
}
