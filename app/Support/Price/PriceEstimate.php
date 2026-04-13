<?php

namespace App\Support\Price;

class PriceEstimate
{
    public function __construct(
        public readonly string $productName,
        public readonly float $min,
        public readonly float $max,
        public readonly string $source, // 'history' | 'catalog'
    ) {}

    public function toArray(): array
    {
        return [
            'product_name' => $this->productName,
            'min' => $this->min,
            'max' => $this->max,
            'source' => $this->source,
        ];
    }
}
