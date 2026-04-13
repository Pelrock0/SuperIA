<?php

namespace Tests\Feature;

use App\Enums\ActorType;
use App\Models\ListActivityLog;
use App\Models\ListCollaboratorSession;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CleanupExpiredCollaboratorDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deletes_stale_sessions_beyond_5_minutes(): void
    {
        $token = ListShareToken::factory()->createOne();
        $fresh = ListCollaboratorSession::factory()->createOne([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now()->subMinutes(2),
        ]);
        $stale = ListCollaboratorSession::factory()->createOne([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        $this->artisan('app:cleanup-collaborator-data')->assertSuccessful();

        $this->assertDatabaseHas('list_collaborator_sessions', ['id' => $fresh->id]);
        $this->assertDatabaseMissing('list_collaborator_sessions', ['id' => $stale->id]);
    }

    public function test_deletes_anonymous_logs_older_than_30_days(): void
    {
        $list = ShoppingList::factory()->createOne();
        $old = ListActivityLog::factory()->anonymous()->createOne([
            'shopping_list_id' => $list->id,
            'created_at' => now()->subDays(31),
        ]);
        $recent = ListActivityLog::factory()->anonymous()->createOne([
            'shopping_list_id' => $list->id,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('app:cleanup-collaborator-data')->assertSuccessful();

        $this->assertDatabaseMissing('list_activity_log', ['id' => $old->id]);
        $this->assertDatabaseHas('list_activity_log', ['id' => $recent->id]);
    }

    public function test_keeps_owner_logs_older_than_30_days(): void
    {
        $list = ShoppingList::factory()->createOne();
        $ownerLog = ListActivityLog::factory()->createOne([
            'shopping_list_id' => $list->id,
            'actor_type' => ActorType::Owner,
            'created_at' => now()->subDays(60),
        ]);

        $this->artisan('app:cleanup-collaborator-data')->assertSuccessful();

        $this->assertDatabaseHas('list_activity_log', ['id' => $ownerLog->id]);
    }

    public function test_purges_logs_of_revoked_tokens_older_than_24h(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = ListShareToken::factory()->createOne([
            'shopping_list_id' => $list->id,
            'revoked_at' => now()->subDays(2),
        ]);
        $log = ListActivityLog::factory()->anonymous()->createOne([
            'shopping_list_id' => $list->id,
            'list_share_token_id' => $token->id,
            'created_at' => now()->subHours(2),
        ]);

        $this->artisan('app:cleanup-collaborator-data')->assertSuccessful();

        $this->assertDatabaseMissing('list_activity_log', ['id' => $log->id]);
    }

    public function test_keeps_logs_of_tokens_revoked_less_than_24h_ago(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = ListShareToken::factory()->createOne([
            'shopping_list_id' => $list->id,
            'revoked_at' => now()->subHours(1),
        ]);
        $log = ListActivityLog::factory()->anonymous()->createOne([
            'shopping_list_id' => $list->id,
            'list_share_token_id' => $token->id,
            'created_at' => now()->subMinutes(30),
        ]);

        $this->artisan('app:cleanup-collaborator-data')->assertSuccessful();

        $this->assertDatabaseHas('list_activity_log', ['id' => $log->id]);
    }

    public function test_reports_counts_in_output(): void
    {
        $this->artisan('app:cleanup-collaborator-data')
            ->expectsOutputToContain('Deleted 0 stale session(s).')
            ->expectsOutputToContain('Deleted 0 expired anonymous log entr(ies).')
            ->expectsOutputToContain('Purged 0 log entr(ies) tied to revoked tokens.')
            ->assertSuccessful();
    }
}
