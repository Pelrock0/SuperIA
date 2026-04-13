<?php

namespace Tests\Feature;

use App\Enums\ShareTokenMode;
use App\Models\ListItem;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ShareTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharedListControllerTest extends TestCase
{
    use DatabaseTransactions;

    private ShareTokenService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = app(ShareTokenService::class);
    }

    private function setupSharedList(ShareTokenMode $mode = ShareTokenMode::Edit): array
    {
        $owner = User::factory()->createOne(['name' => 'Maria']);
        $list = ShoppingList::factory()->createOne(['user_id' => $owner->id, 'name' => 'Compra']);
        $token = $this->service->generate($list, $mode);
        $url = $this->service->urlFor($token);
        $raw = substr($url, strrpos($url, '/') + 1);

        return [$owner, $list, $token, $raw];
    }

    // === SHOW ===

    public function test_show_returns_list_data(): void
    {
        [$owner, $list, $token, $raw] = $this->setupSharedList();
        ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Pan']);

        $response = $this->getJson("/api/shared/{$raw}");

        $response->assertOk()
            ->assertJsonPath('data.list.id', $list->id)
            ->assertJsonPath('data.list.owner_name', 'Maria')
            ->assertJsonPath('data.mode', 'edit')
            ->assertJsonStructure(['data' => ['list', 'mode', 'items', 'counters']]);
    }

    public function test_show_410_on_revoked_token(): void
    {
        [, , $token, $raw] = $this->setupSharedList();
        $this->service->revoke($token);

        $this->getJson("/api/shared/{$raw}")->assertStatus(410);
    }

    public function test_show_410_on_invalid_signature(): void
    {
        [, , $token, ] = $this->setupSharedList();

        $this->getJson("/api/shared/{$token->token_id}.fakesig")->assertStatus(410);
    }

    public function test_show_410_on_nonexistent_token(): void
    {
        $fakeUuid = (string) Str::uuid();

        $this->getJson("/api/shared/{$fakeUuid}.anything")->assertStatus(410);
    }

    public function test_show_410_on_malformed_token(): void
    {
        $this->getJson('/api/shared/malformed')->assertStatus(410);
    }

    // === STORE ITEM ===

    public function test_store_item_succeeds_on_edit_token(): void
    {
        [, , , $raw] = $this->setupSharedList(ShareTokenMode::Edit);

        $response = $this->postJson("/api/shared/{$raw}/items", [
            'name' => 'Agua',
            'quantity' => 2,
            'unit' => 'L',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.item.name', 'Agua');
    }

    public function test_store_item_blocked_on_read_only_token(): void
    {
        [, , , $raw] = $this->setupSharedList(ShareTokenMode::ReadOnly);

        $this->postJson("/api/shared/{$raw}/items", ['name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_store_item_validates_input(): void
    {
        [, , , $raw] = $this->setupSharedList(ShareTokenMode::Edit);

        $this->postJson("/api/shared/{$raw}/items", ['name' => ''])->assertUnprocessable();
    }

    public function test_store_item_logs_anonymous_activity(): void
    {
        [, $list, $token, $raw] = $this->setupSharedList(ShareTokenMode::Edit);

        $this->postJson("/api/shared/{$raw}/items", ['name' => 'Leche']);

        $this->assertDatabaseHas('list_activity_log', [
            'shopping_list_id' => $list->id,
            'list_share_token_id' => $token->id,
            'actor_type' => 'anonymous',
            'action' => 'item_added',
            'item_name' => 'Leche',
        ]);
    }

    // === UPDATE ITEM ===

    public function test_update_item_succeeds_on_edit_token(): void
    {
        [, $list, , $raw] = $this->setupSharedList(ShareTokenMode::Edit);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Old']);

        $response = $this->putJson("/api/shared/{$raw}/items/{$item->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJsonPath('data.item.name', 'New Name');
    }

    public function test_update_item_blocked_on_read_only_token(): void
    {
        [, $list, , $raw] = $this->setupSharedList(ShareTokenMode::ReadOnly);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id]);

        $this->putJson("/api/shared/{$raw}/items/{$item->id}", ['name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_update_item_404_when_item_from_other_list(): void
    {
        [, , , $raw] = $this->setupSharedList(ShareTokenMode::Edit);
        $otherList = ShoppingList::factory()->createOne();
        $item = ListItem::factory()->createOne(['shopping_list_id' => $otherList->id]);

        $this->putJson("/api/shared/{$raw}/items/{$item->id}", ['name' => 'Hack'])
            ->assertNotFound();
    }

    // === TOGGLE ITEM ===

    public function test_toggle_succeeds_on_edit_token(): void
    {
        [, $list, , $raw] = $this->setupSharedList(ShareTokenMode::Edit);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'is_purchased' => false]);

        $response = $this->patchJson("/api/shared/{$raw}/items/{$item->id}/toggle");

        $response->assertOk()->assertJsonPath('data.item.is_purchased', true);
    }

    public function test_toggle_blocked_on_read_only_token(): void
    {
        [, $list, , $raw] = $this->setupSharedList(ShareTokenMode::ReadOnly);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id]);

        $this->patchJson("/api/shared/{$raw}/items/{$item->id}/toggle")
            ->assertForbidden();
    }

    public function test_toggle_creates_producto_historial_with_owner_id(): void
    {
        [$owner, $list, , $raw] = $this->setupSharedList(ShareTokenMode::Edit);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Pan']);

        $this->patchJson("/api/shared/{$raw}/items/{$item->id}/toggle");

        $this->assertDatabaseHas('producto_historial', [
            'user_id' => $owner->id,
            'producto_nombre' => 'Pan',
            'lista_id' => $list->id,
        ]);
    }

    public function test_toggle_logs_anonymous_activity(): void
    {
        [, $list, $token, $raw] = $this->setupSharedList(ShareTokenMode::Edit);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id, 'name' => 'Agua']);

        $this->patchJson("/api/shared/{$raw}/items/{$item->id}/toggle");

        $this->assertDatabaseHas('list_activity_log', [
            'shopping_list_id' => $list->id,
            'list_share_token_id' => $token->id,
            'actor_type' => 'anonymous',
            'action' => 'item_checked',
        ]);
    }

    // === DESTROY ITEM ===

    public function test_destroy_item_succeeds_on_edit_token(): void
    {
        [, $list, , $raw] = $this->setupSharedList(ShareTokenMode::Edit);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id]);

        $this->deleteJson("/api/shared/{$raw}/items/{$item->id}")->assertOk();

        $this->assertDatabaseMissing('list_items', ['id' => $item->id]);
    }

    public function test_destroy_item_blocked_on_read_only_token(): void
    {
        [, $list, , $raw] = $this->setupSharedList(ShareTokenMode::ReadOnly);
        $item = ListItem::factory()->createOne(['shopping_list_id' => $list->id]);

        $this->deleteJson("/api/shared/{$raw}/items/{$item->id}")->assertForbidden();

        $this->assertDatabaseHas('list_items', ['id' => $item->id]);
    }

    // === HEARTBEAT ===

    public function test_heartbeat_creates_session_for_edit_token(): void
    {
        [, , $token, $raw] = $this->setupSharedList(ShareTokenMode::Edit);
        $uuid = (string) Str::uuid();

        $this->postJson("/api/shared/{$raw}/heartbeat", ['session_uuid' => $uuid])
            ->assertOk();

        $this->assertDatabaseHas('list_collaborator_sessions', [
            'list_share_token_id' => $token->id,
            'session_uuid' => $uuid,
        ]);
    }

    public function test_heartbeat_works_on_read_only_token(): void
    {
        [, , $token, $raw] = $this->setupSharedList(ShareTokenMode::ReadOnly);
        $uuid = (string) Str::uuid();

        $this->postJson("/api/shared/{$raw}/heartbeat", ['session_uuid' => $uuid])
            ->assertOk();

        $this->assertDatabaseHas('list_collaborator_sessions', [
            'list_share_token_id' => $token->id,
        ]);
    }

    public function test_heartbeat_requires_valid_uuid(): void
    {
        [, , , $raw] = $this->setupSharedList();

        $this->postJson("/api/shared/{$raw}/heartbeat", ['session_uuid' => 'not-a-uuid'])
            ->assertUnprocessable();
    }

    public function test_heartbeat_410_on_revoked_token(): void
    {
        [, , $token, $raw] = $this->setupSharedList();
        $this->service->revoke($token);

        $this->postJson("/api/shared/{$raw}/heartbeat", [
            'session_uuid' => (string) Str::uuid(),
        ])->assertStatus(410);
    }

    // === SECURITY: cross-tenant ===

    public function test_cross_tenant_token_cannot_mutate_another_list(): void
    {
        [, $listA, , $rawA] = $this->setupSharedList(ShareTokenMode::Edit);
        $listB = ShoppingList::factory()->createOne();
        $itemB = ListItem::factory()->createOne(['shopping_list_id' => $listB->id]);

        $this->patchJson("/api/shared/{$rawA}/items/{$itemB->id}/toggle")
            ->assertNotFound();
    }
}
