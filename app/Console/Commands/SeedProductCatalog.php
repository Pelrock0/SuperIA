<?php

namespace App\Console\Commands;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Enums\ItemUnit;
use App\Enums\ProductCategory;
use App\Support\Ai\BudgetCap;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use Illuminate\Console\Command;

class SeedProductCatalog extends Command
{
    protected $signature = 'ai:seed-catalog
        {--total=250 : Total number of products to generate}
        {--batch-size=50 : Number of products per Claude call}
        {--dry-run : Show stats without writing the JSON file}
        {--output= : Override the default output path}';

    protected $description = 'Generate the Spanish supermarket catalog JSON via Claude API. Use this to refresh storage/app/seeds/catalogo-productos.json. Respects BudgetCap; does not touch the database.';

    public function handle(ClaudeClientInterface $claude, BudgetCap $budgetCap): int
    {
        $total = max(1, (int) $this->option('total'));
        $batchSize = max(1, (int) $this->option('batch-size'));
        $dryRun = (bool) $this->option('dry-run');
        $outputPath = (string) ($this->option('output') ?: storage_path('app/seeds/catalogo-productos.json'));

        $batches = (int) ceil($total / $batchSize);

        $this->info("Generating catalog: total={$total}, batch_size={$batchSize}, batches={$batches}, dry_run=".($dryRun ? 'yes' : 'no'));

        $validCategories = array_map(fn (ProductCategory $c) => $c->value, ProductCategory::cases());
        $validUnits = array_map(fn (ItemUnit $u) => $u->value, ItemUnit::cases());

        $collected = [];
        $seenNames = [];
        $stats = [
            'requested' => 0,
            'received' => 0,
            'valid' => 0,
            'invalid_category' => 0,
            'invalid_unit' => 0,
            'invalid_missing_name' => 0,
            'duplicates' => 0,
            'total_cost_usd' => 0.0,
        ];

        for ($i = 0; $i < $batches; $i++) {
            $requestCount = min($batchSize, $total - $stats['valid']);
            if ($requestCount <= 0) {
                break;
            }

            if (! $budgetCap->canSpend()) {
                $this->error('Budget cap exceeded before batch '.($i + 1).'. Aborting.');
                $budgetCap->notifyIfExceeded();
                return self::FAILURE;
            }

            $stats['requested'] += $requestCount;
            $this->line("Batch ".($i + 1)."/{$batches}: requesting {$requestCount} products...");

            try {
                $result = $claude->generateCatalog($requestCount, $i);
            } catch (ClaudeException $e) {
                $this->error("Claude error on batch ".($i + 1).": ".$e->getMessage());
                return self::FAILURE;
            }

            $products = $result['products'] ?? [];
            $stats['received'] += count($products);
            $stats['total_cost_usd'] += (float) ($result['estimated_cost_usd'] ?? 0);

            foreach ($products as $row) {
                $nombre = isset($row['nombre']) ? trim((string) $row['nombre']) : '';
                if ($nombre === '') {
                    $stats['invalid_missing_name']++;
                    continue;
                }

                $categoria = $row['categoria'] ?? null;
                if ($categoria !== null && ! in_array($categoria, $validCategories, true)) {
                    $stats['invalid_category']++;
                    $categoria = null;
                }

                $unidad = $row['unidad_tipica'] ?? null;
                if ($unidad !== null && ! in_array($unidad, $validUnits, true)) {
                    $stats['invalid_unit']++;
                    $unidad = null;
                }

                $key = mb_strtolower($nombre);
                if (isset($seenNames[$key])) {
                    $stats['duplicates']++;
                    continue;
                }
                $seenNames[$key] = true;

                $collected[] = [
                    'nombre' => mb_substr($nombre, 0, 80),
                    'categoria' => $categoria,
                    'unidad_tipica' => $unidad,
                    'cantidad_tipica' => isset($row['cantidad_tipica']) ? (float) $row['cantidad_tipica'] : null,
                ];
                $stats['valid']++;

                if ($stats['valid'] >= $total) {
                    break 2;
                }
            }
        }

        $this->newLine();
        $this->info('Results:');
        $this->line("  Requested:         {$stats['requested']}");
        $this->line("  Received:          {$stats['received']}");
        $this->line("  Valid:             {$stats['valid']}");
        $this->line("  Duplicates:        {$stats['duplicates']}");
        $this->line("  Invalid category:  {$stats['invalid_category']}");
        $this->line("  Invalid unit:      {$stats['invalid_unit']}");
        $this->line("  Missing name:      {$stats['invalid_missing_name']}");
        $this->line("  Estimated cost:    $".number_format($stats['total_cost_usd'], 4));

        if ($dryRun) {
            $this->warn('Dry run — no file written.');
            return self::SUCCESS;
        }

        if ($stats['valid'] === 0) {
            $this->error('No valid products collected. Not overwriting existing file.');
            return self::FAILURE;
        }

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($collected, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error('Failed to encode catalog to JSON.');
            return self::FAILURE;
        }

        file_put_contents($outputPath, $json.PHP_EOL);

        $this->info("Wrote {$stats['valid']} products to {$outputPath}");
        $this->line('Next: run `php artisan db:seed --class=ProductoCatalogoSeeder` to load into the database.');

        return self::SUCCESS;
    }
}
