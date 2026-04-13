<?php

namespace Tests\Feature;

use App\Enums\AiUsageStatus;
use App\Enums\ListStatus;
use App\Models\AiUsageLog;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\Ai\CircuitBreaker;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\FakeClaudeClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ListGenerationControllerTest extends TestCase
{
    use DatabaseTransactions;

    private FakeClaudeClient $fakeClaude;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->fakeClaude = new FakeClaudeClient();
        $this->fakeClaude->cannedListGeneration = [
            ['nombre' => 'Arroz', 'cantidad_tipica' => 1.0, 'unidad_tipica' => 'kg', 'categoria' => 'otros', 'reason' => 'Base'],
            ['nombre' => 'Pollo', 'cantidad_tipica' => 1.5, 'unidad_tipica' => 'kg', 'categoria' => 'carnes_pescados', 'reason' => null],
        ];
        $this->app->instance(ClaudeClientInterface::class, $this->fakeClaude);
        (new CircuitBreaker())->reset();
    }

    // --- POST /api/generate-list ---

    public function test_generate_happy_path(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list', ['description' => 'Paella para 4', 'people' => 4]);

        $response->assertOk()
            ->assertJsonPath('data.meta.people', 4)
            ->assertJsonCount(2, 'data.products');
    }

    public function test_generate_defaults_people_to_config(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list', ['description' => 'Cena']);

        $response->assertOk()
            ->assertJsonPath('data.meta.people', 2);
    }

    public function test_generate_requires_auth(): void
    {
        $this->postJson('/api/generate-list', ['description' => 'Cena'])->assertStatus(401);
    }

    public function test_generate_validates_description_required(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list', []);

        $response->assertStatus(422);
    }

    public function test_generate_validates_description_max_length(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list', ['description' => str_repeat('x', 501)]);

        $response->assertStatus(422);
    }

    public function test_generate_returns_429_on_per_operation_limit(): void
    {
        config(['ai.generation.generation_per_day' => 1]);
        $user = User::factory()->createOne();
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'operation' => \App\Enums\AiOperation::Generation,
            'status' => AiUsageStatus::Success,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list', ['description' => 'Cena']);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'GENERATION_LIMIT');
    }

    public function test_generate_returns_500_on_claude_failure(): void
    {
        $this->fakeClaude->shouldThrow = new ClaudeException('fail');
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list', ['description' => 'Cena']);

        $response->assertStatus(500)
            ->assertJsonPath('error.code', 'GENERATION_FAILED');
    }

    // --- POST /api/generate-list/confirm-new ---

    public function test_confirm_new_creates_list(): void
    {
        $user = User::factory()->createOne();
        $items = [
            ['nombre' => 'Arroz', 'cantidad_tipica' => 1.0, 'unidad_tipica' => 'kg', 'categoria' => 'otros'],
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list/confirm-new', ['items' => $items, 'name' => 'Mi cena']);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Mi cena');
        $this->assertSame(1, $user->shoppingLists()->count());
    }

    public function test_confirm_new_returns_403_freemium(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => ListStatus::Active,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list/confirm-new', [
                'items' => [['nombre' => 'Arroz']],
                'name' => 'Otra',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FREEMIUM_LIMIT');
    }

    public function test_confirm_new_requires_auth(): void
    {
        $this->postJson('/api/generate-list/confirm-new', [
            'items' => [['nombre' => 'Test']],
            'name' => 'Test',
        ])->assertStatus(401);
    }

    public function test_confirm_new_validates_items_required(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list/confirm-new', ['name' => 'Test']);

        $response->assertStatus(422);
    }

    // --- POST /api/generate-list/confirm-existing ---

    public function test_confirm_existing_appends_items(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $items = [
            ['nombre' => 'Tomate', 'cantidad_tipica' => 0.5, 'unidad_tipica' => 'kg'],
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list/confirm-existing', [
                'items' => $items,
                'list_id' => $list->id,
            ]);

        $response->assertOk();
        $this->assertSame(1, $list->items()->count());
    }

    public function test_confirm_existing_returns_404_for_other_users_list(): void
    {
        $owner = User::factory()->createOne();
        $intruder = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeaders($this->authHeaders($intruder))
            ->postJson('/api/generate-list/confirm-existing', [
                'items' => [['nombre' => 'X']],
                'list_id' => $list->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_confirm_existing_requires_auth(): void
    {
        $this->postJson('/api/generate-list/confirm-existing', [
            'items' => [['nombre' => 'X']],
            'list_id' => 1,
        ])->assertStatus(401);
    }

    public function test_confirm_existing_validates_list_id_exists(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/generate-list/confirm-existing', [
                'items' => [['nombre' => 'X']],
                'list_id' => 999999,
            ]);

        $response->assertStatus(422);
    }
}
