<?php

namespace Tests\Unit\Services;

use App\Models\ProductoHistorial;
use App\Models\User;
use App\Services\ProductHistoryWeightingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductHistoryWeightingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ProductHistoryWeightingService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductHistoryWeightingService();
    }

    private function record(User $user, string $name, string $when = 'now'): void
    {
        ProductoHistorial::create([
            'user_id' => $user->id,
            'producto_nombre' => $name,
            'categoria' => null,
            'cantidad' => null,
            'unidad' => null,
            'precio_real' => null,
            'fecha_compra' => $when === 'now' ? now() : now()->parse($when),
            'lista_id' => null,
        ]);
    }

    public function test_search_finds_prefix_match(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Leche entera');
        $this->record($user, 'Pan integral');

        $results = $this->service->search($user, 'le', 5);

        $this->assertCount(1, $results);
        $this->assertSame('Leche entera', $results[0]->name);
        $this->assertSame('history', $results[0]->source);
    }

    public function test_search_ranks_recent_higher_than_frequent_but_old(): void
    {
        $user = User::factory()->createOne();
        // Old but frequent (×5, 6 months ago)
        for ($i = 0; $i < 5; $i++) {
            $this->record($user, 'Yogurt', now()->subMonths(6)->toDateTimeString());
        }
        // Recent, less frequent (×3, this week)
        for ($i = 0; $i < 3; $i++) {
            $this->record($user, 'Yogures', now()->subDays(2)->toDateTimeString());
        }

        $results = $this->service->search($user, 'yog', 5);

        $this->assertSame('Yogures', $results[0]->name);
    }

    public function test_search_scopes_to_user(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->record($user, 'Mio');
        $this->record($other, 'Mio');

        $results = $this->service->search($user, 'mio', 5);

        $this->assertCount(1, $results);
    }

    public function test_search_respects_limit(): void
    {
        $user = User::factory()->createOne();
        foreach (['Leche', 'Leche entera', 'Leche desnatada', 'Leche sin lactosa', 'Leche de almendras', 'Leche de soja'] as $name) {
            $this->record($user, $name);
        }

        $results = $this->service->search($user, 'leche', 3);

        $this->assertCount(3, $results);
    }

    public function test_search_empty_query_returns_empty(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Leche');

        $this->assertSame([], $this->service->search($user, '', 5));
    }

    public function test_search_returns_dto_with_source_history(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Agua');

        $results = $this->service->search($user, 'agu', 5);

        $this->assertSame('history', $results[0]->source);
    }

    public function test_ranked_list_paginated_orders_by_weighted_score(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Pan');
        $this->record($user, 'Leche');
        $this->record($user, 'Leche');
        $this->record($user, 'Leche');

        $page = $this->service->rankedListPaginated($user, 20);

        /** @var list<\stdClass> $rows */
        $rows = $page->items();
        $this->assertSame('Leche', $rows[0]->producto_nombre);
    }

    public function test_ranked_list_excludes_other_users(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->record($user, 'Mio');
        $this->record($other, 'Suyo');

        $page = $this->service->rankedListPaginated($user, 20);

        $names = collect($page->items())->pluck('producto_nombre')->all();
        $this->assertSame(['Mio'], $names);
    }
}
