<?php

namespace Tests\Feature;

use App\Models\ProductoCatalogo;
use Database\Seeders\ProductoCatalogoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductoCatalogoSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeder_imports_catalog_from_json(): void
    {
        $this->seed(ProductoCatalogoSeeder::class);

        $count = ProductoCatalogo::count();
        $this->assertGreaterThanOrEqual(100, $count);
        $this->assertLessThanOrEqual(3000, $count);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ProductoCatalogoSeeder::class);
        $firstCount = ProductoCatalogo::count();

        $this->seed(ProductoCatalogoSeeder::class);
        $secondCount = ProductoCatalogo::count();

        $this->assertSame($firstCount, $secondCount);
    }

    public function test_seeder_covers_all_10_categories(): void
    {
        $this->seed(ProductoCatalogoSeeder::class);

        $categories = ProductoCatalogo::query()
            ->whereNotNull('categoria')
            ->distinct()
            ->pluck('categoria')
            ->all();

        $this->assertGreaterThanOrEqual(8, count($categories));
    }
}
