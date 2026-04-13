<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Enums\ShareTokenMode;
use App\Models\ListActivityLog;
use App\Models\ListCollaboratorSession;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ShareTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CollaborationOwnerViewsTest extends TestCase
{
    use DatabaseTransactions;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    // === collaboratorsCount ===

    public function test_collaborators_count_returns_active_sessions(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $token = app(ShareTokenService::class)->generate($list, ShareTokenMode::Edit);
        ListCollaboratorSession::factory()->count(2)->create([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/collaborators/count");

        $response->assertOk()->assertJsonPath('data.count', 2);
    }

    public function test_collaborators_count_returns_zero_when_no_tokens(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/collaborators/count")
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    public function test_collaborators_count_excludes_stale_sessions(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $token = app(ShareTokenService::class)->generate($list, ShareTokenMode::Edit);
        ListCollaboratorSession::factory()->stale()->createOne([
            'list_share_token_id' => $token->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/collaborators/count")
            ->assertJsonPath('data.count', 0);
    }

    public function test_collaborators_count_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/collaborators/count")
            ->assertForbidden();
    }

    public function test_collaborators_count_requires_auth(): void
    {
        $list = ShoppingList::factory()->createOne();

        $this->getJson("/api/lists/{$list->id}/collaborators/count")
            ->assertUnauthorized();
    }

    // === activityLog ===

    public function test_activity_log_returns_recent_entries_newest_first(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        ListActivityLog::factory()->createOne([
            'shopping_list_id' => $list->id,
            'item_name' => 'Old',
            'created_at' => now()->subMinutes(10),
        ]);
        ListActivityLog::factory()->createOne([
            'shopping_list_id' => $list->id,
            'item_name' => 'New',
            'created_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/activity");

        $response->assertOk()
            ->assertJsonPath('data.entries.0.item_name', 'New')
            ->assertJsonPath('data.entries.1.item_name', 'Old');
    }

    public function test_activity_log_exposes_actor_and_action(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        ListActivityLog::factory()->anonymous()->createOne([
            'shopping_list_id' => $list->id,
            'action' => ActivityAction::ItemChecked,
            'item_name' => 'Pan',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/activity");

        $response->assertOk()
            ->assertJsonPath('data.entries.0.actor_type', 'anonymous')
            ->assertJsonPath('data.entries.0.action', 'item_checked');
    }

    public function test_activity_log_empty_for_list_without_activity(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.entries', []);
    }

    public function test_activity_log_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/activity")
            ->assertForbidden();
    }

    public function test_activity_log_requires_auth(): void
    {
        $list = ShoppingList::factory()->createOne();

        $this->getJson("/api/lists/{$list->id}/activity")
            ->assertUnauthorized();
    }
}
