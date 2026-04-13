<?php

namespace Tests\Feature;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use App\Models\ProductoCatalogo;
use App\Models\ProductoHistorial;
use App\Models\User;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Dto\Suggestion;
use App\Support\Ai\FakeClaudeClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductSuggestionControllerTest extends TestCase
{
    use DatabaseTransactions;

    private FakeClaudeClient $fakeClaude;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->fakeClaude = new FakeClaudeClient();
        $this->app->instance(ClaudeClientInterface::class, $this->fakeClaude);
    }

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
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

    public function test_requires_auth(): void
    {
        $this->getJson('/api/suggestions?q=leche')->assertUnauthorized();
    }

    public function test_validates_minimum_query_length(): void
    {
        $user = User::factory()->createOne();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q=a')
            ->assertUnprocessable();
    }

    public function test_validates_maximum_query_length(): void
    {
        $user = User::factory()->createOne();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q='.str_repeat('a', 61))
            ->assertUnprocessable();
    }

    public function test_happy_path_layer1(): void
    {
        $user = User::factory()->createOne();
        $this->seedHistory($user, 'Leche entera', 2);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q=le');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['suggestions', 'ai_fallback_used', 'ai_limit_reached']])
            ->assertJsonPath('data.ai_fallback_used', false)
            ->assertJsonPath('data.suggestions.0.name', 'Leche entera')
            ->assertJsonPath('data.suggestions.0.source', 'history');
    }

    public function test_happy_path_layer2(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Pan de barra']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q=pan');

        $response->assertOk()
            ->assertJsonPath('data.suggestions.0.name', 'Pan de barra')
            ->assertJsonPath('data.suggestions.0.source', 'catalog');
    }

    public function test_layer3_fallback_when_scarce(): void
    {
        $user = User::factory()->createOne();
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'Xilitol')];

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q=xy&include_ai=1');

        $response->assertOk()
            ->assertJsonPath('data.ai_fallback_used', true);
    }

    public function test_ai_limit_reached_when_user_quota_exhausted(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->count(20)->create([
            'user_id' => $user->id,
            'operation' => AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
        ]);
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'Blocked')];

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q=xy&include_ai=1');

        $response->assertOk()
            ->assertJsonPath('data.ai_limit_reached', true)
            ->assertJsonPath('data.ai_fallback_used', false);
    }

    public function test_ai_limit_reached_when_budget_cap_exceeded(): void
    {
        config(['ai.budget_cap_monthly_usd' => 1]);
        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 5,
        ]);
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'Blocked')];

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q=xy&include_ai=1');

        $response->assertJsonPath('data.ai_limit_reached', true);
    }

    public function test_empty_results_does_not_fail(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q=xyzzy');

        $response->assertOk()
            ->assertJsonPath('data.suggestions', [])
            ->assertJsonPath('data.ai_fallback_used', false);
    }

    public function test_user_cannot_see_other_users_history_results(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->seedHistory($other, 'Zzsecreto99', 5);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q=zzsecreto99');

        $response->assertOk()
            ->assertJsonPath('data.suggestions', []);
    }

    public function test_include_ai_false_by_default(): void
    {
        $user = User::factory()->createOne();
        $this->fakeClaude->cannedSuggestions = [new Suggestion('ai', 'Should not appear')];

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions?q=xy');

        $response->assertOk()->assertJsonPath('data.ai_fallback_used', false);
        $this->assertNull($this->fakeClaude->lastQuery);
    }
}
