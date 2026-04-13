<?php

namespace Tests\Feature;

use App\Models\ProductoCatalogo;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AutoCategorizationTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    public function test_auto_categorizes_from_catalog(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        ProductoCatalogo::factory()->createOne([
            'nombre' => 'Leche entera',
            'categoria' => 'lacteos_huevos',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Leche entera']);

        $response->assertStatus(201);
        $this->assertSame('lacteos_huevos', $response->json('data.item.category'));
    }

    public function test_leaves_null_when_not_in_catalog(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", ['name' => 'Salsa rara']);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.item.category'));
    }

    public function test_respects_explicit_category(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        ProductoCatalogo::factory()->createOne([
            'nombre' => 'Leche',
            'categoria' => 'lacteos_huevos',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/items", [
                'name' => 'Leche',
                'category' => 'bebidas', // user overrides
            ]);

        $response->assertStatus(201);
        $this->assertSame('bebidas', $response->json('data.item.category'));
    }
}
