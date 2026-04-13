<?php

namespace Tests\Feature;

use App\Enums\ShareTokenMode;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ShareTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ShareTokenControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    public function test_store_generates_edit_token(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/share", ['mode' => 'edit']);

        $response->assertCreated()
            ->assertJsonPath('data.token.mode', 'edit')
            ->assertJsonStructure(['data' => ['token' => ['id', 'mode', 'url', 'created_at']]]);
        $this->assertTrue($list->fresh()->is_shared);
    }

    public function test_store_generates_read_only_token(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/share", ['mode' => 'read_only']);

        $response->assertCreated()
            ->assertJsonPath('data.token.mode', 'read_only');
    }

    public function test_store_rejects_invalid_mode(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/share", ['mode' => 'admin'])
            ->assertUnprocessable();
    }

    public function test_store_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/share", ['mode' => 'edit'])
            ->assertForbidden();
    }

    public function test_store_requires_auth(): void
    {
        $list = ShoppingList::factory()->createOne();

        $this->postJson("/api/lists/{$list->id}/share", ['mode' => 'edit'])
            ->assertUnauthorized();
    }

    public function test_index_returns_active_tokens_only(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $service = app(ShareTokenService::class);
        $active = $service->generate($list, ShareTokenMode::Edit);
        $revoked = $service->generate($list, ShareTokenMode::ReadOnly);
        $service->revoke($revoked);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/share");

        $response->assertOk()
            ->assertJsonCount(1, 'data.tokens')
            ->assertJsonPath('data.tokens.0.id', $active->id);
    }

    public function test_index_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/share")
            ->assertForbidden();
    }

    public function test_destroy_revokes_token(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $token = app(ShareTokenService::class)->generate($list, ShareTokenMode::Edit);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list->id}/share/{$token->id}");

        $response->assertOk();
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_destroy_unflags_is_shared_when_no_active_tokens_remain(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $token = app(ShareTokenService::class)->generate($list, ShareTokenMode::Edit);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list->id}/share/{$token->id}");

        $this->assertFalse($list->fresh()->is_shared);
    }

    public function test_destroy_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);
        $token = app(ShareTokenService::class)->generate($list, ShareTokenMode::Edit);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list->id}/share/{$token->id}")
            ->assertForbidden();
    }

    public function test_destroy_404_when_token_belongs_to_different_list(): void
    {
        $user = User::factory()->createOne();
        $list1 = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $list2 = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $token = app(ShareTokenService::class)->generate($list2, ShareTokenMode::Edit);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list1->id}/share/{$token->id}")
            ->assertNotFound();
    }

    public function test_free_user_can_share_list(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/share", ['mode' => 'edit'])
            ->assertCreated();
    }
}
