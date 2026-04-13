<?php

namespace Tests\Feature;

use App\Enums\ListStatus;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class HistoryControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    public function test_history_returns_archived_lists(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        ShoppingList::factory()->createOne(['user_id' => $user->id, 'status' => ListStatus::Active]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/history');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_history_includes_price_total(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);
        $list->items()->create(['name' => 'Leche', 'estimated_price' => 1.20, 'is_purchased' => true, 'position' => 0]);
        $list->items()->create(['name' => 'Pan', 'estimated_price' => 0.80, 'is_purchased' => true, 'position' => 1]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/history');

        $response->assertOk();
        $this->assertSame(2.00, (float) $response->json('data.0.price_total'));
    }

    public function test_history_requires_auth(): void
    {
        $this->getJson('/api/history')->assertStatus(401);
    }

    public function test_history_excludes_other_users(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        ShoppingList::factory()->create(['user_id' => $other->id, 'status' => ListStatus::Archived]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/history');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_duplicate_creates_clean_copy(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Archived,
            'name' => 'Cena Navidad',
        ]);
        $list->items()->create(['name' => 'Pavo', 'quantity' => 1, 'unit' => 'ud', 'category' => 'carnes_pescados', 'is_purchased' => true, 'position' => 0]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$list->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Copia de Cena Navidad');
        $newList = ShoppingList::find($response->json('data.id'));
        $this->assertSame(1, $newList->items()->count());
        $this->assertFalse((bool) $newList->items()->first()->is_purchased);
    }

    public function test_duplicate_respects_freemium(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create(['user_id' => $user->id, 'status' => ListStatus::Active]);
        $archived = ShoppingList::factory()->create(['user_id' => $user->id, 'status' => ListStatus::Archived]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/lists/{$archived->id}/duplicate");

        $response->assertStatus(403)->assertJsonPath('error.code', 'FREEMIUM_LIMIT');
    }

    public function test_duplicate_requires_ownership(): void
    {
        $owner = User::factory()->createOne();
        $intruder = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id, 'status' => ListStatus::Archived]);

        $this->withHeaders($this->authHeaders($intruder))
            ->postJson("/api/lists/{$list->id}/duplicate")
            ->assertStatus(404);
    }

    public function test_duplicate_requires_auth(): void
    {
        $list = ShoppingList::factory()->create(['status' => ListStatus::Archived]);
        $this->postJson("/api/lists/{$list->id}/duplicate")->assertStatus(401);
    }
}
