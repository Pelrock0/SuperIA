<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResetAiDailyUsageCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_runs_successfully_on_empty_log(): void
    {
        $this->artisan('ai:reset-daily-usage')
            ->expectsOutputToContain('Pruned 0 row')
            ->assertSuccessful();
    }

    public function test_prunes_rows_older_than_90_days(): void
    {
        $user = User::factory()->createOne();
        $old = AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'created_at' => now()->subDays(100),
        ]);
        $recent = AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('ai:reset-daily-usage')->assertSuccessful();

        $this->assertDatabaseMissing('ai_usage_log', ['id' => $old->id]);
        $this->assertDatabaseHas('ai_usage_log', ['id' => $recent->id]);
    }
}
