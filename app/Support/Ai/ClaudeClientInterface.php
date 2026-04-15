<?php

namespace App\Support\Ai;

use App\Support\Ai\Dto\Suggestion;

interface ClaudeClientInterface
{
    /**
     * Estimate price ranges for a batch of Spanish supermarket products.
     *
     * @param  array<int, array{nombre: string, categoria: ?string}>  $products
     * @return array{
     *     prices: array<int, array{nombre: string, precio_min: float, precio_max: float}>,
     *     estimated_cost_usd: float,
     * }
     *
     * @throws \App\Support\Ai\Exceptions\ClaudeException on any failure
     */
    public function estimateCatalogPrices(array $products): array;

    /**
     * @param  string  $userQuery  sanitized user query (already cleaned)
     * @param  string[]  $anonymizedContext  array of product name strings only (no PII)
     * @return array{suggestions: Suggestion[], estimated_cost_usd: float}
     *
     * @throws \App\Support\Ai\Exceptions\ClaudeException on any failure
     */
    public function suggest(string $userQuery, array $anonymizedContext): array;

    /**
     * Generate a batch of Spanish supermarket catalog entries.
     *
     * @param  int  $count  how many products to request in this batch
     * @param  int  $batchIndex  0-based index of the batch within a multi-batch run (passed to the prompt as a seed to reduce overlap)
     * @return array{products: array<int, array{nombre: string, categoria: ?string, unidad_tipica: ?string, cantidad_tipica: ?float}>, estimated_cost_usd: float}
     *
     * @throws \App\Support\Ai\Exceptions\ClaudeException on any failure
     */
    public function generateCatalog(int $count, int $batchIndex): array;

    /**
     * Ask Claude for products that are typically bought alongside the given product.
     *
     * @param  string  $productName  sanitized product name (already cleaned via PromptSanitizer)
     * @return array{products: array<int, array{nombre: string, unidad_tipica: ?string, categoria: ?string}>, estimated_cost_usd: float}
     *
     * @throws \App\Support\Ai\Exceptions\ClaudeException on any failure
     */
    public function suggestComplements(string $productName): array;

    /**
     * Generate a full shopping list from a natural language description.
     *
     * @param  array{description: string, people: int}  $context
     * @return array{
     *     products: array<int, array{nombre: string, cantidad_tipica: ?float, unidad_tipica: ?string, categoria: ?string, reason: ?string}>,
     *     estimated_cost_usd: float,
     * }
     *
     * @throws \App\Support\Ai\Exceptions\ClaudeException on any failure
     */
    public function generateListFromContext(array $context): array;

    /**
     * Generate a weekly shopping summary for a user based on their recent history and the current month.
     *
     * @param  array{
     *     history_weeks: array<int, array<int, string>>,
     *     active_list_items: array<int, string>,
     *     month: int,
     * }  $context  sanitized context: 4 weekly arrays of product names, current active list items, month int (1-12)
     * @return array{
     *     products: array<int, array{nombre: string, cantidad_tipica: ?float, unidad_tipica: ?string, categoria: ?string, reason: ?string}>,
     *     estimated_cost_usd: float,
     * }
     *
     * @throws \App\Support\Ai\Exceptions\ClaudeException on any failure
     */
    public function generateWeeklySummary(array $context): array;

    /**
     * Infer the category for a single product name.
     *
     * @param  string  $productName  sanitized product name
     * @return array{category: ?string, estimated_cost_usd: float}
     *
     * @throws \App\Support\Ai\Exceptions\ClaudeException on any failure
     */
    public function inferCategory(string $productName): array;
}
