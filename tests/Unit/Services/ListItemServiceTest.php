<?php

namespace Tests\Unit\Services;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Enums\ProductCategory;
use App\Enums\ShareTokenMode;
use App\Models\ListActivityLog;
use App\Models\ListItem;
use App\Models\ListShareToken;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ListItemService;
use App\Support\ShareTokenContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ListItemServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ListItemService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ListItemService();
    }

    public function test_get_items_returns_grouped_and_counters(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'category' => ProductCategory::Bebidas]);
        ListItem::factory()->purchased()->createOne(['shopping_list_id' => $list->id, 'category' => ProductCategory::Panaderia]);
        $list->update(['items_total' => 2, 'items_completed' => 1]);

        $result = $this->service->getItemsForList($list);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('counters', $result);
        $this->assertEquals(2, $result['counters']['items_total']);
    }

    public function test_create_adds_item_and_syncs_counters(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $result = $this->service->create($list, [
            'name' => 'Manzanas',
            'quantity' => 1,
            'unit' => 'kg',
            'category' => 'frutas_verduras',
        ]);

        $this->assertEquals('Manzanas', $result['item']->name);
        $this->assertEquals(1, $result['counters']['items_total']);
    }

    public function test_update_changes_item(): void
    {
        $item = ListItem::factory()->createOne(['name' => 'Old']);

        $updated = $this->service->update($item, ['name' => 'New']);

        $this->assertEquals('New', $updated->name);
    }

    public function test_toggle_marks_purchased_and_creates_historial(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $item = ListItem::factory()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Leche',
            'is_purchased' => false,
        ]);

        $result = $this->service->togglePurchased($item, $user->id, $list->id);

        $this->assertTrue($result['item']->is_purchased);
        $this->assertEquals(1, $result['counters']['items_completed']);
        $this->assertEquals(1, ProductoHistorial::where('user_id', $user->id)->count());
    }

    public function test_toggle_unmarks_without_historial(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $item = ListItem::factory()->purchased()->createOne(['shopping_list_id' => $list->id]);
        $list->update(['items_total' => 1, 'items_completed' => 1]);

        $result = $this->service->togglePurchased($item, $user->id, $list->id);

        $this->assertFalse($result['item']->is_purchased);
        $this->assertEquals(0, $result['counters']['items_completed']);
        $this->assertEquals(0, ProductoHistorial::where('user_id', $user->id)->count());
    }

    public function test_delete_removes_item_and_syncs_counters(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id]);
        $list->update(['items_total' => 1]);

        $result = $this->service->delete($item);

        $this->assertEquals(0, $result['counters']['items_total']);
        $this->assertDatabaseMissing('list_items', ['id' => $item->id]);
    }

    public function test_clear_completed_removes_only_purchased(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'is_purchased' => false]);
        ListItem::factory()->purchased()->count(2)->create(['shopping_list_id' => $list->id]);
        $list->update(['items_total' => 3, 'items_completed' => 2]);

        $result = $this->service->clearCompleted($list);

        $this->assertEquals(1, $result['counters']['items_total']);
        $this->assertEquals(0, $result['counters']['items_completed']);
    }

    public function test_items_without_category_grouped_as_otros(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'category' => null, 'name' => 'Mystery']);

        $result = $this->service->getItemsForList($list);

        $this->assertArrayHasKey('otros', $result['items']);
    }

    // === Activity log integration ===

    public function test_create_logs_owner_activity_when_no_context(): void
    {
        $list = ShoppingList::factory()->createOne();

        $this->service->create($list, ['name' => 'Agua']);

        $this->assertDatabaseHas('list_activity_log', [
            'shopping_list_id' => $list->id,
            'actor_type' => ActorType::Owner->value,
            'action' => ActivityAction::ItemAdded->value,
            'item_name' => 'Agua',
            'list_share_token_id' => null,
        ]);
    }

    public function test_create_logs_anonymous_activity_with_context(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);
        $context = new ShareTokenContext($token, $list, ShareTokenMode::Edit);

        $this->service->create($list, ['name' => 'Pan'], $context);

        $this->assertDatabaseHas('list_activity_log', [
            'shopping_list_id' => $list->id,
            'actor_type' => ActorType::Anonymous->value,
            'action' => ActivityAction::ItemAdded->value,
            'item_name' => 'Pan',
            'list_share_token_id' => $token->id,
        ]);
    }

    public function test_toggle_logs_checked_and_unchecked(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Leche']);

        $this->service->togglePurchased($item, $user->id, $list->id);
        $this->assertDatabaseHas('list_activity_log', [
            'shopping_list_id' => $list->id,
            'action' => ActivityAction::ItemChecked->value,
        ]);

        $this->service->togglePurchased($item->fresh(), $user->id, $list->id);
        $this->assertDatabaseHas('list_activity_log', [
            'shopping_list_id' => $list->id,
            'action' => ActivityAction::ItemUnchecked->value,
        ]);
    }

    public function test_update_logs_item_edited(): void
    {
        $list = ShoppingList::factory()->createOne();
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Old']);

        $this->service->update($item, ['name' => 'New']);

        $this->assertDatabaseHas('list_activity_log', [
            'shopping_list_id' => $list->id,
            'action' => ActivityAction::ItemEdited->value,
            'item_name' => 'New',
        ]);
    }

    public function test_delete_logs_item_deleted(): void
    {
        $list = ShoppingList::factory()->createOne();
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Gone']);

        $this->service->delete($item);

        $this->assertDatabaseHas('list_activity_log', [
            'shopping_list_id' => $list->id,
            'action' => ActivityAction::ItemDeleted->value,
            'item_name' => 'Gone',
        ]);
    }

    public function test_clear_completed_logs_list_cleared(): void
    {
        $list = ShoppingList::factory()->createOne(['name' => 'Weekly']);
        ListItem::factory()->purchased()->createOne(['shopping_list_id' => $list->id]);

        $this->service->clearCompleted($list);

        $this->assertDatabaseHas('list_activity_log', [
            'shopping_list_id' => $list->id,
            'action' => ActivityAction::ListCleared->value,
            'item_name' => 'Weekly',
        ]);
    }
}
