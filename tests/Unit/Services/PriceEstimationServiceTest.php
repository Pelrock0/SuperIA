<?php

namespace Tests\Unit\Services;

use App\Models\ListItem;
use App\Models\PriceCache;
use App\Models\ProductoCatalogo;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\PriceEstimationService;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\FakeClaudeClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PriceEstimationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PriceEstimationService $service;

    private FakeClaudeClient $fakeClaude;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeClaude = new FakeClaudeClient();
        $this->fakeClaude->shouldThrow = new ClaudeException('disabled in test');
        $this->service = new PriceEstimationService($this->fakeClaude);
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

    public function test_quantity_in_grams_normalized_to_kg(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Arroz', 'precio_min' => 1.20, 'precio_max' => 1.80]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Arroz', 'quantity' => 500, 'unit' => 'g', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->estimateForList($user, $list->load('items'));

        $this->assertSame(0.60, $result->totalMin);  // 1.20 * 0.5
        $this->assertSame(0.90, $result->totalMax);  // 1.80 * 0.5
    }

    public function test_quantity_in_ml_normalized_to_liters(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Nata', 'precio_min' => 1.00, 'precio_max' => 2.00]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Nata', 'quantity' => 250, 'unit' => 'ml', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->estimateForList($user, $list->load('items'));

        $this->assertSame(0.25, $result->totalMin);  // 1.00 * 0.25
        $this->assertSame(0.50, $result->totalMax);  // 2.00 * 0.25
    }

    public function test_quantity_zero_defaults_to_one(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Sal', 'precio_min' => 0.50, 'precio_max' => 1.00]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Sal', 'quantity' => 0, 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->estimateForList($user, $list->load('items'));

        $this->assertSame(0.50, $result->totalMin);
        $this->assertSame(1.00, $result->totalMax);
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

    public function test_layer3a_fuzzy_match_resolves_from_catalog(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Pan de molde', 'precio_min' => 1.20, 'precio_max' => 2.50]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'pan de molde integral', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNotNull($estimate);
        $this->assertSame('catalog_fuzzy', $estimate->source);
        $this->assertSame(1.20, $estimate->min);
        $this->assertSame(2.50, $estimate->max);
    }

    public function test_layer3b_resolves_from_price_cache(): void
    {
        $user = User::factory()->createOne();
        PriceCache::create([
            'input_name' => 'salsa especial casera',
            'precio_min' => 2.00,
            'precio_max' => 4.00,
            'expires_at' => now()->addDays(10),
        ]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Salsa especial casera', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNotNull($estimate);
        $this->assertSame('cache', $estimate->source);
        $this->assertSame(2.00, $estimate->min);
    }

    public function test_layer3b_ignores_expired_cache(): void
    {
        $user = User::factory()->createOne();
        PriceCache::create([
            'input_name' => 'producto expirado',
            'precio_min' => 1.00,
            'precio_max' => 2.00,
            'expires_at' => now()->subDay(),
        ]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'producto expirado', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNull($estimate);
    }

    public function test_layer3c_claude_estimates_and_writes_cache(): void
    {
        $this->fakeClaude->shouldThrow = null;
        $this->fakeClaude->cannedItemPriceMin = 0.80;
        $this->fakeClaude->cannedItemPriceMax = 1.60;

        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Zumo de naranja artesanal', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNotNull($estimate);
        $this->assertSame('ai', $estimate->source);
        $this->assertSame(0.80, $estimate->min);
        $this->assertSame(1.60, $estimate->max);
        $this->assertCount(1, $this->fakeClaude->itemPriceCalls);
        $this->assertDatabaseHas('price_cache', ['input_name' => 'zumo de naranja artesanal']);
    }

    public function test_layer3c_returns_null_when_claude_fails(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Producto inexistente xyz123', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNull($estimate);
    }

    public function test_layer3c_throttle_blocks_after_daily_limit(): void
    {
        $this->fakeClaude->shouldThrow = null;
        $this->fakeClaude->cannedItemPriceMin = 1.00;
        $this->fakeClaude->cannedItemPriceMax = 2.00;

        $user = User::factory()->createOne();
        $key = "price_throttle:{$user->id}:".now()->toDateString();
        Cache::store('array')->put($key, 50, 3600);
        // Point the app cache to array store so withinThrottle reads it
        config(['cache.default' => 'array']);

        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Producto xyz99zz sin match', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNull($estimate);
        $this->assertEmpty($this->fakeClaude->itemPriceCalls);
    }

    public function test_layer3c_increments_throttle_counter_after_successful_call(): void
    {
        $this->fakeClaude->shouldThrow = null;
        $this->fakeClaude->cannedItemPriceMin = 1.00;
        $this->fakeClaude->cannedItemPriceMax = 2.00;
        config(['cache.default' => 'array']);

        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Producto sin match xyz987abc', 'is_purchased' => false, 'position' => 0]);

        $key = "price_throttle:{$user->id}:".now()->toDateString();
        $this->assertSame(0, (int) Cache::get($key, 0));

        $this->service->estimateForItem($user, $item);

        $this->assertSame(1, (int) Cache::get($key));
    }

    public function test_throttle_key_includes_user_id_and_date(): void
    {
        $this->fakeClaude->shouldThrow = null;
        $this->fakeClaude->cannedItemPriceMin = 1.00;
        $this->fakeClaude->cannedItemPriceMax = 2.00;
        config(['cache.default' => 'array']);

        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Producto sin match abc789xyz', 'is_purchased' => false, 'position' => 0]);

        $this->service->estimateForItem($user, $item);

        $expectedKey = "price_throttle:{$user->id}:".now()->toDateString();
        $this->assertSame(1, (int) Cache::get($expectedKey));

        // Key without date suffix must not exist
        $this->assertNull(Cache::get("price_throttle:{$user->id}:"));
        // Key without user prefix must not exist
        $this->assertNull(Cache::get(now()->toDateString()));
    }

    public function test_throttle_counter_reaches_two_after_two_calls(): void
    {
        $this->fakeClaude->shouldThrow = null;
        $this->fakeClaude->cannedItemPriceMin = 1.00;
        $this->fakeClaude->cannedItemPriceMax = 2.00;
        config(['cache.default' => 'array']);

        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item1 = $list->items()->create(['name' => 'Producto aaa111zzz sin match', 'is_purchased' => false, 'position' => 0]);
        $item2 = $list->items()->create(['name' => 'Producto bbb222zzz sin match', 'is_purchased' => false, 'position' => 1]);

        $this->service->estimateForItem($user, $item1);
        $this->service->estimateForItem($user, $item2);

        $key = "price_throttle:{$user->id}:".now()->toDateString();
        $this->assertSame(2, (int) Cache::get($key));
    }

    public function test_layer3a_excludes_words_with_two_or_fewer_chars(): void
    {
        $user = User::factory()->createOne();
        // Catalog entry only matchable via 2-char word "ab"
        ProductoCatalogo::factory()->createOne(['nombre' => 'ab special product', 'precio_min' => 1.00, 'precio_max' => 2.00]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        // "ab" (2 chars) and "de" (2 chars) — both must be filtered out
        $item = $list->items()->create(['name' => 'ab de', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        // With correct filter (> 2), no words pass → no fuzzy match → null
        $this->assertNull($estimate);
    }

    public function test_layer3a_matches_word_in_middle_of_catalog_name(): void
    {
        $user = User::factory()->createOne();
        // "pan" appears in the MIDDLE — requires %pan%, not pan% or %pan
        ProductoCatalogo::factory()->createOne(['nombre' => 'barra pan molde', 'precio_min' => 1.10, 'precio_max' => 2.20]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'pan artesanal integral', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNotNull($estimate);
        $this->assertSame('catalog_fuzzy', $estimate->source);
        $this->assertSame(1.10, $estimate->min);
        $this->assertSame(2.20, $estimate->max);
    }

    public function test_layer3c_persists_precio_min_and_max_to_price_cache(): void
    {
        $this->fakeClaude->shouldThrow = null;
        $this->fakeClaude->cannedItemPriceMin = 0.80;
        $this->fakeClaude->cannedItemPriceMax = 1.60;

        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Crema catalana especial xz99', 'is_purchased' => false, 'position' => 0]);

        $this->service->estimateForItem($user, $item);

        $this->assertDatabaseHas('price_cache', [
            'input_name' => 'crema catalana especial xz99',
            'precio_min' => 0.80,
            'precio_max' => 1.60,
        ]);
    }

    public function test_unresolved_item_resets_estimated_price_to_null(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create([
            'name' => 'ProductoSinMatchXYZ999abc',
            'estimated_price' => 9.99,
            'is_purchased' => false,
            'position' => 0,
        ]);

        $this->service->estimateForList($user, $list->load('items'));

        $this->assertNull($item->fresh()->estimated_price);
    }

    public function test_estimate_for_list_includes_item_id_in_unresolved_items(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'ProductoSinMatchABC888xyz', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->estimateForList($user, $list->load('items'));

        $this->assertCount(1, $result->items);
        $this->assertSame($item->id, $result->items[0]['item_id']);
        $this->assertNull($result->items[0]['min']);
        $this->assertNull($result->items[0]['source']);
    }

    public function test_record_total_price_logs_correct_info(): void
    {
        Log::spy();

        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);

        $this->service->recordTotalPrice($user, $list, 42.50);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('price.total_confirmed', \Mockery::on(fn ($ctx) =>
                $ctx['user_id'] === $user->id &&
                $ctx['list_id'] === $list->id &&
                $ctx['total'] === 42.50
            ));
    }

    public function test_layer3c_logs_warning_on_claude_failure(): void
    {
        Log::spy();

        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Producto inexistente warningxyz', 'is_purchased' => false, 'position' => 0]);

        $this->service->estimateForItem($user, $item);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('price.layer3.claude_failed', \Mockery::on(fn ($ctx) =>
                isset($ctx['item']) && isset($ctx['error'])
            ));
    }

    public function test_layer1_returns_float_values(): void
    {
        $user = User::factory()->createOne();
        ProductoHistorial::create([
            'user_id' => $user->id,
            'producto_nombre' => 'Producto float layer1',
            'precio_real' => 1.99,
            'fecha_compra' => now(),
            'lista_id' => null,
        ]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Producto float layer1', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertIsFloat($estimate->min);
        $this->assertIsFloat($estimate->max);
        $this->assertSame(1.99, $estimate->min);
        $this->assertSame(1.99, $estimate->max);
    }

    public function test_layer3a_returns_float_values(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne([
            'nombre' => 'queso manchego especial curado extra',
            'precio_min' => 3.50,
            'precio_max' => 5.20,
        ]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        // Slightly different name to force fuzzy (layer 3a) instead of exact (layer 2)
        $item = $list->items()->create(['name' => 'queso manchego especial curado extra fino', 'is_purchased' => false, 'position' => 0]);

        $estimate = $this->service->estimateForItem($user, $item);

        $this->assertNotNull($estimate);
        $this->assertSame('catalog_fuzzy', $estimate->source);
        $this->assertIsFloat($estimate->min);
        $this->assertIsFloat($estimate->max);
        $this->assertSame(3.50, $estimate->min);
        $this->assertSame(5.20, $estimate->max);
    }
}
