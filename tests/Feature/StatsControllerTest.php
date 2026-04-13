<?php

namespace Tests\Feature;

use App\Enums\ListStatus;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StatsControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    public function test_stats_returns_data_when_enough_lists(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        DB::table('producto_historial')->insert([
            'user_id' => $user->id, 'producto_nombre' => 'Leche', 'categoria' => 'lacteos_huevos',
            'cantidad' => 1, 'unidad' => 'L', 'fecha_compra' => now(), 'lista_id' => null,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/stats');

        $response->assertOk()
            ->assertJsonPath('data.has_enough_data', true)
            ->assertJsonPath('data.total_lists_completed', 3);
    }

    public function test_stats_returns_not_enough_data(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(2)->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/stats');

        $response->assertOk()
            ->assertJsonPath('data.has_enough_data', false)
            ->assertJsonPath('data.monthly_spend', [])
            ->assertJsonPath('data.top_categories', [])
            ->assertJsonPath('data.top_products', []);
    }

    public function test_stats_top_products_returns_up_to_10(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        for ($i = 0; $i < 12; $i++) {
            DB::table('producto_historial')->insert([
                'user_id' => $user->id, 'producto_nombre' => "Product{$i}",
                'fecha_compra' => now(), 'lista_id' => null,
            ]);
        }

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/stats');

        $this->assertLessThanOrEqual(10, count($response->json('data.top_products')));
    }

    public function test_stats_requires_auth(): void
    {
        $this->getJson('/api/stats')->assertStatus(401);
    }

    public function test_stats_scoped_to_user(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        ShoppingList::factory()->count(5)->create(['user_id' => $other->id, 'status' => ListStatus::Archived]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/stats');

        $response->assertOk()->assertJsonPath('data.total_lists_completed', 0);
    }
}
