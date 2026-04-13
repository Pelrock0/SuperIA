<?php

namespace Tests\Unit\Services;

use App\Enums\ProductCategory;
use App\Models\ProductoCatalogo;
use App\Services\CategoryInferenceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CategoryInferenceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CategoryInferenceService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CategoryInferenceService();
    }

    public function test_infers_category_from_catalog(): void
    {
        ProductoCatalogo::factory()->createOne([
            'nombre' => 'Leche entera',
            'categoria' => 'lacteos_huevos',
        ]);

        $result = $this->service->infer('Leche entera');

        $this->assertSame(ProductCategory::LacteosHuevos, $result);
    }

    public function test_case_insensitive_match(): void
    {
        ProductoCatalogo::factory()->createOne([
            'nombre' => 'TOMATES',
            'categoria' => 'frutas_verduras',
        ]);

        $result = $this->service->infer('tomates');

        $this->assertSame(ProductCategory::FrutasVerduras, $result);
    }

    public function test_returns_null_when_not_in_catalog(): void
    {
        $result = $this->service->infer('Salsa especial casera');

        $this->assertNull($result);
    }

    public function test_returns_null_for_empty_name(): void
    {
        $result = $this->service->infer('');

        $this->assertNull($result);
    }

    public function test_returns_null_for_whitespace_name(): void
    {
        $result = $this->service->infer('   ');

        $this->assertNull($result);
    }
}
