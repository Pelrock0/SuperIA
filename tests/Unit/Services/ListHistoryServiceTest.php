<?php

namespace Tests\Unit\Services;

use App\Enums\ListStatus;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ListHistoryService;
use App\Services\ShoppingListService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ListHistoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ListHistoryService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ListHistoryService(new ShoppingListService());
    }

    // ── getHistory ────────────────────────────────────────────────────────

    public function test_get_history_returns_exactly_20_per_page_by_default(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(25)->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);

        $result = $this->service->getHistory($user);

        $this->assertSame(20, $result->perPage());
        $this->assertSame(20, $result->count());
        $this->assertSame(25, $result->total());
    }

    public function test_get_history_perpage_param_is_respected(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(10)->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);

        $result = $this->service->getHistory($user, 5);

        $this->assertSame(5, $result->perPage());
        $this->assertSame(5, $result->count());
    }

    public function test_price_total_is_null_when_no_estimated_prices(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create(['name' => 'Sin precio', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->getHistory($user);

        $this->assertNull($result->getCollection()->first()->price_total);
    }

    public function test_price_total_is_null_when_sum_is_zero(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);

        $result = $this->service->getHistory($user);

        // Zero priceTotal must produce null, not 0.00 (> 0 guard, not >= 0)
        $this->assertNull($result->getCollection()->first()->price_total);
    }

    public function test_price_total_is_float_rounded_to_2_decimals(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        // 1.05 → round(1.05, 2) = 1.05; round(1.05, 1) = 1.1 (different)
        $list->items()->create(['name' => 'A', 'estimated_price' => 1.05, 'is_purchased' => true, 'position' => 0]);

        $result = $this->service->getHistory($user);

        $priceTotal = $result->getCollection()->first()->price_total;
        $this->assertIsFloat($priceTotal);
        $this->assertSame(1.05, $priceTotal);
    }

    public function test_price_total_uses_round_not_floor_or_ceil(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        // 2.555 → round = 2.56; floor = 2.0; ceil = 3.0
        $list->items()->create(['name' => 'A', 'estimated_price' => 2.00, 'is_purchased' => true, 'position' => 0]);
        $list->items()->create(['name' => 'B', 'estimated_price' => 0.555, 'is_purchased' => true, 'position' => 1]);

        $result = $this->service->getHistory($user);

        $this->assertSame(2.56, $result->getCollection()->first()->price_total);
    }

    public function test_price_source_is_estimated_when_price_exists(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create(['name' => 'A', 'estimated_price' => 3.00, 'is_purchased' => true, 'position' => 0]);

        $result = $this->service->getHistory($user);

        $this->assertSame('estimated', $result->getCollection()->first()->price_source);
    }

    public function test_price_source_is_null_when_no_estimated_prices(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);

        $result = $this->service->getHistory($user);

        // priceTotal = 0 → price_source must be null, not 'estimated'
        $this->assertNull($result->getCollection()->first()->price_source);
    }

    // ── duplicate ─────────────────────────────────────────────────────────

    public function test_duplicate_copies_name_with_prefix(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Archived,
            'name' => 'Compra semanal',
        ]);

        $new = $this->service->duplicate($user, $list);

        $this->assertSame('Copia de Compra semanal', $new->name);
    }

    public function test_duplicate_copies_emoji_and_category(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Archived,
            'emoji' => '🛒',
            'category' => 'supermercado',
        ]);

        $new = $this->service->duplicate($user, $list);

        $this->assertSame('🛒', $new->emoji);
        $this->assertSame('supermercado', $new->category?->value ?? $new->category);
    }

    public function test_duplicate_copies_item_name_quantity_and_unit(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create([
            'name' => 'Arroz',
            'quantity' => 500,
            'unit' => 'g',
            'is_purchased' => true,
            'position' => 0,
        ]);

        $new = $this->service->duplicate($user, $list);

        $item = $new->items()->first();
        $this->assertSame('Arroz', $item->name);
        $this->assertSame(500.0, (float) $item->quantity);
        $this->assertSame('g', $item->unit?->value ?? $item->unit);
    }

    public function test_duplicate_copies_item_category(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create([
            'name' => 'Pollo',
            'category' => 'carnes_pescados',
            'is_purchased' => true,
            'position' => 0,
        ]);

        $new = $this->service->duplicate($user, $list);

        $item = $new->items()->first();
        $this->assertSame('carnes_pescados', $item->category?->value ?? $item->category);
    }

    public function test_duplicate_handles_null_unit_without_error(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create(['name' => 'Sal', 'unit' => null, 'is_purchased' => false, 'position' => 0]);

        $new = $this->service->duplicate($user, $list);

        $this->assertNull($new->items()->first()->unit);
    }

    public function test_duplicate_handles_null_category_without_error(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create(['name' => 'Sal', 'category' => null, 'is_purchased' => false, 'position' => 0]);

        $new = $this->service->duplicate($user, $list);

        $this->assertNull($new->items()->first()->category);
    }

    public function test_duplicate_assigns_sequential_positions_starting_at_zero(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create(['name' => 'A', 'is_purchased' => false, 'position' => 0]);
        $list->items()->create(['name' => 'B', 'is_purchased' => false, 'position' => 1]);
        $list->items()->create(['name' => 'C', 'is_purchased' => false, 'position' => 2]);

        $new = $this->service->duplicate($user, $list);

        $positions = $new->items()->orderBy('position')->pluck('position')->all();
        $this->assertSame([0, 1, 2], $positions);
    }

    public function test_duplicate_sets_all_items_as_not_purchased(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create(['name' => 'A', 'is_purchased' => true, 'position' => 0]);
        $list->items()->create(['name' => 'B', 'is_purchased' => true, 'position' => 1]);

        $new = $this->service->duplicate($user, $list);

        $this->assertSame(0, $new->items()->where('is_purchased', true)->count());
    }

    public function test_duplicate_sets_items_total_and_items_completed(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create(['name' => 'A', 'is_purchased' => true, 'position' => 0]);
        $list->items()->create(['name' => 'B', 'is_purchased' => true, 'position' => 1]);
        $list->items()->create(['name' => 'C', 'is_purchased' => false, 'position' => 2]);

        $new = $this->service->duplicate($user, $list);

        $this->assertSame(3, $new->items_total);
        $this->assertSame(0, $new->items_completed);
    }

    public function test_duplicate_aborts_when_user_does_not_own_list(): void
    {
        $owner = User::factory()->createOne();
        $intruder = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id, 'status' => ListStatus::Archived]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $this->service->duplicate($intruder, $list);
    }
}
