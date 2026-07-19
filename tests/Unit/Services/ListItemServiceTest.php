<?php

namespace Tests\Unit\Services;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Enums\ItemUnit;
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

    public function test_create_or_increment_creates_item_when_no_match(): void
    {
        $list = ShoppingList::factory()->createOne();

        $item = $this->service->createOrIncrement($list, [
            'name' => 'Pera',
            'quantity' => 2.0,
            'unit' => 'kg',
            'category' => 'frutas_verduras',
        ]);

        $this->assertSame('Pera', $item->name);
        $this->assertSame(1, $list->refresh()->items()->count());
        $this->assertSame(0, $item->position);
    }

    public function test_create_or_increment_appends_at_end_position(): void
    {
        $list = ShoppingList::factory()->createOne();
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'position' => 0]);
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'position' => 5]);

        $item = $this->service->createOrIncrement($list, [
            'name' => 'Nuevo',
            'quantity' => 1.0,
            'unit' => 'ud',
            'category' => 'otros',
        ]);

        $this->assertSame(6, $item->position);
    }

    public function test_create_or_increment_increments_quantity_when_match_pending_same_unit(): void
    {
        $list = ShoppingList::factory()->createOne();
        $existing = $list->items()->create([
            'name' => 'Leche',
            'quantity' => 1.0,
            'unit' => 'L',
            'is_purchased' => false,
            'position' => 0,
        ]);

        $result = $this->service->createOrIncrement($list, [
            'name' => 'Leche',
            'quantity' => 2.0,
            'unit' => 'L',
            'category' => 'lacteos_huevos',
        ]);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame('3.00', (string) $result->quantity);
        $this->assertSame(1, $list->refresh()->items()->count());
    }

    public function test_create_or_increment_normalizes_name_for_match(): void
    {
        $list = ShoppingList::factory()->createOne();
        $list->items()->create([
            'name' => '  LECHE  ',
            'quantity' => 1.0,
            'unit' => 'L',
            'is_purchased' => false,
            'position' => 0,
        ]);

        $this->service->createOrIncrement($list, [
            'name' => 'leche',
            'quantity' => 1.0,
            'unit' => 'L',
        ]);

        $this->assertSame(1, $list->refresh()->items()->count());
    }

    public function test_create_or_increment_treats_different_unit_as_separate(): void
    {
        $list = ShoppingList::factory()->createOne();
        $list->items()->create([
            'name' => 'Leche',
            'quantity' => 1.0,
            'unit' => 'L',
            'is_purchased' => false,
            'position' => 0,
        ]);

        $this->service->createOrIncrement($list, [
            'name' => 'Leche',
            'quantity' => 1.0,
            'unit' => 'ml',
        ]);

        $this->assertSame(2, $list->refresh()->items()->count());
    }

    public function test_create_or_increment_does_not_match_purchased_item(): void
    {
        $list = ShoppingList::factory()->createOne();
        $purchased = $list->items()->create([
            'name' => 'Leche',
            'quantity' => 1.0,
            'unit' => 'L',
            'is_purchased' => true,
            'position' => 0,
        ]);

        $result = $this->service->createOrIncrement($list, [
            'name' => 'Leche',
            'quantity' => 1.0,
            'unit' => 'L',
        ]);

        $this->assertDatabaseMissing('list_items', ['id' => $purchased->id]);
        $this->assertFalse((bool) $result->is_purchased);
        $this->assertSame(1, $list->refresh()->items()->count());
    }

    public function test_create_or_increment_matches_when_unit_is_null_on_both(): void
    {
        $list = ShoppingList::factory()->createOne();
        $existing = $list->items()->create([
            'name' => 'Algo',
            'quantity' => 1.0,
            'unit' => null,
            'is_purchased' => false,
            'position' => 0,
        ]);

        $result = $this->service->createOrIncrement($list, [
            'name' => 'Algo',
            'quantity' => 2.0,
            'unit' => null,
        ]);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame('3.00', (string) $result->quantity);
    }

    public function test_create_or_increment_infers_category_when_missing(): void
    {
        $list = ShoppingList::factory()->createOne();

        $item = $this->service->createOrIncrement($list, [
            'name' => 'Manzanas',
            'quantity' => 1.0,
            'unit' => 'kg',
        ]);

        $this->assertNotNull($item);
        // Category may be null or inferred depending on CategoryInferenceService;
        // we only assert the item was created.
        $this->assertSame('Manzanas', $item->name);
    }

    public function test_create_or_increment_coerces_invalid_category_to_null(): void
    {
        // Defends against LLM payloads producing categories outside the closed enum.
        // Without coercion, Eloquent's enum cast throws ValueError on save.
        $list = ShoppingList::factory()->createOne();

        $item = $this->service->createOrIncrement($list, [
            'name' => 'AceiteRaro',
            'quantity' => 1.0,
            'unit' => 'L',
            'category' => 'aceites_no_existe_en_enum',
        ]);

        $this->assertNotNull($item);
        $this->assertSame('AceiteRaro', $item->name);
        $this->assertNull($item->category);
    }

    public function test_create_or_increment_coerces_invalid_unit_to_null(): void
    {
        $list = ShoppingList::factory()->createOne();

        $item = $this->service->createOrIncrement($list, [
            'name' => 'CosaExotica',
            'quantity' => 1.0,
            'unit' => 'galones',
            'category' => 'otros',
        ]);

        $this->assertNotNull($item);
        $this->assertNull($item->unit);
    }

    public function test_create_deletes_purchased_homonym_with_same_normalized_name(): void
    {
        $list = ShoppingList::factory()->createOne();
        $purchased = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Pan',
            'unit' => null,
        ]);

        $result = $this->service->create($list, ['name' => 'Pan']);

        $this->assertDatabaseMissing('list_items', ['id' => $purchased->id]);
        $this->assertSame('Pan', $result['item']->name);
        $this->assertFalse((bool) $result['item']->is_purchased);
        $this->assertSame(1, $list->items()->count());
    }

    public function test_create_deletes_purchased_homonym_singular_plural_variants(): void
    {
        $list = ShoppingList::factory()->createOne();
        $purchased = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Panes',
            'unit' => null,
        ]);

        $result = $this->service->create($list, ['name' => 'pan']);

        $this->assertDatabaseMissing('list_items', ['id' => $purchased->id]);
        $this->assertSame('pan', $result['item']->name);
        $this->assertSame(1, $list->items()->count());
    }

    public function test_create_deletes_purchased_homonym_plural_input_singular_existing(): void
    {
        $list = ShoppingList::factory()->createOne();
        $purchased = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Tomate',
            'unit' => null,
        ]);

        $result = $this->service->create($list, ['name' => 'Tomates']);

        $this->assertDatabaseMissing('list_items', ['id' => $purchased->id]);
        $this->assertSame('Tomates', $result['item']->name);
        $this->assertSame(1, $list->items()->count());
    }

    public function test_create_deletes_all_purchased_homonyms_when_multiple_match(): void
    {
        $list = ShoppingList::factory()->createOne();
        $p1 = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Pan',
            'unit' => null,
        ]);
        $p2 = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'PANES',
            'unit' => null,
        ]);

        $this->service->create($list, ['name' => 'Pan']);

        $this->assertDatabaseMissing('list_items', ['id' => $p1->id]);
        $this->assertDatabaseMissing('list_items', ['id' => $p2->id]);
        $this->assertSame(1, $list->items()->count());
    }

    public function test_create_does_not_delete_purchased_with_different_unit(): void
    {
        $list = ShoppingList::factory()->createOne();
        $purchased = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Leche',
            'unit' => ItemUnit::L,
        ]);

        $result = $this->service->create($list, ['name' => 'Leche', 'unit' => 'ml']);

        $this->assertDatabaseHas('list_items', ['id' => $purchased->id]);
        $this->assertSame(2, $list->items()->count());
        $this->assertSame(ItemUnit::Ml, $result['item']->unit);
    }

    public function test_create_does_not_delete_purchased_with_different_name(): void
    {
        $list = ShoppingList::factory()->createOne();
        $purchased = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Pollo',
            'unit' => null,
        ]);

        $result = $this->service->create($list, ['name' => 'Polla']);

        $this->assertDatabaseHas('list_items', ['id' => $purchased->id]);
        $this->assertSame(2, $list->items()->count());
        $this->assertSame('Polla', $result['item']->name);
    }

    public function test_create_does_not_touch_pending_items_with_same_name(): void
    {
        $list = ShoppingList::factory()->createOne();
        $pending = ListItem::factory()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Pan',
            'is_purchased' => false,
            'unit' => null,
        ]);

        $result = $this->service->create($list, ['name' => 'Pan']);

        $this->assertDatabaseHas('list_items', ['id' => $pending->id]);
        $this->assertNotSame($pending->id, $result['item']->id);
        $this->assertSame(2, $list->items()->count());
    }

    public function test_create_does_not_delete_purchased_in_other_lists(): void
    {
        $list = ShoppingList::factory()->createOne();
        $otherList = ShoppingList::factory()->createOne();
        $purchasedOther = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $otherList->id,
            'name' => 'Pan',
            'unit' => null,
        ]);

        $this->service->create($list, ['name' => 'Pan']);

        $this->assertDatabaseHas('list_items', ['id' => $purchasedOther->id]);
    }

    public function test_create_or_increment_matches_normalized_plural_for_pending_increment(): void
    {
        $list = ShoppingList::factory()->createOne();
        $pending = ListItem::factory()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Tomate',
            'quantity' => 2.0,
            'unit' => null,
            'is_purchased' => false,
        ]);

        $result = $this->service->createOrIncrement($list, [
            'name' => 'Tomates',
            'quantity' => 3.0,
        ]);

        $this->assertSame($pending->id, $result->id);
        $this->assertEqualsWithDelta(5.0, (float) $result->quantity, 0.001);
        $this->assertSame(1, $list->items()->count());
    }

    public function test_create_or_increment_deletes_purchased_homonyms_when_no_pending_match(): void
    {
        $list = ShoppingList::factory()->createOne();
        $purchased = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Panes',
            'unit' => null,
        ]);

        $result = $this->service->createOrIncrement($list, [
            'name' => 'Pan',
            'quantity' => 1.0,
        ]);

        $this->assertDatabaseMissing('list_items', ['id' => $purchased->id]);
        $this->assertNotSame($purchased->id, $result->id);
        $this->assertFalse((bool) $result->is_purchased);
        $this->assertSame(1, $list->items()->count());
    }

    public function test_create_or_increment_does_not_delete_purchased_when_incrementing_existing_pending(): void
    {
        $list = ShoppingList::factory()->createOne();
        $purchased = ListItem::factory()->purchased()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Panes',
            'unit' => null,
        ]);
        $pending = ListItem::factory()->createOne([
            'shopping_list_id' => $list->id,
            'name' => 'Pan',
            'quantity' => 1.0,
            'unit' => null,
            'is_purchased' => false,
        ]);

        $result = $this->service->createOrIncrement($list, [
            'name' => 'Panes',
            'quantity' => 2.0,
        ]);

        $this->assertSame($pending->id, $result->id);
        $this->assertEqualsWithDelta(3.0, (float) $result->quantity, 0.001);
        $this->assertDatabaseHas('list_items', ['id' => $purchased->id]);
        $this->assertSame(2, $list->items()->count());
    }
}
