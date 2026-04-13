<?php

namespace Tests\Feature;

use App\Models\ListItem;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReplenishmentControllerTest extends TestCase
{
    use DatabaseTransactions;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    private function primeHistory(User $user): void
    {
        $list = ShoppingList::factory()->createOne([
            'user_id' => $user->id,
            'status' => 'active',
            'items_total' => 3,
        ]);
        foreach (['-14 days', '-9 days', '-5 days'] as $when) {
            ProductoHistorial::create([
                'user_id' => $user->id,
                'producto_nombre' => 'Leche',
                'fecha_compra' => now()->parse($when),
                'lista_id' => $list->id,
            ]);
        }
    }

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/dashboard/replenishment')->assertUnauthorized();
    }

    public function test_index_returns_empty_when_no_active_list(): void
    {
        $user = User::factory()->createOne();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/dashboard/replenishment')
            ->assertOk()
            ->assertJsonPath('data.suggestions', []);
    }

    public function test_index_returns_suggestions_when_data_matches(): void
    {
        $user = User::factory()->createOne();
        $this->primeHistory($user);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/dashboard/replenishment');

        $response->assertOk()
            ->assertJsonPath('data.suggestions.0.producto_nombre', 'Leche');
    }

    public function test_accept_creates_item_in_list(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne([
            'user_id' => $user->id,
            'status' => 'active',
            'items_total' => 3,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/replenishment/accept', [
                'producto_nombre' => 'Leche',
                'list_id' => $list->id,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('list_items', [
            'shopping_list_id' => $list->id,
            'name' => 'Leche',
        ]);
    }

    public function test_accept_denies_other_users_list(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $other->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/replenishment/accept', [
                'producto_nombre' => 'Leche',
                'list_id' => $list->id,
            ])
            ->assertForbidden();
    }

    public function test_accept_requires_valid_input(): void
    {
        $user = User::factory()->createOne();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/replenishment/accept', [])
            ->assertUnprocessable();
    }

    public function test_accept_requires_auth(): void
    {
        $this->postJson('/api/replenishment/accept', [])->assertUnauthorized();
    }

    public function test_ignore_creates_dismiss_row(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/replenishment/ignore', ['producto_nombre' => 'Leche']);

        $response->assertOk();
        $this->assertDatabaseHas('ai_dismissed_suggestions', [
            'user_id' => $user->id,
            'producto_nombre' => 'Leche',
        ]);
    }

    public function test_ignore_validates_input(): void
    {
        $user = User::factory()->createOne();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/replenishment/ignore', [])
            ->assertUnprocessable();
    }

    public function test_ignore_requires_auth(): void
    {
        $this->postJson('/api/replenishment/ignore', [])->assertUnauthorized();
    }

    public function test_silence_creates_silenced_row(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/replenishment/silence', ['producto_nombre' => 'Chocolate']);

        $response->assertOk();
        $this->assertDatabaseHas('user_silenced_products', [
            'user_id' => $user->id,
            'producto_nombre' => 'Chocolate',
        ]);
    }

    public function test_silence_scopes_to_user(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/replenishment/silence', ['producto_nombre' => 'Leche']);

        $this->assertDatabaseHas('user_silenced_products', [
            'user_id' => $user->id,
            'producto_nombre' => 'Leche',
        ]);
        $this->assertDatabaseMissing('user_silenced_products', [
            'user_id' => $other->id,
        ]);
    }

    public function test_silence_requires_auth(): void
    {
        $this->postJson('/api/replenishment/silence', [])->assertUnauthorized();
    }
}
