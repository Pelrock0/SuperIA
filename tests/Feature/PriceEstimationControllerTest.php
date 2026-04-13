<?php

namespace Tests\Feature;

use App\Models\ProductoCatalogo;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PriceEstimationControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    // --- POST /api/lists/{list}/estimate-prices ---

    public function test_estimate_returns_price_breakdown(): void
    {
        $user = User::factory()->createOne();
        ProductoCatalogo::factory()->createOne(['nombre' => 'Leche', 'precio_min' => 0.90, 'precio_max' => 1.20]);
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $list->items()->create(['name' => 'Leche', 'quantity' => 1, 'is_purchased' => false, 'position' => 0]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/estimate-prices");

        $response->assertOk()
            ->assertJsonPath('data.resolved_count', 1)
            ->assertJsonPath('data.unresolved_count', 0);
    }

    public function test_estimate_requires_auth(): void
    {
        $list = ShoppingList::factory()->create();
        $this->postJson("/api/lists/{$list->id}/estimate-prices")->assertStatus(401);
    }

    public function test_estimate_requires_ownership(): void
    {
        $owner = User::factory()->createOne();
        $intruder = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);

        $this->withHeaders($this->authHeaders($intruder))
            ->postJson("/api/lists/{$list->id}/estimate-prices")
            ->assertStatus(404);
    }

    public function test_estimate_handles_empty_list(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/estimate-prices");

        $response->assertOk()
            ->assertJsonPath('data.total_min', 0)
            ->assertJsonPath('data.total_max', 0)
            ->assertJsonPath('data.resolved_count', 0);
    }

    // --- POST /api/lists/{list}/confirm-prices ---

    public function test_confirm_prices_with_per_item(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Leche', 'is_purchased' => true, 'position' => 0]);
        ProductoHistorial::create([
            'user_id' => $user->id, 'producto_nombre' => 'Leche',
            'fecha_compra' => now(), 'lista_id' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/confirm-prices", [
                'total' => 42.50,
                'items' => [['item_id' => $item->id, 'price' => 1.25]],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.updated_count', 1);
    }

    public function test_confirm_prices_total_only(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/confirm-prices", [
                'total' => 35.00,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.updated_count', 0);
    }

    public function test_confirm_prices_requires_auth(): void
    {
        $list = ShoppingList::factory()->create();
        $this->postJson("/api/lists/{$list->id}/confirm-prices", ['total' => 10])
            ->assertStatus(401);
    }

    public function test_confirm_prices_requires_ownership(): void
    {
        $owner = User::factory()->createOne();
        $intruder = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);

        $this->withHeaders($this->authHeaders($intruder))
            ->postJson("/api/lists/{$list->id}/confirm-prices", ['total' => 10])
            ->assertStatus(404);
    }

    public function test_confirm_prices_validates_total_numeric(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/confirm-prices", ['total' => 'abc'])
            ->assertStatus(422);
    }
}
