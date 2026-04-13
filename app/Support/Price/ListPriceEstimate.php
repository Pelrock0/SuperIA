<?php

namespace App\Support\Price;

class ListPriceEstimate
{
    /**
     * @param  array<int, array{item_id: int, name: string, min: ?float, max: ?float, source: ?string}>  $items
     */
    public function __construct(
        public readonly float $totalMin,
        public readonly float $totalMax,
        public readonly array $items,
        public readonly int $resolvedCount,
        public readonly int $unresolvedCount,
    ) {}

    public function toArray(): array
    {
        return [
            'total_min' => round($this->totalMin, 2),
            'total_max' => round($this->totalMax, 2),
            'items' => $this->items,
            'resolved_count' => $this->resolvedCount,
            'unresolved_count' => $this->unresolvedCount,
        ];
    }
}
