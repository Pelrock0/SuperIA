<?php

namespace Tests\Unit\Support\Ai;

use App\Support\Ai\ClaudeClient;
use App\Support\Ai\Exceptions\ClaudeException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaudeClientTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai.api_key' => 'sk-test-key',
            'ai.model' => 'claude-sonnet-4-6',
            'ai.api_base_url' => 'https://api.anthropic.com/v1',
            'ai.timeout_seconds' => 30,
            'ai.prompt.max_history_items_in_context' => 20,
        ]);
    }

    public function test_throws_when_api_key_missing(): void
    {
        config(['ai.api_key' => null]);

        $this->expectException(ClaudeException::class);

        (new ClaudeClient())->suggest('leche', []);
    }

    public function test_parses_valid_response_into_suggestions(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => '[{"name":"Leche entera","unit":"L","category":"lacteos_huevos","quantity":1}]',
                    ],
                ],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
        ]);

        $result = (new ClaudeClient())->suggest('leche', ['Pan', 'Agua']);

        $this->assertCount(1, $result['suggestions']);
        $this->assertSame('Leche entera', $result['suggestions'][0]->name);
        $this->assertSame('lacteos_huevos', $result['suggestions'][0]->category);
        $this->assertGreaterThan(0, $result['estimated_cost_usd']);
    }

    public function test_caps_at_5_suggestions(): void
    {
        $items = array_map(
            fn ($i) => ['name' => "Item{$i}", 'unit' => 'ud', 'category' => 'otros', 'quantity' => 1],
            range(1, 10),
        );
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode($items)]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $result = (new ClaudeClient())->suggest('x', []);

        $this->assertCount(5, $result['suggestions']);
    }

    public function test_throws_on_invalid_json(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'this is not json']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $this->expectException(ClaudeException::class);

        (new ClaudeClient())->suggest('x', []);
    }

    public function test_throws_on_missing_content(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [],
                'usage' => [],
            ], 200),
        ]);

        $this->expectException(ClaudeException::class);

        (new ClaudeClient())->suggest('x', []);
    }

    public function test_throws_on_http_failure(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(ClaudeException::class);

        (new ClaudeClient())->suggest('x', []);
    }

    public function test_extracts_embedded_json_array(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => 'Here you go: [{"name":"Leche","unit":"L","category":"lacteos_huevos","quantity":1}] done.',
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $result = (new ClaudeClient())->suggest('x', []);

        $this->assertCount(1, $result['suggestions']);
        $this->assertSame('Leche', $result['suggestions'][0]->name);
    }

    public function test_uses_fallback_cost_when_usage_missing(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '[]']],
            ], 200),
        ]);

        $result = (new ClaudeClient())->suggest('x', []);

        $this->assertSame(
            (float) config('ai.cost_estimation.fallback_per_call_usd', 0.01),
            $result['estimated_cost_usd'],
        );
    }

    public function test_sends_api_key_header(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '[]']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        (new ClaudeClient())->suggest('x', []);

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-api-key', 'sk-test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01');
        });
    }

    public function test_generate_catalog_parses_valid_response(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '[{"nombre":"Manzana","categoria":"frutas_verduras","unidad_tipica":"kg","cantidad_tipica":1}]',
                ]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ], 200),
        ]);

        $result = (new ClaudeClient())->generateCatalog(1, 0);

        $this->assertCount(1, $result['products']);
        $this->assertSame('Manzana', $result['products'][0]['nombre']);
        $this->assertSame('frutas_verduras', $result['products'][0]['categoria']);
        $this->assertSame('kg', $result['products'][0]['unidad_tipica']);
        $this->assertSame(1.0, $result['products'][0]['cantidad_tipica']);
        $this->assertGreaterThan(0, $result['estimated_cost_usd']);
    }

    public function test_generate_catalog_throws_on_missing_api_key(): void
    {
        config(['ai.api_key' => null]);

        $this->expectException(\App\Support\Ai\Exceptions\ClaudeException::class);

        (new ClaudeClient())->generateCatalog(5, 0);
    }

    public function test_generate_catalog_skips_entries_without_nombre(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '[{"nombre":"Valid"},{"categoria":"otros"}]',
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $result = (new ClaudeClient())->generateCatalog(2, 0);

        $this->assertCount(1, $result['products']);
        $this->assertSame('Valid', $result['products'][0]['nombre']);
    }

    public function test_generate_catalog_throws_on_invalid_json(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'not json']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $this->expectException(\App\Support\Ai\Exceptions\ClaudeException::class);

        (new ClaudeClient())->generateCatalog(1, 0);
    }

    public function test_generate_catalog_sends_batch_index_in_user_message(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '[]']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        (new ClaudeClient())->generateCatalog(10, 3);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $userMessage = $body['messages'][0]['content'] ?? '';
            return str_contains($userMessage, 'batch #3')
                && str_contains($userMessage, '10');
        });
    }

    public function test_suggest_complements_parses_valid_response(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '[{"nombre":"Tomate frito","unidad_tipica":"ud","categoria":"conservas"}]',
                ]],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 25],
            ], 200),
        ]);

        $result = (new ClaudeClient())->suggestComplements('pasta');

        $this->assertCount(1, $result['products']);
        $this->assertSame('Tomate frito', $result['products'][0]['nombre']);
        $this->assertSame('ud', $result['products'][0]['unidad_tipica']);
        $this->assertSame('conservas', $result['products'][0]['categoria']);
        $this->assertGreaterThan(0, $result['estimated_cost_usd']);
    }

    public function test_suggest_complements_caps_at_2(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '[{"nombre":"A"},{"nombre":"B"},{"nombre":"C"},{"nombre":"D"}]',
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $result = (new ClaudeClient())->suggestComplements('x');

        $this->assertCount(2, $result['products']);
    }

    public function test_suggest_complements_throws_on_missing_api_key(): void
    {
        config(['ai.api_key' => null]);

        $this->expectException(\App\Support\Ai\Exceptions\ClaudeException::class);

        (new ClaudeClient())->suggestComplements('pasta');
    }

    public function test_suggest_complements_throws_on_invalid_json(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'not json']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
            ], 200),
        ]);

        $this->expectException(\App\Support\Ai\Exceptions\ClaudeException::class);

        (new ClaudeClient())->suggestComplements('pasta');
    }

    public function test_suggest_complements_sends_product_in_message(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '[]']],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);

        (new ClaudeClient())->suggestComplements('pasta al dente');

        Http::assertSent(function ($request) {
            $body = $request->data();
            $userMessage = $body['messages'][0]['content'] ?? '';
            return str_contains($userMessage, 'pasta al dente');
        });
    }
}
