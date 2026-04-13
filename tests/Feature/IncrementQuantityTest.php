<?php

namespace Tests\Feature;

use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class IncrementQuantityTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    public function test_increments_item_quantity(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Tomates', 'quantity' => 2, 'is_purchased' => false, 'position' => 0]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/increment-quantity", ['quantity' => 3]);

        $response->assertOk();
        $this->assertSame(5.0, (float) $item->refresh()->quantity);
    }

    public function test_increments_null_quantity(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'Sal', 'quantity' => null, 'is_purchased' => false, 'position' => 0]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/increment-quantity", ['quantity' => 1]);

        $response->assertOk();
        $this->assertSame(1.0, (float) $item->refresh()->quantity);
    }

    public function test_requires_auth(): void
    {
        $list = ShoppingList::factory()->create();
        $item = $list->items()->create(['name' => 'X', 'is_purchased' => false, 'position' => 0]);

        $this->patchJson("/api/lists/{$list->id}/items/{$item->id}/increment-quantity", ['quantity' => 1])
            ->assertStatus(401);
    }

    public function test_requires_ownership(): void
    {
        $owner = User::factory()->createOne();
        $intruder = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $owner->id]);
        $item = $list->items()->create(['name' => 'X', 'is_purchased' => false, 'position' => 0]);

        $this->withHeaders($this->authHeaders($intruder))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/increment-quantity", ['quantity' => 1])
            ->assertStatus(403);
    }

    public function test_validates_quantity_required(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'X', 'is_purchased' => false, 'position' => 0]);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/increment-quantity", [])
            ->assertStatus(422);
    }

    public function test_validates_quantity_positive(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create(['user_id' => $user->id]);
        $item = $list->items()->create(['name' => 'X', 'is_purchased' => false, 'position' => 0]);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/lists/{$list->id}/items/{$item->id}/increment-quantity", ['quantity' => 0])
            ->assertStatus(422);
    }
}
