<?php

namespace App\Services;

use App\Models\User;
use App\Support\Ai\Dto\Suggestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductHistoryWeightingService
{
    /**
     * Search the user's history for products matching the query, ranked by recency x frequency.
     *
     * Uses LIKE 'prefix%' over the indexed (user_id, producto_nombre) composite so that queries
     * with as few as 2 characters still return results (FULLTEXT min token size would exclude them).
     * The FULLTEXT index is retained in the migration as a future option for multi-word queries.
     *
     * @return Suggestion[]
     */
    public function search(User $user, string $query, int $limit = 5): array
    {
        $trimmed = trim($query);

        if ($trimmed === '') {
            return [];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $trimmed);

        $rows = $this->baseRankedQuery($user)
            ->where('producto_nombre', 'LIKE', $escaped.'%')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => new Suggestion(
            source: 'history',
            name: $row->producto_nombre,
            quantity: $row->typical_quantity !== null ? (float) $row->typical_quantity : null,
            unit: $row->typical_unit,
            category: $row->typical_category,
        ))->all();
    }

    public function rankedListPaginated(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->baseRankedQuery($user)->paginate($perPage);
    }

    private function baseRankedQuery(User $user): \Illuminate\Database\Query\Builder
    {
        return DB::table('producto_historial')
            ->selectRaw('
                producto_nombre,
                COUNT(*) AS total_count,
                MAX(fecha_compra) AS last_purchased_at,
                MAX(categoria) AS typical_category,
                MAX(unidad) AS typical_unit,
                MAX(cantidad) AS typical_quantity,
                SUM(CASE
                    WHEN fecha_compra >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 2.0
                    WHEN fecha_compra >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1.0
                    ELSE 0.3
                END) AS weighted_score
            ')
            ->where('user_id', $user->id)
            ->groupBy('producto_nombre')
            ->orderByDesc('weighted_score')
            ->orderByDesc('last_purchased_at');
    }
}
