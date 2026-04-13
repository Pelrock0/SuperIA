<?php

namespace App\Support\Ai;

use App\Support\Ai\Dto\Suggestion;
use App\Support\Ai\Exceptions\ClaudeException;

class FakeClaudeClient implements ClaudeClientInterface
{
    /** @var Suggestion[] */
    public array $cannedSuggestions = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    public array $cannedCatalogBatches = [];

    public ?\Throwable $shouldThrow = null;

    public float $cannedCost = 0.001;

    public ?array $lastQuery = null;

    /** @var array<int, array{count: int, batchIndex: int}> */
    public array $catalogCalls = [];

    /** @var array<int, array<string, mixed>> */
    public array $cannedComplements = [];

    /** @var array<int, array{product: string}> */
    public array $complementCalls = [];

    /** @var array<int, array{nombre: string, precio_min: float, precio_max: float}> */
    public array $cannedCatalogPrices = [];

    /** @var array<int, array{products: array<int, array<string, mixed>>}> */
    public array $catalogPriceCalls = [];

    /** @var array<int, array{nombre: string, cantidad_tipica: ?float, unidad_tipica: ?string, categoria: ?string, reason: ?string}> */
    public array $cannedListGeneration = [];

    /** @var array<int, array{context: array<string, mixed>}> */
    public array $listGenerationCalls = [];

    /** @var array<int, array{nombre: string, cantidad_tipica: ?float, unidad_tipica: ?string, categoria: ?string, reason: ?string}> */
    public array $cannedWeeklySummary = [];

    /** @var array<int, array{context: array<string, mixed>}> */
    public array $weeklySummaryCalls = [];

    #[\Override]
    public function suggest(string $userQuery, array $anonymizedContext): array
    {
        $this->lastQuery = [
            'query' => $userQuery,
            'context' => $anonymizedContext,
        ];

        if ($this->shouldThrow !== null) {
            throw $this->shouldThrow instanceof ClaudeException
                ? $this->shouldThrow
                : new ClaudeException($this->shouldThrow->getMessage(), 0, $this->shouldThrow);
        }

        return [
            'suggestions' => $this->cannedSuggestions,
            'estimated_cost_usd' => $this->cannedCost,
        ];
    }

    #[\Override]
    public function generateCatalog(int $count, int $batchIndex): array
    {
        $this->catalogCalls[] = ['count' => $count, 'batchIndex' => $batchIndex];

        if ($this->shouldThrow !== null) {
            throw $this->shouldThrow instanceof ClaudeException
                ? $this->shouldThrow
                : new ClaudeException($this->shouldThrow->getMessage(), 0, $this->shouldThrow);
        }

        $batch = $this->cannedCatalogBatches[$batchIndex] ?? [];

        return [
            'products' => $batch,
            'estimated_cost_usd' => $this->cannedCost,
        ];
    }

    #[\Override]
    public function suggestComplements(string $productName): array
    {
        $this->complementCalls[] = ['product' => $productName];

        if ($this->shouldThrow !== null) {
            throw $this->shouldThrow instanceof ClaudeException
                ? $this->shouldThrow
                : new ClaudeException($this->shouldThrow->getMessage(), 0, $this->shouldThrow);
        }

        return [
            'products' => $this->cannedComplements,
            'estimated_cost_usd' => $this->cannedCost,
        ];
    }

    #[\Override]
    public function estimateCatalogPrices(array $products): array
    {
        $this->catalogPriceCalls[] = ['products' => $products];

        if ($this->shouldThrow !== null) {
            throw $this->shouldThrow instanceof ClaudeException
                ? $this->shouldThrow
                : new ClaudeException($this->shouldThrow->getMessage(), 0, $this->shouldThrow);
        }

        return [
            'prices' => $this->cannedCatalogPrices,
            'estimated_cost_usd' => $this->cannedCost,
        ];
    }

    #[\Override]
    public function generateListFromContext(array $context): array
    {
        $this->listGenerationCalls[] = ['context' => $context];

        if ($this->shouldThrow !== null) {
            throw $this->shouldThrow instanceof ClaudeException
                ? $this->shouldThrow
                : new ClaudeException($this->shouldThrow->getMessage(), 0, $this->shouldThrow);
        }

        return [
            'products' => $this->cannedListGeneration,
            'estimated_cost_usd' => $this->cannedCost,
        ];
    }

    #[\Override]
    public function generateWeeklySummary(array $context): array
    {
        $this->weeklySummaryCalls[] = ['context' => $context];

        if ($this->shouldThrow !== null) {
            throw $this->shouldThrow instanceof ClaudeException
                ? $this->shouldThrow
                : new ClaudeException($this->shouldThrow->getMessage(), 0, $this->shouldThrow);
        }

        return [
            'products' => $this->cannedWeeklySummary,
            'estimated_cost_usd' => $this->cannedCost,
        ];
    }
}
