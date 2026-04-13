<?php

namespace Tests\Feature\Auth;

use App\Models\ProductoHistorial;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileHistoryTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    private function record(User $user, string $name, int $times = 1): void
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

    public function test_history_requires_auth(): void
    {
        $this->getJson('/api/profile/history')->assertUnauthorized();
    }

    public function test_history_returns_paginated_list_sorted_by_weighted_score(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Leche', 3);
        $this->record($user, 'Pan', 1);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/profile/history');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        ['producto_nombre', 'total_count', 'weighted_score'],
                    ],
                    'pagination' => ['page', 'per_page', 'total'],
                ],
            ])
            ->assertJsonPath('data.items.0.producto_nombre', 'Leche')
            ->assertJsonPath('data.items.0.total_count', 3);
    }

    public function test_history_empty_returns_empty_items(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/profile/history');

        $response->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_history_excludes_other_users(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->record($user, 'Mio');
        $this->record($other, 'Suyo');

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/profile/history');

        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.producto_nombre', 'Mio');
    }

    public function test_clear_history_deletes_all_user_rows(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->record($user, 'Mio1');
        $this->record($user, 'Mio2');
        $this->record($other, 'Suyo');

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson('/api/profile/history');

        $response->assertOk()->assertJsonPath('data.deleted', 2);
        $this->assertDatabaseHas('producto_historial', ['producto_nombre' => 'Suyo']);
        $this->assertDatabaseMissing('producto_historial', ['producto_nombre' => 'Mio1']);
    }

    public function test_clear_history_requires_auth(): void
    {
        $this->deleteJson('/api/profile/history')->assertUnauthorized();
    }

    public function test_forget_product_deletes_only_matching(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Leche', 2);
        $this->record($user, 'Pan');

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson('/api/profile/history/Leche');

        $response->assertOk()->assertJsonPath('data.deleted', 2);
        $this->assertDatabaseHas('producto_historial', ['user_id' => $user->id, 'producto_nombre' => 'Pan']);
        $this->assertDatabaseMissing('producto_historial', ['user_id' => $user->id, 'producto_nombre' => 'Leche']);
    }

    public function test_forget_product_scopes_to_user(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->record($user, 'Leche');
        $this->record($other, 'Leche');

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson('/api/profile/history/Leche');

        $this->assertDatabaseHas('producto_historial', [
            'user_id' => $other->id,
            'producto_nombre' => 'Leche',
        ]);
    }

    public function test_forget_product_requires_auth(): void
    {
        $this->deleteJson('/api/profile/history/Leche')->assertUnauthorized();
    }

    public function test_forget_product_handles_url_encoded_name(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Leche entera');

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson('/api/profile/history/'.rawurlencode('Leche entera'));

        $response->assertOk()->assertJsonPath('data.deleted', 1);
    }
}
