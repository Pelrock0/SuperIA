<?php

namespace Tests\Unit\Services;

use App\Enums\ShareTokenMode;
use App\Models\ListCollaboratorSession;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Services\CollaboratorPresenceService;
use App\Support\ShareTokenContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CollaboratorPresenceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CollaboratorPresenceService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new CollaboratorPresenceService();
    }

    private function context(ListShareToken $token): ShareTokenContext
    {
        return new ShareTokenContext($token, $token->shoppingList, $token->mode);
    }

    public function test_heartbeat_creates_session_on_first_call(): void
    {
        $token = ListShareToken::factory()->createOne();
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $session = $this->service->heartbeat($this->context($token), $uuid);

        $this->assertEquals($token->id, $session->list_share_token_id);
        $this->assertEquals($uuid, $session->session_uuid);
    }

    public function test_heartbeat_updates_existing_session(): void
    {
        $token = ListShareToken::factory()->createOne();
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $first = $this->service->heartbeat($this->context($token), $uuid);
        $originalTime = $first->last_heartbeat_at;

        sleep(1);

        $second = $this->service->heartbeat($this->context($token), $uuid);

        $this->assertEquals($first->id, $second->id);
        $this->assertTrue($second->last_heartbeat_at->gt($originalTime));
    }

    public function test_count_active_includes_recent_sessions(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);
        ListCollaboratorSession::factory()->count(3)->create([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now(),
        ]);

        $count = $this->service->countActive($list);

        $this->assertEquals(3, $count);
    }

    public function test_count_active_excludes_stale_sessions(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);
        ListCollaboratorSession::factory()->createOne([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now(),
        ]);
        ListCollaboratorSession::factory()->createOne([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now()->subSeconds(45),
        ]);

        $count = $this->service->countActive($list);

        $this->assertEquals(1, $count);
    }

    public function test_count_active_excludes_revoked_token_sessions(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = ListShareToken::factory()->revoked()->createOne(['shopping_list_id' => $list->id]);
        ListCollaboratorSession::factory()->createOne([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now(),
        ]);

        $count = $this->service->countActive($list);

        $this->assertEquals(0, $count);
    }

    public function test_count_active_is_zero_for_list_without_tokens(): void
    {
        $list = ShoppingList::factory()->createOne();

        $this->assertEquals(0, $this->service->countActive($list));
    }

    public function test_count_active_is_cached(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);
        ListCollaboratorSession::factory()->createOne([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now(),
        ]);

        $first = $this->service->countActive($list);
        ListCollaboratorSession::where('list_share_token_id', $token->id)->delete();
        $second = $this->service->countActive($list);

        $this->assertEquals(1, $first);
        $this->assertEquals(1, $second);
    }

    public function test_heartbeat_invalidates_cache(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);

        $this->assertEquals(0, $this->service->countActive($list));

        $this->service->heartbeat($this->context($token), (string) \Illuminate\Support\Str::uuid());

        $this->assertEquals(1, $this->service->countActive($list));
    }

    public function test_delete_stale_removes_old_sessions(): void
    {
        $token = ListShareToken::factory()->createOne();
        ListCollaboratorSession::factory()->createOne([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now(),
        ]);
        ListCollaboratorSession::factory()->createOne([
            'list_share_token_id' => $token->id,
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        $deleted = $this->service->deleteStale();

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, ListCollaboratorSession::count());
    }
}
