<?php

namespace App\Support\Ai\Dto;

class Suggestion
{
    public function __construct(
        public readonly string $source,      // 'history' | 'catalog' | 'ai'
        public readonly string $name,
        public readonly ?float $quantity = null,
        public readonly ?string $unit = null,
        public readonly ?string $category = null,
    ) {}

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'category' => $this->category,
        ];
    }
}
