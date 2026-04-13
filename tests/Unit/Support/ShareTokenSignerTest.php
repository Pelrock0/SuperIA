<?php

namespace Tests\Unit\Support;

use App\Support\ShareTokenSigner;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ShareTokenSignerTest extends TestCase
{
    public function test_sign_is_deterministic(): void
    {
        $a = ShareTokenSigner::sign('token-1', 42, 'edit');
        $b = ShareTokenSigner::sign('token-1', 42, 'edit');

        $this->assertSame($a, $b);
    }

    public function test_sign_differs_per_payload(): void
    {
        $edit = ShareTokenSigner::sign('token-1', 42, 'edit');
        $read = ShareTokenSigner::sign('token-1', 42, 'read_only');
        $otherId = ShareTokenSigner::sign('token-2', 42, 'edit');
        $otherList = ShareTokenSigner::sign('token-1', 43, 'edit');

        $this->assertNotSame($edit, $read);
        $this->assertNotSame($edit, $otherId);
        $this->assertNotSame($edit, $otherList);
    }

    public function test_url_token_contains_token_id_and_signature(): void
    {
        $url = ShareTokenSigner::urlToken('abcd-1234', 7, 'edit');

        $this->assertStringContainsString('abcd-1234.', $url);
    }

    public function test_parse_splits_token_id_and_signature(): void
    {
        $url = ShareTokenSigner::urlToken('abc', 1, 'edit');

        $parsed = ShareTokenSigner::parse($url);

        $this->assertIsArray($parsed);
        $this->assertSame('abc', $parsed['token_id']);
        $this->assertNotEmpty($parsed['signature']);
    }

    public function test_parse_returns_null_when_no_dot(): void
    {
        $this->assertNull(ShareTokenSigner::parse('no-dot-token'));
    }

    public function test_parse_returns_null_on_empty_halves(): void
    {
        $this->assertNull(ShareTokenSigner::parse('.sig'));
        $this->assertNull(ShareTokenSigner::parse('id.'));
    }

    public function test_verify_accepts_valid_signature(): void
    {
        $signature = ShareTokenSigner::sign('abc', 9, 'edit');

        $this->assertTrue(ShareTokenSigner::verify($signature, 'abc', 9, 'edit'));
    }

    public function test_verify_rejects_tampered_signature(): void
    {
        $this->assertFalse(ShareTokenSigner::verify('tampered', 'abc', 9, 'edit'));
    }

    public function test_raw_key_supports_plain_app_key(): void
    {
        Config::set('app.key', 'plain-unwrapped-key-123');

        $signature = ShareTokenSigner::sign('abc', 1, 'edit');

        $this->assertNotEmpty($signature);
    }

    public function test_throws_when_app_key_missing(): void
    {
        Config::set('app.key', '');

        $this->expectException(\InvalidArgumentException::class);

        ShareTokenSigner::sign('abc', 1, 'edit');
    }
}
