<?php

namespace App\Services;

use App\Models\ListItem;
use App\Models\ProductoCatalogo;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\Price\ListPriceEstimate;
use App\Support\Price\PriceEstimate;
use Illuminate\Support\Facades\DB;

class PriceEstimationService
{
    /**
     * Estimate price for a single item using the 2-layer pipeline.
     * Layer 1: user's personal price history (most recent precio_real).
     * Layer 2: static catalog average (precio_min/precio_max).
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

        return null;
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
