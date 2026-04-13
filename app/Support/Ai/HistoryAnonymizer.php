<?php

namespace App\Support\Ai;

use App\Models\ProductoHistorial;
use App\Models\User;

class HistoryAnonymizer
{
    /**
     * Return an array of product name strings only.
     * Never returns user IDs, timestamps, list references, or any PII.
     *
     * @return string[]
     */
    public function topProducts(User $user, int $limit): array
    {
        return ProductoHistorial::query()
            ->where('user_id', $user->id)
            ->selectRaw('producto_nombre, COUNT(*) as total')
            ->groupBy('producto_nombre')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('producto_nombre')
            ->map(fn (string $name) => $name)
            ->all();
    }
}
