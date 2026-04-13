<?php

namespace Tests\Feature;

use App\Enums\ProductCategory;
use App\Models\ListItem;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ListItemControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    /**
     * @psalm-return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model
     */
    private function createListForUser(User $user): \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
    {
        return ShoppingList::factory()->createOne(['user_id' => $user->id]);
    }

    // === INDEX ===

    public function test_index_returns_items_grouped_by_category(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'category' => ProductCategory::Bebidas, 'name' => 'Agua']);
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'category' => ProductCategory::Panaderia, 'name' => 'Pan']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/items");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['items', 'counters']]);
    }

    public function test_index_returns_empty_for_empty_list(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/items");

        $response->assertOk()
            ->assertJsonPath('data.counters.items_total', 0);
    }

    public function test_index_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = $this->createListForUser($other);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/lists/{$list->id}/items")
            ->assertForbidden();
    }

    public function test_index_requires_auth(): void
    {
        $list = ShoppingList::factory()->createOne();
        $this->getJson("/api/lists/{$list->id}/items")->assertUnauthorized();
    }

    // === STORE ===

    public function test_store_creates_item_with_all_fields(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", [
                'name' => 'Leche entera',
                'quantity' => 2,
                'unit' => 'L',
                'category' => 'lacteos_huevos',
                'estimated_price' => 1.50,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.item.name', 'Leche entera')
            ->assertJsonPath('data.counters.items_total', 1);
    }

    public function test_store_creates_item_with_name_only(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Pan']);

        $response->assertCreated()
            ->assertJsonPath('data.item.name', 'Pan')
            ->assertJsonPath('data.item.quantity', null);
    }

    public function test_store_syncs_counters(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Item 1']);
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Item 2']);

        $list->refresh();
        $this->assertEquals(2, $list->items_total);
        $this->assertEquals(0, $list->items_completed);
    }

    public function test_store_fails_with_empty_name(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => ''])
            ->assertUnprocessable();
    }

    public function test_store_fails_with_name_over_80_chars(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => str_repeat('a', 81)])
            ->assertUnprocessable();
    }

    public function test_store_fails_with_invalid_unit(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Test', 'unit' => 'invalid'])
            ->assertUnprocessable();
    }

    public function test_store_fails_with_invalid_category(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Test', 'category' => 'invalid'])
            ->assertUnprocessable();
    }

    public function test_store_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = $this->createListForUser($other);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Hack'])
            ->assertForbidden();
    }

    // === UPDATE ===

    public function test_update_changes_item_fields(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Old']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/lists/{$list->id}/items/{$item->id}", [
                'name' => 'New Name',
                'quantity' => 3,
                'unit' => 'kg',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.item.name', 'New Name')
            ->assertJsonPath('data.item.quantity', '3.00');
    }

    public function test_update_denies_item_from_other_list(): void
    {
        $user = User::factory()->createOne();
        $list1 = $this->createListForUser($user);
        $list2 = $this->createListForUser($user);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list2->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/lists/{$list1->id}/items/{$item->id}", ['name' => 'Hack'])
            ->assertNotFound();
    }

    // === TOGGLE ===

    public function test_toggle_marks_item_as_purchased(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'is_purchased' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('data.item.is_purchased', true)
            ->assertJsonPath('data.counters.items_completed', 1);
    }

    public function test_toggle_unmarks_purchased_item(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);
        $item = ListItem::factory()->purchased()->createOne(['shopping_list_id' => $list->id]);
        $list->update(['items_total' => 1, 'items_completed' => 1]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('data.item.is_purchased', false)
            ->assertJsonPath('data.counters.items_completed', 0);
    }

    public function test_toggle_creates_historial_on_purchase(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);
        $item = ListItem::factory()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Leche',
            'category' => ProductCategory::LacteosHuevos,
            'quantity' => 2,
            'unit' => 'L',
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/toggle");

        $this->assertDatabaseHas('producto_historial', [
            'user_id' => $user->id,
            'producto_nombre' => 'Leche',
            'categoria' => 'lacteos_huevos',
            'lista_id' => $list->id,
        ]);
    }

    public function test_toggle_does_not_create_historial_on_uncheck(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);
        $item = ListItem::factory()->purchased()->createOne(['shopping_list_id' => $list->id]);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/toggle");

        $this->assertEquals(0, ProductoHistorial::where('user_id', $user->id)->count());
    }

    public function test_toggle_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = $this->createListForUser($other);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id]);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/toggle")
            ->assertForbidden();
    }

    // === DESTROY ===

    public function test_destroy_deletes_item_and_syncs_counters(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id]);
        $list->update(['items_total' => 1]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list->id}/items/{$item->id}");

        $response->assertOk()
            ->assertJsonPath('data.counters.items_total', 0);

        $this->assertDatabaseMissing('list_items', ['id' => $item->id]);
    }

    public function test_destroy_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = $this->createListForUser($other);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list->id}/items/{$item->id}")
            ->assertForbidden();
    }

    // === CLEAR COMPLETED ===

    public function test_clear_completed_removes_only_purchased_items(): void
    {
        $user = User::factory()->createOne();
        $list = $this->createListForUser($user);
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'is_purchased' => false, 'name' => 'Pending']);
        ListItem::factory()->purchased()->createOne(['shopping_list_id' => $list->id, 'name' => 'Done1']);
        ListItem::factory()->purchased()->createOne(['shopping_list_id' => $list->id, 'name' => 'Done2']);
        $list->update(['items_total' => 3, 'items_completed' => 2]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list->id}/items/completed");

        $response->assertOk()
            ->assertJsonPath('data.counters.items_total', 1)
            ->assertJsonPath('data.counters.items_completed', 0);

        $this->assertDatabaseHas('list_items', ['name' => 'Pending']);
        $this->assertDatabaseMissing('list_items', ['name' => 'Done1']);
    }

    public function test_clear_completed_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = $this->createListForUser($other);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/lists/{$list->id}/items/completed")
            ->assertForbidden();
    }
}
