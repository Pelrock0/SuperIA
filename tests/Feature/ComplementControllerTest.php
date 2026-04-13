<?php

namespace Tests\Feature;

use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\FakeClaudeClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ComplementControllerTest extends TestCase
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

    public function test_requires_auth(): void
    {
        $this->getJson('/api/suggestions/complements?product=pasta&list_id=1')
            ->assertUnauthorized();
    }

    public function test_validates_required_params(): void
    {
        $user = User::factory()->createOne();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suggestions/complements')
            ->assertUnprocessable();
    }

    public function test_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/suggestions/complements?product=pasta&list_id={$list->id}")
            ->assertForbidden();
    }

    public function test_returns_local_suggestions(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        // 5 completed pasta lists all with tomate frito
        foreach (range(1, 5) as $_) {
            $completed = ShoppingList::factory()->createOne([
                'user_id' => $user->id,
                'items_total' => 2,
                'items_completed' => 2,
            ]);
            ProductoHistorial::create([
                'user_id' => $user->id,
                'producto_nombre' => 'Pasta',
                'fecha_compra' => now(),
                'lista_id' => $completed->id,
            ]);
            ProductoHistorial::create([
                'user_id' => $user->id,
                'producto_nombre' => 'Tomate frito',
                'fecha_compra' => now(),
                'lista_id' => $completed->id,
            ]);
        }

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/suggestions/complements?product=Pasta&list_id={$list->id}");

        $response->assertOk()
            ->assertJsonPath('data.ai_fallback_used', false);
        $names = collect($response->json('data.suggestions'))->pluck('nombre')->all();
        $this->assertContains('Tomate frito', $names);
    }

    public function test_ai_fallback_when_new_user(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);

        $this->fakeClaude->cannedComplements = [
            ['nombre' => 'Tomate frito', 'unidad_tipica' => 'ud', 'categoria' => 'conservas'],
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/suggestions/complements?product=Pasta&list_id={$list->id}");

        $response->assertOk()
            ->assertJsonPath('data.ai_fallback_used', true)
            ->assertJsonPath('data.suggestions.0.nombre', 'Tomate frito');
    }
}
