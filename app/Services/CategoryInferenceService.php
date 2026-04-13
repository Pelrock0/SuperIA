<?php

namespace App\Services;

use App\Enums\ProductCategory;
use App\Models\ProductoCatalogo;

class CategoryInferenceService
{
    /**
     * Infer the product category from the static catalog by exact name match (case-insensitive).
     * Returns null if the product is not in the catalog.
     */
    public function infer(string $name): ?ProductCategory
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        $product = ProductoCatalogo::query()
            ->whereRaw('LOWER(nombre) = LOWER(?)', [$trimmed])
            ->whereNotNull('categoria')
            ->first(['categoria']);

        if ($product === null) {
            return null;
        }

        return $product->categoria;
    }
}
