<?php

namespace Database\Factories;

use App\Enums\ListCategory;
use App\Enums\ListStatus;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShoppingList>
 */
class ShoppingListFactory extends Factory
{
    protected $model = ShoppingList::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'emoji' => fake()->optional()->randomElement(['🛒', '🏪', '💊', '🛍️']),
            'category' => fake()->optional()->randomElement(ListCategory::cases()),
            'status' => ListStatus::Active,
            'is_shared' => false,
            'items_total' => 0,
            'items_completed' => 0,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ListStatus::Archived,
        ]);
    }
}
