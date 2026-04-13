<?php

namespace Tests\Unit\Services;

use App\Enums\ShareTokenMode;
use App\Models\ListCollaboratorSession;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ShareTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShareTokenServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ShareTokenService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShareTokenService();
    }

    public function test_generate_creates_token_and_flags_list_as_shared(): void
    {
        $list = ShoppingList::factory()->createOne(['is_shared' => false]);

        $token = $this->service->generate($list, ShareTokenMode::Edit);

        $this->assertEquals($list->id, $token->shopping_list_id);
        $this->assertEquals(ShareTokenMode::Edit, $token->mode);
        $this->assertNull($token->revoked_at);
        $this->assertTrue($list->fresh()->is_shared);
    }

    public function test_generate_read_only_token(): void
    {
        $list = ShoppingList::factory()->createOne();

        $token = $this->service->generate($list, ShareTokenMode::ReadOnly);

        $this->assertEquals(ShareTokenMode::ReadOnly, $token->mode);
    }

    public function test_generate_two_tokens_coexist(): void
    {
        $list = ShoppingList::factory()->createOne();

        $edit = $this->service->generate($list, ShareTokenMode::Edit);
        $read = $this->service->generate($list, ShareTokenMode::ReadOnly);

        $this->assertNotEquals($edit->token_id, $read->token_id);
        $this->assertCount(2, $this->service->activeTokensForList($list));
    }

    public function test_revoke_marks_token_and_keeps_other_active(): void
    {
        $list = ShoppingList::factory()->createOne();
        $edit = $this->service->generate($list, ShareTokenMode::Edit);
        $read = $this->service->generate($list, ShareTokenMode::ReadOnly);

        $this->service->revoke($edit);

        $this->assertNotNull($edit->fresh()->revoked_at);
        $this->assertNull($read->fresh()->revoked_at);
        $this->assertTrue($list->fresh()->is_shared);
    }

    public function test_revoke_deletes_sessions(): void
    {
        $token = ListShareToken::factory()->createOne();
        ListCollaboratorSession::factory()->count(3)->create([
            'list_share_token_id' => $token->id,
        ]);

        $this->service->revoke($token);

        $this->assertEquals(0, ListCollaboratorSession::where('list_share_token_id', $token->id)->count());
    }

    public function test_revoke_last_active_token_unflags_is_shared(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = $this->service->generate($list, ShareTokenMode::Edit);

        $this->service->revoke($token);

        $this->assertFalse($list->fresh()->is_shared);
    }

    public function test_revoke_is_idempotent(): void
    {
        $token = ListShareToken::factory()->revoked()->createOne();
        $originalRevokedAt = $token->revoked_at;

        $this->service->revoke($token);

        $this->assertEquals(
            $originalRevokedAt->toIso8601String(),
            $token->fresh()->revoked_at->toIso8601String(),
        );
    }

    public function test_active_tokens_excludes_revoked(): void
    {
        $list = ShoppingList::factory()->createOne();
        $active = $this->service->generate($list, ShareTokenMode::Edit);
        $revoked = $this->service->generate($list, ShareTokenMode::ReadOnly);
        $this->service->revoke($revoked);

        $tokens = $this->service->activeTokensForList($list);

        $this->assertCount(1, $tokens);
        $this->assertEquals($active->id, $tokens->first()->id);
    }

    public function test_resolve_from_url_param_returns_context(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = $this->service->generate($list, ShareTokenMode::Edit);
        $url = $this->service->urlFor($token);

        $raw = substr($url, strrpos($url, '/') + 1);
        $context = $this->service->resolveFromUrlParam($raw);

        $this->assertNotNull($context);
        $this->assertEquals($token->id, $context->token->id);
        $this->assertEquals($list->id, $context->list->id);
        $this->assertEquals(ShareTokenMode::Edit, $context->mode);
    }

    public function test_resolve_returns_null_for_invalid_token(): void
    {
        $this->assertNull($this->service->resolveFromUrlParam('notreal.badsignature'));
    }

    public function test_resolve_returns_null_for_malformed(): void
    {
        $this->assertNull($this->service->resolveFromUrlParam('nodothere'));
    }

    public function test_resolve_returns_null_for_revoked(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = $this->service->generate($list, ShareTokenMode::Edit);
        $url = $this->service->urlFor($token);
        $raw = substr($url, strrpos($url, '/') + 1);

        $this->service->revoke($token);

        $this->assertNull($this->service->resolveFromUrlParam($raw));
    }

    public function test_resolve_returns_null_on_tampered_signature(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = $this->service->generate($list, ShareTokenMode::Edit);

        $tampered = $token->token_id.'.AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

        $this->assertNull($this->service->resolveFromUrlParam($tampered));
    }

    public function test_url_for_produces_full_url(): void
    {
        config(['app.url' => 'http://example.test']);
        $token = ListShareToken::factory()->createOne();

        $url = $this->service->urlFor($token);

        $this->assertStringStartsWith('http://example.test/shared/', $url);
        $this->assertStringContainsString($token->token_id, $url);
    }
}
