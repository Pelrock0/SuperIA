<?php

namespace Tests\Feature;

use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\FakeClaudeClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeedProductCatalogCommandTest extends TestCase
{
    use DatabaseTransactions;

    private FakeClaudeClient $fakeClaude;

    private string $outputPath;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->fakeClaude = new FakeClaudeClient();
        $this->app->instance(ClaudeClientInterface::class, $this->fakeClaude);

        $this->outputPath = storage_path('app/seeds/test-catalogo-'.uniqid().'.json');
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (file_exists($this->outputPath)) {
            unlink($this->outputPath);
        }
        parent::tearDown();
    }

    private function makeProduct(string $nombre, string $categoria = 'frutas_verduras', string $unidad = 'kg', float $cantidad = 1.0): array
    {
        return [
            'nombre' => $nombre,
            'categoria' => $categoria,
            'unidad_tipica' => $unidad,
            'cantidad_tipica' => $cantidad,
        ];
    }

    public function test_dry_run_does_not_write_file(): void
    {
        $this->fakeClaude->cannedCatalogBatches[0] = [
            $this->makeProduct('Manzana'),
            $this->makeProduct('Platano'),
        ];

        $this->artisan('ai:seed-catalog', [
            '--total' => 2,
            '--batch-size' => 2,
            '--dry-run' => true,
            '--output' => $this->outputPath,
        ])->assertSuccessful();

        $this->assertFileDoesNotExist($this->outputPath);
    }

    public function test_writes_valid_products_to_json(): void
    {
        $this->fakeClaude->cannedCatalogBatches[0] = [
            $this->makeProduct('Manzana'),
            $this->makeProduct('Platano'),
            $this->makeProduct('Naranja'),
        ];

        $this->artisan('ai:seed-catalog', [
            '--total' => 3,
            '--batch-size' => 3,
            '--output' => $this->outputPath,
        ])->assertSuccessful();

        $this->assertFileExists($this->outputPath);
        /** @var list<array<string, mixed>> $contents */
        $contents = json_decode(file_get_contents($this->outputPath), true);
        $this->assertCount(3, $contents);
        $this->assertSame('Manzana', $contents[0]['nombre']);
    }

    public function test_drops_invalid_categories(): void
    {
        $this->fakeClaude->cannedCatalogBatches[0] = [
            $this->makeProduct('Valid', 'frutas_verduras'),
            $this->makeProduct('Invalid', 'banana_republic'),
        ];

        $this->artisan('ai:seed-catalog', [
            '--total' => 2,
            '--batch-size' => 2,
            '--output' => $this->outputPath,
        ])->assertSuccessful();

        /** @var list<array<string, mixed>> $contents */
        $contents = json_decode(file_get_contents($this->outputPath), true);
        $this->assertCount(2, $contents);
        $this->assertNull($contents[1]['categoria']);
        $this->assertSame('Invalid', $contents[1]['nombre']);
    }

    public function test_drops_invalid_units(): void
    {
        $this->fakeClaude->cannedCatalogBatches[0] = [
            $this->makeProduct('Valid', 'frutas_verduras', 'kg'),
            $this->makeProduct('Weird', 'frutas_verduras', 'cucharadas'),
        ];

        $this->artisan('ai:seed-catalog', [
            '--total' => 2,
            '--batch-size' => 2,
            '--output' => $this->outputPath,
        ])->assertSuccessful();

        /** @var list<array<string, mixed>> $contents */
        $contents = json_decode(file_get_contents($this->outputPath), true);
        $this->assertCount(2, $contents);
        $this->assertNull($contents[1]['unidad_tipica']);
    }

    public function test_deduplicates_case_insensitive(): void
    {
        $this->fakeClaude->cannedCatalogBatches[0] = [
            $this->makeProduct('Manzana'),
            $this->makeProduct('MANZANA'),
            $this->makeProduct('manzana'),
            $this->makeProduct('Pera'),
        ];

        $this->artisan('ai:seed-catalog', [
            '--total' => 4,
            '--batch-size' => 4,
            '--output' => $this->outputPath,
        ])->assertSuccessful();

        /** @var list<array<string, mixed>> $contents */
        $contents = json_decode(file_get_contents($this->outputPath), true);
        $this->assertCount(2, $contents);
    }

    public function test_drops_entries_with_missing_name(): void
    {
        $this->fakeClaude->cannedCatalogBatches[0] = [
            $this->makeProduct('Valid'),
            ['nombre' => '', 'categoria' => 'frutas_verduras', 'unidad_tipica' => 'kg', 'cantidad_tipica' => 1],
        ];

        $this->artisan('ai:seed-catalog', [
            '--total' => 2,
            '--batch-size' => 2,
            '--output' => $this->outputPath,
        ])->assertSuccessful();

        /** @var list<array<string, mixed>> $contents */
        $contents = json_decode(file_get_contents($this->outputPath), true);
        $this->assertCount(1, $contents);
    }

    public function test_stops_when_total_reached_across_batches(): void
    {
        $this->fakeClaude->cannedCatalogBatches[0] = [
            $this->makeProduct('A'),
            $this->makeProduct('B'),
            $this->makeProduct('C'),
        ];
        $this->fakeClaude->cannedCatalogBatches[1] = [
            $this->makeProduct('D'),
            $this->makeProduct('E'),
        ];

        $this->artisan('ai:seed-catalog', [
            '--total' => 4,
            '--batch-size' => 3,
            '--output' => $this->outputPath,
        ])->assertSuccessful();

        /** @var list<array<string, mixed>> $contents */
        $contents = json_decode(file_get_contents($this->outputPath), true);
        $this->assertCount(4, $contents);
    }

    public function test_aborts_when_budget_cap_exceeded(): void
    {
        config(['ai.budget_cap_monthly_usd' => 1]);
        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 5,
        ]);

        $this->fakeClaude->cannedCatalogBatches[0] = [$this->makeProduct('Manzana')];

        $this->artisan('ai:seed-catalog', [
            '--total' => 1,
            '--batch-size' => 1,
            '--output' => $this->outputPath,
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->outputPath);
    }

    public function test_fails_on_claude_error(): void
    {
        $this->fakeClaude->shouldThrow = new ClaudeException('boom');

        $this->artisan('ai:seed-catalog', [
            '--total' => 1,
            '--batch-size' => 1,
            '--output' => $this->outputPath,
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->outputPath);
    }

    public function test_fails_when_no_valid_products(): void
    {
        $this->fakeClaude->cannedCatalogBatches[0] = [
            ['nombre' => '', 'categoria' => 'otros'],
        ];

        $this->artisan('ai:seed-catalog', [
            '--total' => 1,
            '--batch-size' => 1,
            '--output' => $this->outputPath,
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->outputPath);
    }

    public function test_passes_correct_batch_index_to_claude(): void
    {
        $this->fakeClaude->cannedCatalogBatches[0] = [$this->makeProduct('A')];
        $this->fakeClaude->cannedCatalogBatches[1] = [$this->makeProduct('B')];
        $this->fakeClaude->cannedCatalogBatches[2] = [$this->makeProduct('C')];

        $this->artisan('ai:seed-catalog', [
            '--total' => 3,
            '--batch-size' => 1,
            '--output' => $this->outputPath,
        ])->assertSuccessful();

        $this->assertCount(3, $this->fakeClaude->catalogCalls);
        $this->assertSame(0, $this->fakeClaude->catalogCalls[0]['batchIndex']);
        $this->assertSame(1, $this->fakeClaude->catalogCalls[1]['batchIndex']);
        $this->assertSame(2, $this->fakeClaude->catalogCalls[2]['batchIndex']);
    }

    public function test_truncates_names_over_80_chars(): void
    {
        $longName = str_repeat('a', 100);
        $this->fakeClaude->cannedCatalogBatches[0] = [$this->makeProduct($longName)];

        $this->artisan('ai:seed-catalog', [
            '--total' => 1,
            '--batch-size' => 1,
            '--output' => $this->outputPath,
        ])->assertSuccessful();

        /** @var list<array<string, mixed>> $contents */
        $contents = json_decode(file_get_contents($this->outputPath), true);
        $this->assertSame(80, mb_strlen($contents[0]['nombre']));
    }
}
