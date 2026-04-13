<?php

namespace Database\Factories;

use App\Enums\ShareTokenMode;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ListShareToken>
 */
class ListShareTokenFactory extends Factory
{
    protected $model = ListShareToken::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'shopping_list_id' => ShoppingList::factory(),
            'token_id' => (string) Str::uuid(),
            'mode' => ShareTokenMode::Edit,
            'revoked_at' => null,
        ];
    }

    public function readOnly(): static
    {
        return $this->state(fn () => ['mode' => ShareTokenMode::ReadOnly]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
