<?php

namespace Database\Factories;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Models\ListActivityLog;
use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListActivityLog>
 */
class ListActivityLogFactory extends Factory
{
    protected $model = ListActivityLog::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'shopping_list_id' => ShoppingList::factory(),
            'list_share_token_id' => null,
            'actor_type' => ActorType::Owner,
            'action' => ActivityAction::ItemAdded,
            'item_name' => fake()->words(2, true),
            'created_at' => now(),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn () => ['actor_type' => ActorType::Anonymous]);
    }
}
