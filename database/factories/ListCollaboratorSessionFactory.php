<?php

namespace Database\Factories;

use App\Models\ListCollaboratorSession;
use App\Models\ListShareToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ListCollaboratorSession>
 */
class ListCollaboratorSessionFactory extends Factory
{
    protected $model = ListCollaboratorSession::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'list_share_token_id' => ListShareToken::factory(),
            'session_uuid' => (string) Str::uuid(),
            'last_heartbeat_at' => now(),
            'created_at' => now(),
        ];
    }

    public function stale(): static
    {
        return $this->state(fn () => [
            'last_heartbeat_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
        ]);
    }
}
