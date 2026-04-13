<?php

namespace Tests\Unit\Services;

use App\Models\ListItem;
use App\Models\ProductoCatalogo;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\PriceEstimationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PriceEstimationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PriceEstimationService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(PriceEstimationService::class);
    }

    public function test_layer1_resolves_from_personal_history(): void
    {
        $user = User::factory()->createOne();
        ProductoHistorial::create([
            'user_id' => $user->id,
            'producto_nombre' => 'Leche entera',
            'categoria' => 'lacteos_huevos',
            'cantidad' => 1,
            'unidad' => 'L',
            'precio_real' => 1.15,
            'fecha_compra' => now(),
            'lista_id' => null,
        ]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Leche entera', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNotNull($estimate);
        $this->assertSame(1.15, $estimate->min);
        $this->assertSame(1.15, $estimate->max);
        $this->assertSame('history', $estimate->source);
    }

    public function test_layer1_uses_most_recent_price(): void
    {
        $user = User::factory()->createOne();
        ProductoHistorial::create([
            'user_id' => $user->id, 'producto_nombre' => 'Pan', 'precio_real' => 0.80,
            'fecha_compra' => now()->subDays(30), 'lista_id' => null,
        ]);
        ProductoHistorial::create([
            'user_id' => $user->id, 'producto_nombre' => 'Pan', 'precio_real' => 0.95,
            'fecha_compra' => now()->subDays(1), 'lista_id' => null,
        ]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Pan', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertSame(0.95, $estimate->min);
    }

    public function test_layer2_resolves_from_catalog(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne([
            'nombre' => 'Tomates',
            'precio_min' => 1.50,
            'precio_max' => 2.80,
        ]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Tomates', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNotNull($estimate);
        $this->assertSame(1.50, (float) $estimate->min);
        $this->assertSame(2.80, (float) $estimate->max);
        $this->assertSame('catalog', $estimate->source);
    }

    public function test_returns_null_when_both_layers_miss(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Salsa especial casera', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNull($estimate);
    }

    public function test_layer1_takes_precedence_over_layer2(): void
    {
        $user = User::factory()->createOne();
        ProductoHistorial::create([
            'user_id' => $user->id, 'producto_nombre' => 'Arroz', 'precio_real' => 1.20,
            'fecha_compra' => now(), 'lista_id' => null,
        ]);
        ProductoCatalogo::factory()->createOne([
            'nombre' => 'Arroz', 'precio_min' => 0.90, 'precio_max' => 1.50,
        ]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Arroz', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertSame('history', $estimate->source);
        $this->assertSame(1.20, $estimate->min);
    }

    public function test_estimate_for_list_aggregates_and_persists(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Leche', 'precio_min' => 0.90, 'precio_max' => 1.20]);
        ProductoCatalogo::factory()->createOne(['nombre' => 'Pan', 'precio_min' => 0.50, 'precio_max' => 0.80]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Leche', 'quantity' => 2, 'is_purchased' => false, 'position' => 0]);
        $list->items()->create(['name' => 'Pan', 'quantity' => 1, 'is_purchased' => false, 'position' => 1]);
        $list->items()->create(['name' => 'Unknown', 'is_purchased' => false, 'position' => 2]);

        $result = $this->service->estimateForList($user, $list->load('items'));

        $this->assertSame(2.30, $result->totalMin); // 0.90*2 + 0.50*1
        $this->assertSame(3.20, $result->totalMax); // 1.20*2 + 0.80*1
        $this->assertSame(2, $result->resolvedCount);
        $this->assertSame(1, $result->unresolvedCount);
        $this->assertCount(3, $result->items);

        // Check estimated_price persisted
        $leche = $list->items()->where('name', 'Leche')->first();
        $this->assertNotNull($leche->estimated_price);
        $unknown = $list->items()->where('name', 'Unknown')->first();
        $this->assertNull($unknown->estimated_price);
    }

    public function test_quantity_multiplied_into_estimate(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Yogur', 'precio_min' => 0.30, 'precio_max' => 0.50]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Yogur', 'quantity' => 6, 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->estimateForList($user, $list->load('items'));

        $this->assertSame(1.80, $result->totalMin); // 0.30 * 6
        $this->assertSame(3.00, $result->totalMax); // 0.50 * 6
    }

    public function test_record_item_prices_updates_history_and_item(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Leche', 'is_purchased' => true, 'position' => 0]);
        ProductoHistorial::create([
            'user_id' => $user->id, 'producto_nombre' => 'Leche',
            'fecha_compra' => now(), 'lista_id' => null,
        ]);

        $updated = $this->service->recordItemPrices($user, $list, [
            ['item_id' => $item->id, 'price' => 1.25],
        ]);

        $this->assertSame(1, $updated);
        $this->assertSame(1.25, (float) ProductoHistorial::where('user_id', $user->id)->where('producto_nombre', 'Leche')->first()->precio_real);
        $this->assertSame(1.25, (float) $item->refresh()->estimated_price);
    }

    public function test_record_item_prices_skips_missing_history(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'NoHistory', 'is_purchased' => true, 'position' => 0]);

        $updated = $this->service->recordItemPrices($user, $list, [
            ['item_id' => $item->id, 'price' => 5.00],
        ]);

        $this->assertSame(0, $updated);
    }

    public function test_case_insensitive_matching(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'LECHE ENTERA', 'precio_min' => 0.85, 'precio_max' => 1.20]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'leche entera', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNotNull($estimate);
        $this->assertSame('catalog', $estimate->source);
    }
}
