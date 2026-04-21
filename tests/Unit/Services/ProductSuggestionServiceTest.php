<?php

namespace Tests\Unit\Services;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use App\Models\ProductoCatalogo;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ProductHistoryWeightingService;
use App\Services\ProductSuggestionService;
use App\Support\Ai\AiUsageTracker;
use App\Support\Ai\BudgetCap;
use App\Support\Ai\CircuitBreaker;
use App\Support\Ai\Dto\Suggestion;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\FakeClaudeClient;
use App\Support\Ai\HistoryAnonymizer;
use App\Support\Ai\PromptSanitizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductSuggestionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private FakeClaudeClient $fakeClaude;

    private ProductSuggestionService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'ai.budget_cap_monthly_usd' => 50,
            'ai.rate_limits.free.suggestions_per_day' => 20,
            'ai.circuit_breaker.failure_threshold' => 3,
            'ai.circuit_breaker.cool_down_seconds' => 60,
            'ai.prompt.max_user_input_chars' => 200,
            'ai.prompt.max_history_items_in_context' => 20,
            'ai.timezone' => 'Europe/Madrid',
        ]);

        $this->fakeClaude = new FakeClaudeClient();
        $this->service = new ProductSuggestionService(
            new ProductHistoryWeightingService(),
            new PromptSanitizer(),
            new HistoryAnonymizer(),
            new BudgetCap(),
            new AiUsageTracker(),
            new CircuitBreaker('claude-test'),
            $this->fakeClaude,
        );
    }

    private function seedHistory(User $user, string $name, int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            ProductoHistorial::create([
                'user_id' => $user->id,
                'producto_nombre' => $name,
                'fecha_compra' => now(),
                'lista_id' => null,
            ]);
        }
    }

    public function test_layer1_hits_history_with_prefix(): void
    {
        $user = User::factory()->createOne();
        $this->seedHistory($user, 'Leche entera', 3);
        $this->seedHistory($user, 'Leche desnatada', 1);

        $result = $this->service->suggest($user, 'le', includeAi: false);

        $names = collect($result['suggestions'])->pluck('name')->all();
        $this->assertContains('Leche entera', $names);
        $this->assertSame('Leche entera', $names[0]);
        $this->assertFalse($result['ai_fallback_used']);
    }

    public function test_layer2_hits_catalog_when_history_empty(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Zzunico TestProd1']);
        ProductoCatalogo::factory()->createOne(['nombre' => 'Zzunico TestProd2']);

        $result = $this->service->suggest($user, 'zzunico', includeAi: false);

        $names = collect($result['suggestions'])->pluck('name')->all();
        $this->assertContains('Zzunico TestProd1', $names);
        $this->assertContains('Zzunico TestProd2', $names);
    }

    public function test_layer3_not_called_when_local_has_three_results(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Pan de barra']);
        ProductoCatalogo::factory()->createOne(['nombre' => 'Pan integral']);
        ProductoCatalogo::factory()->createOne(['nombre' => 'Pan de molde']);
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'Not called')];

        $result = $this->service->suggest($user, 'pa', includeAi: true);

        $this->assertNull($this->fakeClaude->lastQuery);
        $this->assertFalse($result['ai_fallback_used']);
    }

    public function test_layer3_called_when_local_scarce_and_include_ai(): void
    {
        $user = User::factory()->createOne();
        $this->fakeClaude->cannedSuggestions = [
            new Suggestion('ai', 'Xilitol'),
            new Suggestion('ai', 'Xuxos'),
        ];

        $result = $this->service->suggest($user, 'xy', includeAi: true);

        $this->assertNotNull($this->fakeClaude->lastQuery);
        $this->assertTrue($result['ai_fallback_used']);
        $names = collect($result['suggestions'])->pluck('name')->all();
        $this->assertContains('Xilitol', $names);
    }

    public function test_layer3_not_called_when_include_ai_false(): void
    {
        $user = User::factory()->createOne();
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'Anything')];

        $result = $this->service->suggest($user, 'xy', includeAi: false);

        $this->assertNull($this->fakeClaude->lastQuery);
        $this->assertFalse($result['ai_fallback_used']);
    }

    public function test_budget_cap_blocks_layer3(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 100,
        ]);
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'Should not appear')];

        $result = $this->service->suggest($user, 'xy', includeAi: true);

        $this->assertNull($this->fakeClaude->lastQuery);
        $this->assertTrue($result['ai_limit_reached']);
        $this->assertFalse($result['ai_fallback_used']);
        $this->assertDatabaseHas('ai_usage_log', [
            'user_id' => $user->id,
            'status' => AiUsageStatus::BudgetCapped->value,
        ]);
    }

    public function test_user_quota_blocks_layer3(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->count(20)->create([
            'user_id' => $user->id,
            'operation' => AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 0.01,
        ]);
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'Blocked')];

        $result = $this->service->suggest($user, 'xy', includeAi: true);

        $this->assertNull($this->fakeClaude->lastQuery);
        $this->assertTrue($result['ai_limit_reached']);
    }

    public function test_claude_error_records_and_does_not_throw(): void
    {
        $user = User::factory()->createOne();
        $this->fakeClaude->shouldThrow = new ClaudeException('boom');

        $result = $this->service->suggest($user, 'xy', includeAi: true);

        $this->assertFalse($result['ai_fallback_used']);
        $this->assertSame([], $result['suggestions']);
        $this->assertDatabaseHas('ai_usage_log', [
            'user_id' => $user->id,
            'status' => AiUsageStatus::Error->value,
        ]);
    }

    public function test_successful_claude_records_cost(): void
    {
        $user = User::factory()->createOne();
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'Nuez')];
        $this->fakeClaude->cannedCost = 0.005;

        $result = $this->service->suggest($user, 'xy', includeAi: true);

        $this->assertTrue($result['ai_fallback_used']);
        $this->assertDatabaseHas('ai_usage_log', [
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success->value,
        ]);
    }

    public function test_cross_layer_dedup_case_insensitive(): void
    {
        $user = User::factory()->createOne();
        $this->seedHistory($user, 'Leche entera');
        ProductoCatalogo::factory()->createOne(['nombre' => 'Leche entera']);

        $result = $this->service->suggest($user, 'le', includeAi: false);

        $names = collect($result['suggestions'])->pluck('name')->all();
        $leche = array_filter($names, fn ($n) => mb_strtolower($n) === 'leche entera');
        $this->assertCount(1, $leche);
    }

    public function test_local_limit_cap_total_at_five(): void
    {
        $user = User::factory()->createOne();
        foreach (range(1, 10) as $i) {
            ProductoCatalogo::factory()->createOne(['nombre' => "Pan {$i}"]);
        }

        $result = $this->service->suggest($user, 'pan', includeAi: false);

        $this->assertLessThanOrEqual(5, count($result['suggestions']));
    }

    public function test_history_takes_precedence_in_dedup(): void
    {
        $user = User::factory()->createOne();
        $this->seedHistory($user, 'Leche entera');
        ProductoCatalogo::factory()->createOne(['nombre' => 'Leche entera']);

        $result = $this->service->suggest($user, 'le', includeAi: false);

        $this->assertSame('history', $result['suggestions'][0]['source']);
    }

    public function test_circuit_breaker_opens_after_failures(): void
    {
        $user = User::factory()->createOne();
        $this->fakeClaude->shouldThrow = new ClaudeException('x');

        for ($i = 0; $i < 3; $i++) {
            $this->service->suggest($user, 'xy', includeAi: true);
        }

        $this->fakeClaude->shouldThrow = null;
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'After reset')];
        $this->fakeClaude->lastQuery = null;

        $result = $this->service->suggest($user, 'xy', includeAi: true);

        $this->assertNull($this->fakeClaude->lastQuery);
        $this->assertTrue($result['ai_limit_reached']);
        $this->assertDatabaseHas('ai_usage_log', [
            'user_id' => $user->id,
            'status' => AiUsageStatus::CircuitOpen->value,
        ]);
    }

    public function test_empty_local_and_no_ai_returns_empty(): void
    {
        $user = User::factory()->createOne();

        $result = $this->service->suggest($user, 'xyz', includeAi: false);

        $this->assertSame([], $result['suggestions']);
        $this->assertFalse($result['ai_fallback_used']);
        $this->assertFalse($result['ai_limit_reached']);
    }

    public function test_layer3_pii_never_leaves_via_claude(): void
    {
        $user = User::factory()->createOne(['email' => 'secret@superia.test']);
        $this->seedHistory($user, 'Leche entera');
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'X')];

        $this->service->suggest($user, 'xy', includeAi: true);

        $payload = json_encode($this->fakeClaude->lastQuery);
        $this->assertStringNotContainsString('secret@superia.test', $payload);
        $this->assertStringNotContainsString('user_id', $payload);
        $this->assertStringNotContainsString((string) $user->id, $payload);
    }

    // AC-1: list items appear when no purchase history exists
    public function test_layer_list_returns_item_added_to_list_without_purchase(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Chocolate negro 70%', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->suggest($user, 'Choco', includeAi: false);

        $names = collect($result['suggestions'])->pluck('name')->all();
        $this->assertContains('Chocolate negro 70%', $names);
        $sources = collect($result['suggestions'])->pluck('source')->all();
        $this->assertContains('list', $sources);
    }

    // AC-2: history takes precedence over list layer
    public function test_layer_list_loses_to_history_in_dedup(): void
    {
        $user = User::factory()->createOne();
        $this->seedHistory($user, 'Leche entera');
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Leche entera', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->suggest($user, 'Leche', includeAi: false);

        $match = collect($result['suggestions'])->firstWhere('name', 'Leche entera');
        $this->assertNotNull($match);
        $this->assertSame('history', $match['source']);
    }

    // AC-3: list layer wins over catalog
    public function test_layer_list_beats_catalog_in_dedup(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Tortitas de arroz', 'is_purchased' => false, 'position' => 0]);
        ProductoCatalogo::factory()->createOne(['nombre' => 'Tortitas de arroz']);

        $result = $this->service->suggest($user, 'Tort', includeAi: false);

        $match = collect($result['suggestions'])->firstWhere('name', 'Tortitas de arroz');
        $this->assertNotNull($match);
        $this->assertSame('list', $match['source']);
    }

    // AC-4: items from other users' lists are never returned
    public function test_layer_list_never_returns_other_users_items(): void
    {
        $userA = User::factory()->createOne();
        $userB = User::factory()->createOne();
        $listA = ShoppingList::factory()->create(['user_id' => $userA->id]);
        $listA->items()->create(['name' => 'Producto secreto XYZ', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->suggest($userB, 'Prod', includeAi: false);

        $names = collect($result['suggestions'])->pluck('name')->all();
        $this->assertNotContains('Producto secreto XYZ', $names);
    }

    // AC-5: prefix-only — mid-word does not match
    public function test_layer_list_prefix_only_no_mid_word_match(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Pan integral', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->suggest($user, 'integral', includeAi: false);

        $names = collect($result['suggestions'])->pluck('name')->all();
        $this->assertNotContains('Pan integral', $names);
    }

    // AC-6: empty query returns no list-layer results
    public function test_layer_list_empty_query_returns_nothing(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Cualquier cosa', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->suggest($user, '   ', includeAi: false);

        $this->assertSame([], $result['suggestions']);
    }

    // AC-7: same item in list and catalog appears once
    public function test_layer_list_deduplicates_with_catalog(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Arroz largo', 'is_purchased' => false, 'position' => 0]);
        ProductoCatalogo::factory()->createOne(['nombre' => 'Arroz largo']);

        $result = $this->service->suggest($user, 'Arroz', includeAi: false);

        $arrozMatches = collect($result['suggestions'])->filter(
            fn ($s) => mb_strtolower($s['name']) === 'arroz largo'
        );
        $this->assertCount(1, $arrozMatches);
    }

    // Item from multiple lists appears once (DISTINCT)
    public function test_layer_list_distinct_across_multiple_lists(): void
    {
        $user = User::factory()->createOne();
        $list1 = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list2 = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list1->items()->create(['name' => 'Mantequilla', 'is_purchased' => false, 'position' => 0]);
        $list2->items()->create(['name' => 'Mantequilla', 'is_purchased' => false, 'position' => 0]);

        $result = $this->service->suggest($user, 'Mant', includeAi: false);

        $manteMatches = collect($result['suggestions'])->filter(
            fn ($s) => mb_strtolower($s['name']) === 'mantequilla'
        );
        $this->assertCount(1, $manteMatches);
    }
}
