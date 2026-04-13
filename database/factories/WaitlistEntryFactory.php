<?php

namespace Database\Factories;

use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'shopping_companion' => fake()->optional()->randomElement(['solo', 'pareja', 'familia', 'compañeros']),
            'position' => 0,
            'status' => 'pending',
        ];
    }

    public function invited(): static
    {
        return $this->state(fn () => [
            'status' => 'invited',
            'invitation_token' => hash_hmac('sha256', fake()->email() . now()->timestamp, config('app.key')),
            'invitation_sent_at' => now(),
            'invitation_expires_at' => now()->addDays(7),
        ]);
    }

    public function expiredInvitation(): static
    {
        return $this->state(fn () => [
            'status' => 'invited',
            'invitation_token' => hash_hmac('sha256', fake()->email() . now()->timestamp, config('app.key')),
            'invitation_sent_at' => now()->subDays(8),
            'invitation_expires_at' => now()->subDay(),
        ]);
    }
}
