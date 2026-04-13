<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductoCatalogoSeeder extends Seeder
{
    private const SEED_PATH = 'seeds/catalogo-productos.json';

    public function run(): void
    {
        $path = storage_path('app/'.self::SEED_PATH);

        if (! file_exists($path)) {
            throw new RuntimeException('Catalog seed file not found at '.$path);
        }

        $raw = file_get_contents($path);
        $rows = json_decode($raw, true);

        if (! is_array($rows)) {
            throw new RuntimeException('Catalog seed file is not a valid JSON array.');
        }

        DB::transaction(function () use ($rows) {
            $now = now();
            $batch = [];

            foreach ($rows as $row) {
                if (! isset($row['nombre'])) {
                    continue;
                }

                $batch[] = [
                    'nombre' => (string) $row['nombre'],
                    'categoria' => $row['categoria'] ?? null,
                    'unidad_tipica' => $row['unidad_tipica'] ?? null,
                    'cantidad_tipica' => $row['cantidad_tipica'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Clear and reinsert for idempotency.
            DB::table('producto_catalogo')->delete();

            foreach (array_chunk($batch, 200) as $chunk) {
                DB::table('producto_catalogo')->insert($chunk);
            }
        });
    }
}
