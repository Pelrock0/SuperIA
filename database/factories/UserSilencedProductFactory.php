<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSilencedProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSilencedProduct>
 */
class UserSilencedProductFactory extends Factory
{
    protected $model = UserSilencedProduct::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'producto_nombre' => fake()->words(2, true),
            'silenced_at' => now(),
        ];
    }
}
