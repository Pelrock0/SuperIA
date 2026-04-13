<?php

namespace Tests\Feature;

use App\Models\AiDismissedSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CleanupDismissedSuggestionsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_runs_successfully_on_empty_table(): void
    {
        $this->artisan('ai:cleanup-dismissed-suggestions')
            ->expectsOutputToContain('Deleted 0 expired dismissed suggestion row(s).')
            ->assertSuccessful();
    }

    public function test_deletes_only_expired_rows(): void
    {
        $user = User::factory()->createOne();
        $expired = AiDismissedSuggestion::factory()->expired()->createOne([
            'user_id' => $user->id,
            'producto_nombre' => 'OldDismiss',
        ]);
        $active = AiDismissedSuggestion::factory()->createOne([
            'user_id' => $user->id,
            'producto_nombre' => 'StillDismissed',
        ]);

        $this->artisan('ai:cleanup-dismissed-suggestions')->assertSuccessful();

        $this->assertDatabaseMissing('ai_dismissed_suggestions', ['id' => $expired->id]);
        $this->assertDatabaseHas('ai_dismissed_suggestions', ['id' => $active->id]);
    }

    public function test_scopes_correctly_across_users(): void
    {
        $a = User::factory()->createOne();
        $b = User::factory()->createOne();
        AiDismissedSuggestion::factory()->expired()->createOne(['user_id' => $a->id]);
        AiDismissedSuggestion::factory()->expired()->createOne(['user_id' => $b->id]);

        $this->artisan('ai:cleanup-dismissed-suggestions')->assertSuccessful();

        $this->assertSame(0, AiDismissedSuggestion::count());
    }

    public function test_reports_count_in_output(): void
    {
        $user = User::factory()->createOne();
        AiDismissedSuggestion::factory()->expired()->count(3)->create(['user_id' => $user->id]);

        $this->artisan('ai:cleanup-dismissed-suggestions')
            ->expectsOutputToContain('Deleted 3 expired dismissed suggestion row(s).')
            ->assertSuccessful();
    }
}
