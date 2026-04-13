<?php

namespace Database\Factories;

use App\Enums\ItemUnit;
use App\Enums\ProductCategory;
use App\Models\ProductoCatalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductoCatalogo>
 */
class ProductoCatalogoFactory extends Factory
{
    protected $model = ProductoCatalogo::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'nombre' => fake()->words(2, true),
            'categoria' => fake()->randomElement(ProductCategory::cases()),
            'unidad_tipica' => fake()->randomElement(ItemUnit::cases()),
            'cantidad_tipica' => fake()->randomFloat(2, 0.1, 5),
        ];
    }
}
