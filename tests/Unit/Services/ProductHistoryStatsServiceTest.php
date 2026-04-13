<?php

namespace Tests\Unit\Services;

use App\Models\ListItem;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ProductHistoryStatsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductHistoryStatsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ProductHistoryStatsService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductHistoryStatsService();
    }

    public function test_completed_lists_count_is_zero_when_none(): void
    {
        $user = User::factory()->createOne();

        $this->assertSame(0, $this->service->completedListsCount($user));
    }

    public function test_completed_lists_count_matches_items_total_equals_completed(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'items_total' => 5, 'items_completed' => 5]);
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'items_total' => 3, 'items_completed' => 3]);
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'items_total' => 4, 'items_completed' => 2]);

        $this->assertSame(2, $this->service->completedListsCount($user));
    }

    public function test_completed_lists_count_excludes_empty_lists(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'items_total' => 0, 'items_completed' => 0]);

        $this->assertSame(0, $this->service->completedListsCount($user));
    }

    public function test_completed_lists_count_excludes_other_users(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $other->id, 'items_total' => 5, 'items_completed' => 5]);

        $this->assertSame(0, $this->service->completedListsCount($user));
    }

    public function test_completed_list_ids_returns_array(): void
    {
        $user = User::factory()->createOne();
        $l1 = ShoppingList::factory()->createOne(['user_id' => $user->id, 'items_total' => 2, 'items_completed' => 2]);
        $l2 = ShoppingList::factory()->createOne(['user_id' => $user->id, 'items_total' => 1, 'items_completed' => 1]);
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'items_total' => 3, 'items_completed' => 1]);

        $ids = $this->service->completedListIds($user);

        $this->assertContains($l1->id, $ids);
        $this->assertContains($l2->id, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_has_active_list_with_min_items_true_when_meets_threshold(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'status' => 'active', 'items_total' => 5]);

        $this->assertTrue($this->service->hasActiveListWithMinItems($user, 3));
    }

    public function test_has_active_list_with_min_items_false_when_below(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'status' => 'active', 'items_total' => 2]);

        $this->assertFalse($this->service->hasActiveListWithMinItems($user, 3));
    }

    public function test_has_active_list_with_min_items_excludes_archived(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'status' => 'archived', 'items_total' => 10]);

        $this->assertFalse($this->service->hasActiveListWithMinItems($user, 3));
    }

    public function test_distinct_product_count(): void
    {
        $user = User::factory()->createOne();
        ProductoHistorial::create(['user_id' => $user->id, 'producto_nombre' => 'Leche', 'fecha_compra' => now()]);
        ProductoHistorial::create(['user_id' => $user->id, 'producto_nombre' => 'Leche', 'fecha_compra' => now()]);
        ProductoHistorial::create(['user_id' => $user->id, 'producto_nombre' => 'Pan', 'fecha_compra' => now()]);

        $this->assertSame(2, $this->service->distinctProductCount($user));
    }
}
