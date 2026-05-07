<?php

namespace Tests\Unit\Services;

use App\Models\AiDismissedSuggestion;
use App\Models\ListItem;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Models\UserSilencedProduct;
use App\Services\ProductHistoryStatsService;
use App\Services\ReplenishmentSuggestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReplenishmentSuggestionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ReplenishmentSuggestionService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'ai.thresholds.min_occurrences' => 3,
            'ai.thresholds.replenishment_factor' => 0.8,
        ]);
        $this->service = new ReplenishmentSuggestionService(new ProductHistoryStatsService());
    }

    /**
     * @psalm-return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Eloquent\Model
     */
    private function activeListWithItems(User $user, int $itemCount = 3): \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
    {
        $list = ShoppingList::factory()->createOne([
            'user_id' => $user->id,
            'status' => 'active',
            'items_total' => $itemCount,
        ]);
        return $list;
    }

    private function recordPurchase(User $user, string $name, string $when): void
    {
        ProductoHistorial::create([
            'user_id' => $user->id,
            'producto_nombre' => $name,
            'fecha_compra' => now()->parse($when),
            'lista_id' => null,
        ]);
    }

    public function test_empty_when_no_active_list_with_items(): void
    {
        $user = User::factory()->createOne();
        $this->recordPurchase($user, 'Leche', '-14 days');
        $this->recordPurchase($user, 'Leche', '-7 days');
        $this->recordPurchase($user, 'Leche', now()->toDateTimeString());

        $this->assertSame([], $this->service->forUser($user));
    }

    public function test_empty_when_active_list_has_less_than_3_items(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'status' => 'active', 'items_total' => 2]);
        $this->recordPurchase($user, 'Leche', '-14 days');
        $this->recordPurchase($user, 'Leche', '-7 days');
        $this->recordPurchase($user, 'Leche', '-10 days');

        $this->assertSame([], $this->service->forUser($user));
    }

    public function test_suggests_when_due_for_replenishment(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);
        // 3 purchases: day -14, -7, -10 -> avg gap ~3.5 days
        // But we need monotonic dates for DATEDIFF(max-min)/(count-1). Using -14, -7, -3 -> gap=5.5
        $this->recordPurchase($user, 'Leche', '-14 days');
        $this->recordPurchase($user, 'Leche', '-9 days');
        $this->recordPurchase($user, 'Leche', '-5 days'); // avg=4.5, days_since_last=5, 5>4.5*0.8=3.6 -> suggest

        $result = $this->service->forUser($user);

        $this->assertCount(1, $result);
        $this->assertSame('Leche', $result[0]['producto_nombre']);
    }

    public function test_does_not_suggest_below_min_occurrences(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);
        $this->recordPurchase($user, 'Pan', '-10 days');
        $this->recordPurchase($user, 'Pan', '-5 days');

        $this->assertSame([], $this->service->forUser($user));
    }

    public function test_does_not_suggest_when_factor_gates(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);
        // 3 purchases: -6, -4, -2 -> avg=2, days_since_last=2, 2 > 2*0.8 = 1.6 -> would suggest
        // Let's make it not suggest: -10, -8, -6 -> avg=2, days_since_last=6 -> 6>1.6 suggest
        // Make it NOT suggest: -6, -4, -2 days, but check: days_since=2, avg=2, 2>1.6 still suggests
        // Really not suggest: -3, -2, -1 -> avg=1, days_since_last=1, 1>0.8 -> still suggests
        // Not suggest requires: days_since <= avg*0.8
        // -6, -4, -2: avg=2, days_since=2. 2 > 1.6. Suggests.
        // -4, -2, today: avg=2, days_since=0. 0 > 1.6 NO. Does NOT suggest.
        $this->recordPurchase($user, 'Queso', '-4 days');
        $this->recordPurchase($user, 'Queso', '-2 days');
        $this->recordPurchase($user, 'Queso', now()->toDateTimeString());

        $this->assertSame([], $this->service->forUser($user));
    }

    public function test_excludes_silenced_products(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);
        $this->recordPurchase($user, 'Silenciado', '-14 days');
        $this->recordPurchase($user, 'Silenciado', '-9 days');
        $this->recordPurchase($user, 'Silenciado', '-5 days');
        UserSilencedProduct::create([
            'user_id' => $user->id,
            'producto_nombre' => 'Silenciado',
            'silenced_at' => now(),
        ]);

        $this->assertSame([], $this->service->forUser($user));
    }

    public function test_excludes_dismissed_products_within_ttl(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);
        $this->recordPurchase($user, 'Dismissed', '-14 days');
        $this->recordPurchase($user, 'Dismissed', '-9 days');
        $this->recordPurchase($user, 'Dismissed', '-5 days');
        AiDismissedSuggestion::factory()->createOne([
            'user_id' => $user->id,
            'producto_nombre' => 'Dismissed',
            'dismissed_until' => now()->addHours(12),
        ]);

        $this->assertSame([], $this->service->forUser($user));
    }

    public function test_includes_expired_dismissed_products(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);
        $this->recordPurchase($user, 'Expired', '-14 days');
        $this->recordPurchase($user, 'Expired', '-9 days');
        $this->recordPurchase($user, 'Expired', '-5 days');
        AiDismissedSuggestion::factory()->expired()->createOne([
            'user_id' => $user->id,
            'producto_nombre' => 'Expired',
        ]);

        $result = $this->service->forUser($user);

        $this->assertCount(1, $result);
    }

    public function test_excludes_products_in_active_lists(): void
    {
        $user = User::factory()->createOne();
        $list = $this->activeListWithItems($user);
        $this->recordPurchase($user, 'Leche', '-14 days');
        $this->recordPurchase($user, 'Leche', '-9 days');
        $this->recordPurchase($user, 'Leche', '-5 days');
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Leche']);

        $this->assertSame([], $this->service->forUser($user));
    }

    public function test_caps_at_3_suggestions(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);

        foreach (['A', 'B', 'C', 'D', 'E'] as $name) {
            $this->recordPurchase($user, $name, '-14 days');
            $this->recordPurchase($user, $name, '-9 days');
            $this->recordPurchase($user, $name, '-5 days');
        }

        $result = $this->service->forUser($user);

        $this->assertCount(3, $result);
    }

    public function test_sorts_by_urgency_ratio_desc(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);

        // Low urgency: -6, -4, -2 (avg=2, since=2, ratio=1.0)
        $this->recordPurchase($user, 'Baja', '-6 days');
        $this->recordPurchase($user, 'Baja', '-4 days');
        $this->recordPurchase($user, 'Baja', '-2 days');

        // High urgency: -20, -15, -10 (avg=5, since=10, ratio=2.0)
        $this->recordPurchase($user, 'Alta', '-20 days');
        $this->recordPurchase($user, 'Alta', '-15 days');
        $this->recordPurchase($user, 'Alta', '-10 days');

        $result = $this->service->forUser($user);

        $this->assertSame('Alta', $result[0]['producto_nombre']);
    }

    public function test_ignore_creates_dismiss_row(): void
    {
        $user = User::factory()->createOne();

        $this->service->ignore($user, 'Leche');

        $this->assertDatabaseHas('ai_dismissed_suggestions', [
            'user_id' => $user->id,
            'producto_nombre' => 'Leche',
        ]);
    }

    public function test_silence_creates_silenced_row(): void
    {
        $user = User::factory()->createOne();

        $this->service->silence($user, 'Chocolate');

        $this->assertDatabaseHas('user_silenced_products', [
            'user_id' => $user->id,
            'producto_nombre' => 'Chocolate',
        ]);
    }

    public function test_silence_is_idempotent(): void
    {
        $user = User::factory()->createOne();

        $this->service->silence($user, 'Chocolate');
        $this->service->silence($user, 'Chocolate');

        $this->assertSame(1, UserSilencedProduct::where('user_id', $user->id)->count());
    }

    public function test_cache_returns_same_result_within_ttl(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);
        $this->recordPurchase($user, 'Leche', '-14 days');
        $this->recordPurchase($user, 'Leche', '-9 days');
        $this->recordPurchase($user, 'Leche', '-5 days');

        $first = $this->service->forUser($user);

        // Mutate underlying data, should not affect cached result
        ProductoHistorial::where('user_id', $user->id)->delete();

        $second = $this->service->forUser($user);

        $this->assertSame($first, $second);
    }

    public function test_invalidate_cache_clears_cached_value(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);
        $this->recordPurchase($user, 'Leche', '-14 days');
        $this->recordPurchase($user, 'Leche', '-9 days');
        $this->recordPurchase($user, 'Leche', '-5 days');

        $this->service->forUser($user);

        ProductoHistorial::where('user_id', $user->id)->delete();
        $this->service->invalidateCache($user);

        $this->assertSame([], $this->service->forUser($user));
    }

    public function test_ignore_invalidates_cache(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);
        $this->recordPurchase($user, 'Leche', '-14 days');
        $this->recordPurchase($user, 'Leche', '-9 days');
        $this->recordPurchase($user, 'Leche', '-5 days');

        $this->service->forUser($user);
        $this->service->ignore($user, 'Leche');

        $this->assertSame([], $this->service->forUser($user));
    }

    public function test_returns_empty_array_when_no_qualifying_rows(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);

        $result = $this->service->forUser($user);

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function test_excluded_product_does_not_stop_iteration_of_remaining(): void
    {
        $user = User::factory()->createOne();
        $this->activeListWithItems($user);

        // Leche is in the active list — will be excluded
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id, 'status' => 'active', 'items_total' => 3]);
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Leche', 'is_purchased' => false]);

        // Leche: 3 purchases (excluded via active list)
        $this->recordPurchase($user, 'Leche', '-20 days');
        $this->recordPurchase($user, 'Leche', '-14 days');
        $this->recordPurchase($user, 'Leche', '-7 days');

        // Pan: 3 purchases (should still appear after Leche is skipped)
        $this->recordPurchase($user, 'Pan', '-21 days');
        $this->recordPurchase($user, 'Pan', '-14 days');
        $this->recordPurchase($user, 'Pan', '-7 days');

        $result = $this->service->forUser($user);

        $names = array_column($result, 'producto_nombre');
        $this->assertContains('Pan', $names);
        $this->assertNotContains('Leche', $names);
    }
}
