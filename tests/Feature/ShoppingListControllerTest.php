<?php

namespace Tests\Feature;

use App\Enums\ListStatus;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ShoppingListControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    // === INDEX ===

    public function test_index_returns_active_and_archived_lists(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'name' => 'Active List']);
        ShoppingList::factory()->archived()->createOne(['user_id' => $user->id, 'name' => 'Archived List']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/lists');

        $response->assertOk()
            ->assertJsonCount(1, 'data.active')
            ->assertJsonCount(1, 'data.archived')
            ->assertJsonPath('data.active.0.name', 'Active List')
            ->assertJsonPath('data.archived.0.name', 'Archived List');
    }

    public function test_index_returns_empty_when_no_lists(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/lists');

        $response->assertOk()
            ->assertJsonCount(0, 'data.active')
            ->assertJsonCount(0, 'data.archived');
    }

    public function test_index_does_not_return_other_users_lists(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/lists');

        $response->assertOk()
            ->assertJsonCount(0, 'data.active');
    }

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/lists')->assertUnauthorized();
    }

    // === STORE ===

    public function test_store_creates_list_with_all_fields(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/lists', [
                'name' => 'Compra semanal',
                'emoji' => '🛒',
                'category' => 'supermercado',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Compra semanal')
            ->assertJsonPath('data.emoji', '🛒')
            ->assertJsonPath('data.category', 'supermercado');

        $this->assertDatabaseHas('shopping_lists', [
            'user_id' => $user->id,
            'name' => 'Compra semanal',
        ]);
    }

    public function test_store_creates_list_with_name_only(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/lists', ['name' => 'Mi lista']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Mi lista')
            ->assertJsonPath('data.emoji', null)
            ->assertJsonPath('data.category', null);
    }

    public function test_store_fails_at_freemium_limit(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/lists', ['name' => 'Fourth list']);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FREEMIUM_LIMIT');
    }

    public function test_store_fails_with_empty_name(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/lists', ['name' => '']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_fails_with_name_over_60_chars(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/lists', ['name' => str_repeat('a', 61)]);

        $response->assertUnprocessable();
    }

    public function test_store_fails_with_invalid_category(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/lists', ['name' => 'Test', 'category' => 'invalid']);

        $response->assertUnprocessable();
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/lists', ['name' => 'Test'])->assertUnauthorized();
    }

    // === SHOW ===

    public function test_show_returns_list(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $list->id);
    }

    public function test_show_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}");

        $response->assertForbidden();
    }

    // === UPDATE ===

    public function test_update_changes_name(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id, 'name' => 'Old']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/lists/{$list->id}", ['name' => 'New Name']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_update_changes_emoji_and_category(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/lists/{$list->id}", ['emoji' => '🏪', 'category' => 'mercado']);

        $response->assertOk()
            ->assertJsonPath('data.emoji', '🏪')
            ->assertJsonPath('data.category', 'mercado');
    }

    public function test_update_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/lists/{$list->id}", ['name' => 'Hacked']);

        $response->assertForbidden();
    }

    // === ARCHIVE ===

    public function test_archive_changes_status(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/archive");

        $response->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    public function test_archive_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/archive");

        $response->assertForbidden();
    }

    // === RESTORE ===

    public function test_restore_changes_status_to_active(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->archived()->createOne(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/restore");

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_restore_fails_at_freemium_limit(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create(['user_id' => $user->id]);
        $archived = ShoppingList::factory()->archived()->createOne(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$archived->id}/restore");

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FREEMIUM_LIMIT');
    }

    // === DESTROY ===

    public function test_destroy_deletes_list(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list->id}");

        $response->assertOk()
            ->assertJsonPath('data.message', 'Lista eliminada correctamente.');

        $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
    }

    public function test_destroy_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list->id}");

        $response->assertForbidden();
    }

    public function test_destroy_requires_auth(): void
    {
        $list = ShoppingList::factory()->createOne();

        $this->deleteJson("/api/lists/{$list->id}")->assertUnauthorized();
    }
}
