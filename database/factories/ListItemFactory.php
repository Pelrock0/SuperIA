<?php

namespace Database\Factories;

use App\Enums\ItemUnit;
use App\Enums\ProductCategory;
use App\Models\ListItem;
use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListItem>
 */
class ListItemFactory extends Factory
{
    protected $model = ListItem::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'shopping_list_id' => ShoppingList::factory(),
            'name' => fake()->words(2, true),
            'quantity' => fake()->optional()->randomFloat(2, 0.1, 10),
            'unit' => fake()->optional()->randomElement(ItemUnit::cases()),
            'category' => fake()->optional()->randomElement(ProductCategory::cases()),
            'estimated_price' => fake()->optional()->randomFloat(2, 0.5, 20),
            'is_purchased' => false,
            'position' => 0,
        ];
    }

    public function purchased(): static
    {
        return $this->state(fn () => ['is_purchased' => true]);
    }
}
