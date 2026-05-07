<?php

namespace Tests\Unit\Services;

use App\Enums\ShareTokenMode;
use App\Models\ListCollaborator;
use App\Models\ListCollaboratorSession;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ListCollaboratorService;
use App\Support\ShareTokenContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ListCollaboratorServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ListCollaboratorService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ListCollaboratorService();
    }

    private function makeContext(User $user, ShoppingList $list, ShareTokenMode $mode = ShareTokenMode::Edit): ShareTokenContext
    {
        $token = ListShareToken::factory()->createOne([
            'shopping_list_id' => $list->id,
            'mode' => $mode,
        ]);
        return new ShareTokenContext($token, $list, $mode);
    }

    // ── linkUser ─────────────────────────────────────────────────────────────

    public function test_link_user_creates_collaborator(): void
    {
        $owner = User::factory()->createOne();
        $collab = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $ctx = $this->makeContext($collab, $list);

        $result = $this->service->linkUser($collab, $ctx);

        $this->assertInstanceOf(ListCollaborator::class, $result);
        $this->assertSame($collab->id, $result->user_id);
        $this->assertSame($list->id, $result->shopping_list_id);
        $this->assertSame(ShareTokenMode::Edit->value, $result->mode->value);
    }

    public function test_link_user_is_idempotent_and_updates_mode(): void
    {
        $owner = User::factory()->createOne();
        $collab = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);

        $this->service->linkUser($collab, $this->makeContext($collab, $list, ShareTokenMode::Edit));
        $this->service->linkUser($collab, $this->makeContext($collab, $list, ShareTokenMode::ReadOnly));

        $this->assertSame(1, ListCollaborator::where('user_id', $collab->id)->count());
        $this->assertSame(
            ShareTokenMode::ReadOnly,
            ListCollaborator::where('user_id', $collab->id)->first()->mode
        );
    }

    // ── isLinked ─────────────────────────────────────────────────────────────

    public function test_is_linked_returns_true_when_exists(): void
    {
        $owner = User::factory()->createOne();
        $collab = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $this->service->linkUser($collab, $this->makeContext($collab, $list));

        $this->assertTrue($this->service->isLinked($collab->id, $list->id));
    }

    public function test_is_linked_returns_false_when_not_exists(): void
    {
        $this->assertFalse($this->service->isLinked(999, 999));
    }

    // ── findForAccess ─────────────────────────────────────────────────────────

    public function test_find_for_access_returns_collaborator(): void
    {
        $owner = User::factory()->createOne();
        $collab = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $this->service->linkUser($collab, $this->makeContext($collab, $list));

        $found = $this->service->findForAccess($collab->id, $list->id);

        $this->assertNotNull($found);
        $this->assertSame($collab->id, $found->user_id);
    }

    public function test_find_for_access_returns_null_when_not_linked(): void
    {
        $this->assertNull($this->service->findForAccess(999, 999));
    }

    // ── collaboratedListsForUser ──────────────────────────────────────────────

    public function test_collaborated_lists_returns_lists_with_mode_and_owner(): void
    {
        $owner = User::factory()->createOne();
        $collab = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $this->service->linkUser($collab, $this->makeContext($collab, $list, ShareTokenMode::ReadOnly));

        $lists = $this->service->collaboratedListsForUser($collab);

        $this->assertCount(1, $lists);
        $this->assertSame('read_only', $lists->first()->collaborator_mode);
        $this->assertSame($owner->name, $lists->first()->owner_name);
    }

    // ── collaboratorsForList ──────────────────────────────────────────────────

    public function test_collaborators_for_list_returns_array_with_expected_keys(): void
    {
        $owner = User::factory()->createOne();
        $collab = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $this->service->linkUser($collab, $this->makeContext($collab, $list));

        $result = $this->service->collaboratorsForList($list);

        $this->assertCount(1, $result);
        $entry = $result->first();
        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('user_id', $entry);
        $this->assertArrayHasKey('name', $entry);
        $this->assertArrayHasKey('email', $entry);
        $this->assertArrayHasKey('mode', $entry);
        $this->assertArrayHasKey('linked_at', $entry);
        $this->assertSame($collab->id, $entry['user_id']);
    }

    // ── removeByToken ─────────────────────────────────────────────────────────

    public function test_remove_by_token_deletes_collaborators_for_that_token(): void
    {
        $owner = User::factory()->createOne();
        $collab = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);
        ListCollaborator::create([
            'user_id' => $collab->id,
            'shopping_list_id' => $list->id,
            'mode' => 'edit',
            'share_token_id' => $token->id,
        ]);

        $deleted = $this->service->removeByToken($token->id);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('list_collaborators', ['share_token_id' => $token->id]);
    }

    // ── linkRetroactive ───────────────────────────────────────────────────────

    public function test_link_retroactive_links_sessions_and_returns_count(): void
    {
        $owner = User::factory()->createOne();
        $newUser = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);
        $session = ListCollaboratorSession::factory()->createOne(['list_share_token_id' => $token->id]);

        $count = $this->service->linkRetroactive($newUser, [$session->session_uuid]);

        $this->assertSame(1, $count);
        $this->assertTrue($this->service->isLinked($newUser->id, $list->id));
    }

    public function test_link_retroactive_skips_revoked_tokens(): void
    {
        $owner = User::factory()->createOne();
        $newUser = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $token = ListShareToken::factory()->revoked()->createOne(['shopping_list_id' => $list->id]);
        $session = ListCollaboratorSession::factory()->createOne(['list_share_token_id' => $token->id]);

        $count = $this->service->linkRetroactive($newUser, [$session->session_uuid]);

        $this->assertSame(0, $count);
        $this->assertFalse($this->service->isLinked($newUser->id, $list->id));
    }

    public function test_link_retroactive_skips_own_lists(): void
    {
        $owner = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);
        $session = ListCollaboratorSession::factory()->createOne(['list_share_token_id' => $token->id]);

        $count = $this->service->linkRetroactive($owner, [$session->session_uuid]);

        $this->assertSame(0, $count);
    }

    public function test_link_retroactive_is_idempotent(): void
    {
        $owner = User::factory()->createOne();
        $newUser = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id]);
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);
        $session = ListCollaboratorSession::factory()->createOne(['list_share_token_id' => $token->id]);
        $uuid = $session->session_uuid;

        $this->service->linkRetroactive($newUser, [$uuid]);
        $second = $this->service->linkRetroactive($newUser, [$uuid]);

        $this->assertSame(0, $second);
        $this->assertSame(1, ListCollaborator::where('user_id', $newUser->id)->count());
    }

    public function test_link_retroactive_empty_uuids_returns_zero(): void
    {
        $user = User::factory()->createOne();

        $this->assertSame(0, $this->service->linkRetroactive($user, []));
    }
}
