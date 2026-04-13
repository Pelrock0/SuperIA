<?php

namespace Database\Factories;

use App\Models\AiDismissedSuggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiDismissedSuggestion>
 */
class AiDismissedSuggestionFactory extends Factory
{
    protected $model = AiDismissedSuggestion::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'producto_nombre' => fake()->words(2, true),
            'dismissed_until' => now()->addHours(24),
            'created_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['dismissed_until' => now()->subHours(1)]);
    }
}
