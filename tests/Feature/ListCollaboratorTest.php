<?php

namespace Tests\Feature;

use App\Enums\ShareTokenMode;
use App\Models\ListCollaborator;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ShareTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ListCollaboratorTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    private function createSharedList(User $owner, ShareTokenMode $mode = ShareTokenMode::Edit): array
    {
        $list = ShoppingList::factory()->for($owner)->create();
        $token = app(ShareTokenService::class)->generate($list, $mode);
        $url = app(ShareTokenService::class)->urlFor($token);
        $tokenParam = basename($url);

        return [$list, $token, $tokenParam];
    }

    // --- AC-1 & AC-2: Save to account ---

    public function test_authenticated_user_can_save_shared_list(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        [$list, $token, $tokenParam] = $this->createSharedList($owner);

        $response = $this->withHeaders($this->authHeaders($collaborator))
            ->postJson("/api/shared/{$tokenParam}/save");

        $response->assertCreated()
            ->assertJsonPath('data.linked', true)
            ->assertJsonPath('data.mode', 'edit');

        $this->assertDatabaseHas('list_collaborators', [
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
            'mode' => 'edit',
            'share_token_id' => $token->id,
        ]);
    }

    public function test_save_requires_authentication(): void
    {
        $owner = User::factory()->create();
        [, , $tokenParam] = $this->createSharedList($owner);

        $response = $this->postJson("/api/shared/{$tokenParam}/save");

        $response->assertStatus(401);
    }

    public function test_owner_cannot_save_own_list(): void
    {
        $owner = User::factory()->create();
        [, , $tokenParam] = $this->createSharedList($owner);

        $response = $this->withHeaders($this->authHeaders($owner))
            ->postJson("/api/shared/{$tokenParam}/save");

        $response->assertStatus(409);
    }

    public function test_save_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        [, , $tokenParam] = $this->createSharedList($owner);

        $this->withHeaders($this->authHeaders($collaborator))
            ->postJson("/api/shared/{$tokenParam}/save")
            ->assertCreated();

        $this->withHeaders($this->authHeaders($collaborator))
            ->postJson("/api/shared/{$tokenParam}/save")
            ->assertCreated();

        $this->assertEquals(
            1,
            ListCollaborator::where('user_id', $collaborator->id)->count()
        );
    }

    public function test_read_only_token_saves_read_only_mode(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        [, , $tokenParam] = $this->createSharedList($owner, ShareTokenMode::ReadOnly);

        $this->withHeaders($this->authHeaders($collaborator))
            ->postJson("/api/shared/{$tokenParam}/save")
            ->assertCreated()
            ->assertJsonPath('data.mode', 'read_only');
    }

    // --- AC-2: Save status ---

    public function test_save_status_returns_linked_state(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        [$list, , $tokenParam] = $this->createSharedList($owner);

        // Not linked yet
        $this->withHeaders($this->authHeaders($collaborator))
            ->getJson("/api/shared/{$tokenParam}/save-status")
            ->assertOk()
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.linked', false)
            ->assertJsonPath('data.is_owner', false);

        // Link
        ListCollaborator::create([
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
            'mode' => 'edit',
        ]);

        // Now linked
        $this->withHeaders($this->authHeaders($collaborator))
            ->getJson("/api/shared/{$tokenParam}/save-status")
            ->assertOk()
            ->assertJsonPath('data.linked', true);
    }

    public function test_save_status_detects_owner(): void
    {
        $owner = User::factory()->create();
        [, , $tokenParam] = $this->createSharedList($owner);

        $this->withHeaders($this->authHeaders($owner))
            ->getJson("/api/shared/{$tokenParam}/save-status")
            ->assertOk()
            ->assertJsonPath('data.is_owner', true);
    }

    public function test_save_status_unauthenticated(): void
    {
        $owner = User::factory()->create();
        [, , $tokenParam] = $this->createSharedList($owner);

        $this->getJson("/api/shared/{$tokenParam}/save-status")
            ->assertOk()
            ->assertJsonPath('data.authenticated', false);
    }

    // --- AC-3: Dashboard shows collaborated lists ---

    public function test_lists_index_includes_collaborated(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $ownList = ShoppingList::factory()->for($collaborator)->create();
        $sharedList = ShoppingList::factory()->for($owner)->create();

        ListCollaborator::create([
            'user_id' => $collaborator->id,
            'shopping_list_id' => $sharedList->id,
            'mode' => 'edit',
        ]);

        $response = $this->withHeaders($this->authHeaders($collaborator))
            ->getJson('/api/lists');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data['active']);
        $this->assertCount(1, $data['collaborated']);
        $this->assertEquals($sharedList->id, $data['collaborated'][0]['id']);
    }

    // --- AC-4: Collaborator can access list items ---

    public function test_collaborator_can_read_items(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $list = ShoppingList::factory()->for($owner)->create();
        $list->items()->create(['name' => 'Leche']);

        ListCollaborator::create([
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
            'mode' => 'edit',
        ]);

        $this->withHeaders($this->authHeaders($collaborator))
            ->getJson("/api/lists/{$list->id}/items")
            ->assertOk();
    }

    public function test_collaborator_edit_can_add_items(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $list = ShoppingList::factory()->for($owner)->create();

        ListCollaborator::create([
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
            'mode' => 'edit',
        ]);

        $this->withHeaders($this->authHeaders($collaborator))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Pan'])
            ->assertCreated();
    }

    public function test_collaborator_read_only_cannot_add_items(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $list = ShoppingList::factory()->for($owner)->create();

        ListCollaborator::create([
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
            'mode' => 'read_only',
        ]);

        $this->withHeaders($this->authHeaders($collaborator))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Pan'])
            ->assertForbidden();
    }

    public function test_non_collaborator_cannot_access_list(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $list = ShoppingList::factory()->for($owner)->create();

        $this->withHeaders($this->authHeaders($stranger))
            ->getJson("/api/lists/{$list->id}/items")
            ->assertForbidden();
    }

    // --- AC-5: Cascade revocation ---

    public function test_revoking_token_removes_collaborators(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        [$list, $token] = $this->createSharedList($owner);

        ListCollaborator::create([
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
            'mode' => 'edit',
            'share_token_id' => $token->id,
        ]);

        app(ShareTokenService::class)->revoke($token);

        $this->assertDatabaseMissing('list_collaborators', [
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
        ]);
    }

    // --- AC-6: Owner can see collaborators ---

    public function test_owner_can_list_collaborators(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $list = ShoppingList::factory()->for($owner)->create();

        ListCollaborator::create([
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
            'mode' => 'edit',
        ]);

        $response = $this->withHeaders($this->authHeaders($owner))
            ->getJson("/api/lists/{$list->id}/collaborators");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($collaborator->name, $data[0]['name']);
        $this->assertEquals('edit', $data[0]['mode']);
    }

    public function test_non_owner_cannot_list_collaborators(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $list = ShoppingList::factory()->for($owner)->create();

        $this->withHeaders($this->authHeaders($other))
            ->getJson("/api/lists/{$list->id}/collaborators")
            ->assertForbidden();
    }

    // --- AC-10: Permission enforcement ---

    public function test_read_only_collaborator_cannot_delete_items(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $list = ShoppingList::factory()->for($owner)->create();
        $item = $list->items()->create(['name' => 'Leche']);

        ListCollaborator::create([
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
            'mode' => 'read_only',
        ]);

        $this->withHeaders($this->authHeaders($collaborator))
            ->deleteJson("/api/lists/{$list->id}/items/{$item->id}")
            ->assertForbidden();
    }

    public function test_read_only_collaborator_cannot_increment_quantity(): void
    {
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();
        $list = ShoppingList::factory()->for($owner)->create();
        $item = $list->items()->create(['name' => 'Leche', 'quantity' => 1]);

        ListCollaborator::create([
            'user_id' => $collaborator->id,
            'shopping_list_id' => $list->id,
            'mode' => 'read_only',
        ]);

        $this->withHeaders($this->authHeaders($collaborator))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/increment-quantity", ['quantity' => 1])
            ->assertForbidden();
    }
}
