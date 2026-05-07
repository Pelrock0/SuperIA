<?php

namespace Tests\Unit\Services;

use App\Enums\ListStatus;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private StatsService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StatsService();
    }

    // ── getStats ──────────────────────────────────────────────────────────

    public function test_monthly_spend_is_filled_when_enough_data(): void
    {
        $user = $this->userWithArchivedLists(3);
        $list = $user->shoppingLists()->where('status', ListStatus::Archived)->first();
        $list->items()->create(['name' => 'Leche', 'estimated_price' => 2.00, 'is_purchased' => true, 'position' => 0]);

        $stats = $this->service->getStats($user);

        $this->assertNotEmpty($stats['monthly_spend']);
    }

    public function test_top_categories_is_filled_when_enough_data(): void
    {
        $user = $this->userWithArchivedLists(3);
        $this->insertHistorial($user->id, 'Leche', 'lacteos_huevos');

        $stats = $this->service->getStats($user);

        $this->assertNotEmpty($stats['top_categories']);
    }

    public function test_top_products_is_filled_when_enough_data(): void
    {
        $user = $this->userWithArchivedLists(3);
        $this->insertHistorial($user->id, 'Leche', 'lacteos_huevos');

        $stats = $this->service->getStats($user);

        $this->assertNotEmpty($stats['top_products']);
    }

    public function test_monthly_spend_empty_when_not_enough_data(): void
    {
        $user = $this->userWithArchivedLists(2);

        $stats = $this->service->getStats($user);

        $this->assertSame([], $stats['monthly_spend']);
        $this->assertSame([], $stats['top_categories']);
        $this->assertSame([], $stats['top_products']);
    }

    // ── monthlySpend ─────────────────────────────────────────────────────

    public function test_monthly_spend_includes_data_from_6_months_ago(): void
    {
        $user = $this->userWithArchivedLists(3);
        $list = $this->archivedListAt($user, Carbon::now()->subMonths(6)->startOfMonth()->addDays(5));
        $list->items()->create(['name' => 'Pan', 'estimated_price' => 1.50, 'is_purchased' => true, 'position' => 0]);

        $stats = $this->service->getStats($user);

        $this->assertNotEmpty($stats['monthly_spend']);
    }

    public function test_monthly_spend_excludes_data_older_than_6_months(): void
    {
        $user = $this->userWithArchivedLists(3);
        // List archived 7 months ago — must NOT appear
        $old = $this->archivedListAt($user, Carbon::now()->subMonths(7)->startOfMonth());
        $old->items()->create(['name' => 'Sal', 'estimated_price' => 0.50, 'is_purchased' => true, 'position' => 0]);

        // Ensure no recent lists have items so monthly_spend comes only from the old list
        $stats = $this->service->getStats($user);

        $months = array_column($stats['monthly_spend'], 'month');
        $oldMonth = Carbon::now()->subMonths(7)->startOfMonth()->format('Y-m');
        $this->assertNotContains($oldMonth, $months);
    }

    public function test_monthly_spend_entry_has_month_and_total_keys(): void
    {
        $user = $this->userWithArchivedLists(3);
        $list = $user->shoppingLists()->where('status', ListStatus::Archived)->first();
        $list->items()->create(['name' => 'Yogur', 'estimated_price' => 3.00, 'is_purchased' => true, 'position' => 0]);

        $stats = $this->service->getStats($user);

        $entry = $stats['monthly_spend'][0];
        $this->assertArrayHasKey('month', $entry);
        $this->assertArrayHasKey('total', $entry);
    }

    public function test_monthly_spend_total_is_float(): void
    {
        $user = $this->userWithArchivedLists(3);
        $list = $user->shoppingLists()->where('status', ListStatus::Archived)->first();
        $list->items()->create(['name' => 'Queso', 'estimated_price' => 2.50, 'is_purchased' => true, 'position' => 0]);

        $stats = $this->service->getStats($user);

        $this->assertIsFloat($stats['monthly_spend'][0]['total']);
    }

    // ── topCategories ─────────────────────────────────────────────────────

    public function test_top_categories_limits_to_5(): void
    {
        $user = $this->userWithArchivedLists(3);
        $categories = ['frutas_verduras', 'carnes_pescados', 'lacteos_huevos', 'bebidas', 'congelados', 'limpieza'];
        foreach ($categories as $cat) {
            $this->insertHistorial($user->id, "producto_{$cat}", $cat);
        }

        $stats = $this->service->getStats($user);

        $this->assertCount(5, $stats['top_categories']);
    }

    public function test_top_categories_entry_has_category_count_percentage_keys(): void
    {
        $user = $this->userWithArchivedLists(3);
        $this->insertHistorial($user->id, 'Leche', 'lacteos_huevos');

        $stats = $this->service->getStats($user);

        $entry = $stats['top_categories'][0];
        $this->assertArrayHasKey('category', $entry);
        $this->assertArrayHasKey('count', $entry);
        $this->assertArrayHasKey('percentage', $entry);
    }

    public function test_top_categories_count_is_integer(): void
    {
        $user = $this->userWithArchivedLists(3);
        $this->insertHistorial($user->id, 'Leche', 'lacteos_huevos');

        $stats = $this->service->getStats($user);

        $this->assertIsInt($stats['top_categories'][0]['count']);
    }

    public function test_top_categories_percentage_uses_division_and_rounds_to_1_decimal(): void
    {
        $user = $this->userWithArchivedLists(3);
        // 1 item with category, 2 without → total historial = 3, percentage = 1/3*100 = 33.3
        $this->insertHistorial($user->id, 'Leche', 'lacteos_huevos');
        $this->insertHistorial($user->id, 'Pan', null);
        $this->insertHistorial($user->id, 'Sal', null);

        $stats = $this->service->getStats($user);

        $this->assertSame(33.3, $stats['top_categories'][0]['percentage']);
    }

    public function test_top_categories_returns_empty_when_no_historial(): void
    {
        $user = $this->userWithArchivedLists(3);
        // No historial rows — total === 0 guard must return []

        $stats = $this->service->getStats($user);

        $this->assertSame([], $stats['top_categories']);
    }

    // ── topProducts ───────────────────────────────────────────────────────

    public function test_top_products_limits_to_exactly_10(): void
    {
        $user = $this->userWithArchivedLists(3);
        for ($i = 1; $i <= 12; $i++) {
            $this->insertHistorial($user->id, "Producto{$i}", null);
        }

        $stats = $this->service->getStats($user);

        $this->assertCount(10, $stats['top_products']);
    }

    public function test_top_products_entry_has_name_and_count_keys(): void
    {
        $user = $this->userWithArchivedLists(3);
        $this->insertHistorial($user->id, 'Arroz', null);

        $stats = $this->service->getStats($user);

        $entry = $stats['top_products'][0];
        $this->assertArrayHasKey('name', $entry);
        $this->assertArrayHasKey('count', $entry);
    }

    public function test_top_products_count_is_integer(): void
    {
        $user = $this->userWithArchivedLists(3);
        $this->insertHistorial($user->id, 'Arroz', null);
        $this->insertHistorial($user->id, 'Arroz', null);

        $stats = $this->service->getStats($user);

        $this->assertIsInt($stats['top_products'][0]['count']);
        $this->assertSame(2, $stats['top_products'][0]['count']);
    }

    public function test_top_products_orders_by_count_descending(): void
    {
        $user = $this->userWithArchivedLists(3);
        $this->insertHistorial($user->id, 'Arroz', null);
        $this->insertHistorial($user->id, 'Leche', null);
        $this->insertHistorial($user->id, 'Leche', null);
        $this->insertHistorial($user->id, 'Leche', null);

        $stats = $this->service->getStats($user);

        $this->assertSame('Leche', $stats['top_products'][0]['name']);
        $this->assertSame(3, $stats['top_products'][0]['count']);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function userWithArchivedLists(int $count): User
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count($count)->create([
            'user_id' => $user->id,
            'status' => ListStatus::Archived,
        ]);

        return $user;
    }

    private function archivedListAt(User $user, Carbon $date): ShoppingList
    {
        $list = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Archived,
            'updated_at' => $date,
        ]);

        return $list;
    }

    private function insertHistorial(int $userId, string $name, ?string $categoria): void
    {
        DB::table('producto_historial')->insert([
            'user_id' => $userId,
            'producto_nombre' => $name,
            'categoria' => $categoria,
            'fecha_compra' => now(),
            'lista_id' => null,
        ]);
    }
}
