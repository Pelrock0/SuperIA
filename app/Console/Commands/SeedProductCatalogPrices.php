<?php

namespace App\Console\Commands;

use App\Models\ProductoCatalogo;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use Illuminate\Console\Command;

class SeedProductCatalogPrices extends Command
{
    protected $signature = 'prices:seed-catalog';

    protected $description = 'Generate price ranges (precio_min/precio_max) for all products in producto_catalogo via Claude. Idempotent: re-run overwrites.';

    protected $aliases = ['prices:refresh-catalog'];

    public function handle(ClaudeClientInterface $claude): int
    {
        $products = ProductoCatalogo::all(['id', 'nombre', 'categoria']);

        if ($products->isEmpty()) {
            $this->info('No products in catalog. Nothing to seed.');
            return self::SUCCESS;
        }

        $batchSize = (int) config('ai.prices.seed_batch_size', 50);
        $totalUpdated = 0;

        foreach ($products->chunk($batchSize) as $batch) {
            $input = $batch->map(fn ($p) => [
                'nombre' => $p->nombre,
                'categoria' => $p->categoria?->value ?? 'otros',
            ])->values()->all();

            try {
                $result = $claude->estimateCatalogPrices($input);
            } catch (ClaudeException $e) {
                $this->error("Claude error on batch: {$e->getMessage()}");
                continue;
            }

            foreach ($result['prices'] as $price) {
                $updated = ProductoCatalogo::query()
                    ->whereRaw('LOWER(nombre) = LOWER(?)', [$price['nombre']])
                    ->update([
                        'precio_min' => $price['precio_min'],
                        'precio_max' => $price['precio_max'],
                    ]);
                $totalUpdated += $updated;
            }
        }

        $this->info("Updated {$totalUpdated} products with price ranges.");

        return self::SUCCESS;
    }
}
