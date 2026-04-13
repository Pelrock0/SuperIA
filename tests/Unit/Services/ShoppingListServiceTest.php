<?php

namespace Tests\Unit\Services;

use App\Enums\ListStatus;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ShoppingListService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShoppingListServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ShoppingListService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShoppingListService();
    }

    // === getListsForUser ===

    public function test_get_lists_returns_grouped_by_status(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'status' => ListStatus::Active]);
        ShoppingList::factory()->archived()->createOne(['user_id' => $user->id]);

        $result = $this->service->getListsForUser($user);

        $this->assertCount(1, $result['active']);
        $this->assertCount(1, $result['archived']);
    }

    public function test_get_lists_returns_empty_for_new_user(): void
    {
        $user = User::factory()->createOne();

        $result = $this->service->getListsForUser($user);

        $this->assertCount(0, $result['active']);
        $this->assertCount(0, $result['archived']);
    }

    public function test_get_lists_orders_by_updated_at_desc(): void
    {
        $user = User::factory()->createOne();
        $old = ShoppingList::factory()->createOne(['user_id' => $user->id, 'updated_at' => now()->subDay()]);
        $new = ShoppingList::factory()->createOne(['user_id' => $user->id, 'updated_at' => now()]);

        $result = $this->service->getListsForUser($user);

        $this->assertEquals($new->id, $result['active'][0]->id);
        $this->assertEquals($old->id, $result['active'][1]->id);
    }

    // === create ===

    public function test_create_with_all_fields(): void
    {
        $user = User::factory()->createOne();

        $list = $this->service->create($user, [
            'name' => 'Test List',
            'emoji' => '🛒',
            'category' => 'supermercado',
        ]);

        $this->assertInstanceOf(ShoppingList::class, $list);
        $this->assertEquals('Test List', $list->name);
        $this->assertEquals('🛒', $list->emoji);
        $this->assertEquals(ListStatus::Active, $list->status);
    }

    public function test_create_with_name_only(): void
    {
        $user = User::factory()->createOne();

        $list = $this->service->create($user, ['name' => 'Minimal']);

        $this->assertEquals('Minimal', $list->name);
        $this->assertNull($list->emoji);
        $this->assertNull($list->category);
    }

    public function test_create_throws_at_freemium_limit(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create(['user_id' => $user->id]);

        $this->expectException(\OverflowException::class);

        $this->service->create($user, ['name' => 'Fourth']);
    }

    public function test_create_allows_when_archived_lists_exist(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(2)->create(['user_id' => $user->id]);
        ShoppingList::factory()->archived()->createOne(['user_id' => $user->id]);

        $list = $this->service->create($user, ['name' => 'Third active']);

        $this->assertNotNull($list->id);
    }

    // === update ===

    public function test_update_changes_name(): void
    {
        $list = ShoppingList::factory()->createOne(['name' => 'Old']);

        $updated = $this->service->update($list, ['name' => 'New']);

        $this->assertEquals('New', $updated->name);
    }

    // === archive ===

    public function test_archive_changes_status(): void
    {
        $list = ShoppingList::factory()->createOne();

        $archived = $this->service->archive($list);

        $this->assertEquals(ListStatus::Archived, $archived->status);
    }

    // === restore ===

    public function test_restore_changes_status_to_active(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->archived()->createOne(['user_id' => $user->id]);

        $restored = $this->service->restore($list);

        $this->assertEquals(ListStatus::Active, $restored->status);
    }

    public function test_restore_throws_at_freemium_limit(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create(['user_id' => $user->id]);
        $archived = ShoppingList::factory()->archived()->createOne(['user_id' => $user->id]);

        $this->expectException(\OverflowException::class);

        $this->service->restore($archived);
    }

    // === delete ===

    public function test_delete_removes_list(): void
    {
        $list = ShoppingList::factory()->createOne();

        $this->service->delete($list);

        $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
    }
}
