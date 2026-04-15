<?php

namespace App\Services;

use App\Enums\ProductCategory;
use App\Jobs\InferItemCategoryJob;
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

    /**
     * Dispatch an async AI inference job for an item that has no category.
     */
    public function dispatchAiInference(int $listItemId): void
    {
        InferItemCategoryJob::dispatch($listItemId);
    }
}
