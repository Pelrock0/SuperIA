<?php

namespace App\Support\Ai;

use App\Models\AiPrompt;
use App\Support\Ai\Dto\Suggestion;
use App\Support\Ai\Exceptions\ClaudeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeClient implements ClaudeClientInterface
{
    private const SYSTEM_PROMPT = <<<PROMPT
You suggest Spanish supermarket products. Return a strict JSON array of up to 5 objects with keys: name (Spanish product name), unit (one of: kg, g, L, ml, ud, pack), category (one of: frutas_verduras, carnes_pescados, lacteos_huevos, panaderia, bebidas, congelados, limpieza, higiene_personal, conservas, otros), quantity (numeric).
Respond ONLY with the JSON array. No prose, no markdown fences, no commentary.
PROMPT;

    private const CATALOG_SYSTEM_PROMPT = <<<PROMPT
You generate entries for a Spanish supermarket product catalog. Return a strict JSON array of objects with keys: nombre (Spanish product name, generic, no brand), categoria (one of: frutas_verduras, carnes_pescados, lacteos_huevos, panaderia, bebidas, congelados, limpieza, higiene_personal, conservas, otros), unidad_tipica (one of: kg, g, L, ml, ud, pack), cantidad_tipica (numeric, typical purchase quantity).
Rules:
- NO brand names. Only generic products.
- Cover all 10 categorias evenly.
- Every product must be a real typical Spanish supermarket item.
- Use natural Spanish product names.
- Avoid duplicates within the same batch.
Respond ONLY with the JSON array. No prose, no markdown fences, no commentary.
PROMPT;

    private const COMPLEMENTS_SYSTEM_PROMPT = <<<PROMPT
Eres un asistente que sugiere productos complementarios para listas de compra espanolas. Cuando el usuario anade un producto, responde con hasta 2 productos que tipicamente se compran junto con el.
Devuelve un array JSON estricto de hasta 2 objetos con claves: nombre (nombre generico en espanol, sin marca), unidad_tipica (kg, g, L, ml, ud, pack), categoria (frutas_verduras, carnes_pescados, lacteos_huevos, panaderia, bebidas, congelados, limpieza, higiene_personal, conservas, otros).
Reglas:
- Sin marcas comerciales.
- Productos reales tipicos de supermercado espanol.
- Solo 2 productos complementarios como maximo.
Responde SOLO con el array JSON. Sin prosa, sin markdown, sin comentarios.
PROMPT;

    private const PRICE_SEED_SYSTEM_PROMPT = <<<PROMPT
Eres un experto en precios de supermercados espanoles. Para cada producto, estima el rango de precio tipico en EUR (minimo y maximo) que un consumidor encontraria en un supermercado medio en Espana en 2025. Devuelve un array JSON estricto con un objeto por producto: nombre (exactamente como se proporciona), precio_min (float, en EUR), precio_max (float, en EUR). Reglas: precios en EUR sin simbolo, rango realista (minimo en supermercados economicos como Mercadona/Dia, maximo en premium como El Corte Ingles), si no conoces el precio exacto estima por categoria. Responde SOLO con el array JSON.
PROMPT;

    private const LIST_GENERATION_SYSTEM_PROMPT = <<<PROMPT
Eres un asistente que genera listas de compra completas para hogares en Espana. Recibes una descripcion en lenguaje natural de lo que el usuario necesita y el numero de personas. Devuelve un array JSON estricto de hasta 25 objetos con claves: nombre (nombre generico en espanol, sin marca), cantidad_tipica (numerico, ajustado al numero de personas indicado), unidad_tipica (kg, g, L, ml, ud, pack), categoria (frutas_verduras, carnes_pescados, lacteos_huevos, panaderia, bebidas, congelados, limpieza, higiene_personal, conservas, otros), reason (frase corta explicando por que se incluye).
Reglas:
- Sin marcas comerciales.
- Cantidades ajustadas al numero de personas indicado.
- Redondea todas las cantidades a unidades comerciales disponibles en supermercados espanoles.
- Maximo 25 productos.
- Contexto geografico: Espana.
Responde SOLO con el array JSON. Sin prosa, sin markdown, sin comentarios.
PROMPT;

    private const WEEKLY_SUMMARY_SYSTEM_PROMPT = <<<PROMPT
Eres un asistente que genera un resumen semanal de compra para usuarios en Espana. Recibes: (1) el historial anonimizado de las ultimas 4 semanas del usuario separado por semanas, (2) los items de la lista activa actual si existe, (3) el mes del ano (entero 1-12) para que puedas razonar sobre estacionalidad tipica espanola.
Devuelve un array JSON estricto de hasta 8 objetos con claves: nombre (nombre generico en espanol, sin marca), cantidad_tipica (numerico, opcional), unidad_tipica (kg, g, L, ml, ud, pack, opcional), categoria (frutas_verduras, carnes_pescados, lacteos_huevos, panaderia, bebidas, congelados, limpieza, higiene_personal, conservas, otros; opcional), reason (frase corta en espanol explicando por que se sugiere, opcional).
Reglas:
- Sin marcas comerciales.
- Priorizar productos con patron de recompra (aparecen en mas de una semana del historial).
- Incluir 1-2 sugerencias estacionales coherentes con el mes (contexto geografico: Espana).
- Excluir productos que ya estan en la lista activa actual.
- Maximo 8 productos.
Responde SOLO con el array JSON. Sin prosa, sin markdown, sin comentarios.
PROMPT;

    #[\Override]
    public function suggest(string $userQuery, array $anonymizedContext): array
    {
        $apiKey = config('ai.api_key');
        if (! $apiKey) {
            throw new ClaudeException('Claude API key not configured.');
        }

        $payload = [
            'model' => config('ai.model'),
            'max_tokens' => 512,
            'system' => AiPrompt::getContent('autocomplete', self::SYSTEM_PROMPT),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $this->buildUserMessage($userQuery, $anonymizedContext),
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout((int) config('ai.timeout_seconds', 30))
                ->post(rtrim((string) config('ai.api_base_url'), '/').'/messages', $payload)
                ->throw();
        } catch (ConnectionException $e) {
            throw new ClaudeException('Claude API connection failed: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new ClaudeException('Claude API request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();

        return [
            'suggestions' => $this->parseSuggestions($body),
            'estimated_cost_usd' => $this->estimateCost($body),
        ];
    }

    #[\Override]
    public function generateCatalog(int $count, int $batchIndex): array
    {
        $apiKey = config('ai.api_key');
        if (! $apiKey) {
            throw new ClaudeException('Claude API key not configured.');
        }

        $payload = [
            'model' => config('ai.model'),
            'max_tokens' => min(8192, (int) ($count * 80)),
            'system' => AiPrompt::getContent('catalog', self::CATALOG_SYSTEM_PROMPT),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Generate exactly {$count} unique Spanish supermarket products for batch #{$batchIndex}. Cover the 10 categorias evenly (roughly equal counts). Avoid obvious overlap with other batches by varying across subcategories.",
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout((int) config('ai.seed_timeout_seconds', 90))
                ->post(rtrim((string) config('ai.api_base_url'), '/').'/messages', $payload)
                ->throw();
        } catch (ConnectionException $e) {
            throw new ClaudeException('Claude API connection failed: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new ClaudeException('Claude API request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();

        return [
            'products' => $this->parseCatalogEntries($body),
            'estimated_cost_usd' => $this->estimateCost($body),
        ];
    }

    #[\Override]
    public function suggestComplements(string $productName): array
    {
        $apiKey = config('ai.api_key');
        if (! $apiKey) {
            throw new ClaudeException('Claude API key not configured.');
        }

        $payload = [
            'model' => config('ai.model'),
            'max_tokens' => 256,
            'system' => AiPrompt::getContent('complements', self::COMPLEMENTS_SYSTEM_PROMPT),
            'messages' => [[
                'role' => 'user',
                'content' => "Producto: {$productName}",
            ]],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout((int) config('ai.timeout_seconds', 30))
                ->post(rtrim((string) config('ai.api_base_url'), '/').'/messages', $payload)
                ->throw();
        } catch (ConnectionException $e) {
            throw new ClaudeException('Claude API connection failed: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new ClaudeException('Claude API request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();

        return [
            'products' => $this->parseComplementEntries($body),
            'estimated_cost_usd' => $this->estimateCost($body),
        ];
    }

    #[\Override]
    public function estimateCatalogPrices(array $products): array
    {
        $apiKey = config('ai.api_key');
        if (! $apiKey) {
            throw new ClaudeException('Claude API key not configured.');
        }

        $productList = implode("\n", array_map(
            fn ($p) => "- {$p['nombre']} (categoria: ".($p['categoria'] ?? 'otros').")",
            $products,
        ));

        $payload = [
            'model' => config('ai.prices.seed_model', config('ai.model')),
            'max_tokens' => (int) config('ai.prices.seed_max_tokens', 4000),
            'system' => AiPrompt::getContent('price-estimation', self::PRICE_SEED_SYSTEM_PROMPT),
            'messages' => [[
                'role' => 'user',
                'content' => "Productos:\n{$productList}",
            ]],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout((int) config('ai.seed_timeout_seconds', 90))
                ->post(rtrim((string) config('ai.api_base_url'), '/').'/messages', $payload)
                ->throw();
        } catch (ConnectionException $e) {
            throw new ClaudeException('Claude API connection failed: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new ClaudeException('Claude API request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();

        return [
            'prices' => $this->parsePriceEntries($body),
            'estimated_cost_usd' => $this->estimateCost($body),
        ];
    }

    /**
     * @return array<int, array{nombre: string, precio_min: float, precio_max: float}>
     */
    private function parsePriceEntries(array $body): array
    {
        $content = $body['content'][0]['text'] ?? null;
        if (! is_string($content)) {
            throw new ClaudeException('Claude response missing text content.');
        }

        $json = $this->extractJsonArray($content);
        if (! is_array($json)) {
            throw new ClaudeException('Claude response is not valid JSON array.');
        }

        $entries = [];
        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['nombre'], $row['precio_min'], $row['precio_max'])) {
                continue;
            }
            $entries[] = [
                'nombre' => (string) $row['nombre'],
                'precio_min' => (float) $row['precio_min'],
                'precio_max' => (float) $row['precio_max'],
            ];
        }

        return $entries;
    }

    #[\Override]
    public function generateListFromContext(array $context): array
    {
        $apiKey = config('ai.api_key');
        if (! $apiKey) {
            throw new ClaudeException('Claude API key not configured.');
        }

        $payload = [
            'model' => config('ai.generation.model', config('ai.model')),
            'max_tokens' => (int) config('ai.generation.max_tokens', 3000),
            'system' => AiPrompt::getContent('list-generation', self::LIST_GENERATION_SYSTEM_PROMPT),
            'messages' => [[
                'role' => 'user',
                'content' => "Descripcion: {$context['description']}\nNumero de personas: {$context['people']}",
            ]],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout((int) config('ai.timeout_seconds', 30))
                ->post(rtrim((string) config('ai.api_base_url'), '/').'/messages', $payload)
                ->throw();
        } catch (ConnectionException $e) {
            throw new ClaudeException('Claude API connection failed: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new ClaudeException('Claude API request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();

        return [
            'products' => $this->parseListGenerationEntries($body),
            'estimated_cost_usd' => $this->estimateCost($body),
        ];
    }

    /**
     * @return array<int, array{nombre: string, cantidad_tipica: ?float, unidad_tipica: ?string, categoria: ?string, reason: ?string}>
     */
    private function parseListGenerationEntries(array $body): array
    {
        $content = $body['content'][0]['text'] ?? null;
        if (! is_string($content)) {
            throw new ClaudeException('Claude response missing text content.');
        }

        $json = $this->extractJsonArray($content);

        if (! is_array($json)) {
            throw new ClaudeException('Claude response is not valid JSON array.');
        }

        $maxItems = (int) config('ai.generation.max_items', 25);
        $entries = [];
        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['nombre'])) {
                continue;
            }

            $entries[] = [
                'nombre' => trim((string) $row['nombre']),
                'cantidad_tipica' => isset($row['cantidad_tipica']) ? (float) $row['cantidad_tipica'] : null,
                'unidad_tipica' => isset($row['unidad_tipica']) ? (string) $row['unidad_tipica'] : null,
                'categoria' => isset($row['categoria']) ? (string) $row['categoria'] : null,
                'reason' => isset($row['reason']) ? (string) $row['reason'] : null,
            ];

            if (count($entries) >= $maxItems) {
                break;
            }
        }

        return $entries;
    }

    #[\Override]
    public function generateWeeklySummary(array $context): array
    {
        $apiKey = config('ai.api_key');
        if (! $apiKey) {
            throw new ClaudeException('Claude API key not configured.');
        }

        $payload = [
            'model' => config('ai.weekly_summary.model', config('ai.model')),
            'max_tokens' => (int) config('ai.weekly_summary.max_tokens', 1500),
            'system' => AiPrompt::getContent('weekly-summary', self::WEEKLY_SUMMARY_SYSTEM_PROMPT),
            'messages' => [[
                'role' => 'user',
                'content' => $this->buildWeeklySummaryMessage($context),
            ]],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout((int) config('ai.timeout_seconds', 30))
                ->post(rtrim((string) config('ai.api_base_url'), '/').'/messages', $payload)
                ->throw();
        } catch (ConnectionException $e) {
            throw new ClaudeException('Claude API connection failed: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new ClaudeException('Claude API request failed: '.$e->getMessage(), 0, $e);
        }

        $body = $response->json();

        return [
            'products' => $this->parseWeeklySummaryEntries($body),
            'estimated_cost_usd' => $this->estimateCost($body),
        ];
    }

    /**
     * @param  array{history_weeks: array<int, array<int, string>>, active_list_items: array<int, string>, month: int}  $context
     */
    private function buildWeeklySummaryMessage(array $context): string
    {
        $weeks = [];
        foreach ($context['history_weeks'] as $idx => $products) {
            $weekNum = $idx + 1;
            $list = empty($products) ? '(sin compras)' : implode(', ', $products);
            $weeks[] = "Semana -{$weekNum}: {$list}";
        }
        $weeksBlock = empty($weeks) ? '(sin historial)' : implode("\n", $weeks);

        $active = empty($context['active_list_items'])
            ? '(sin lista activa)'
            : implode(', ', $context['active_list_items']);

        return "Historial ultimas 4 semanas:\n{$weeksBlock}\n\nLista activa actual: {$active}\n\nMes del ano: {$context['month']}";
    }

    /**
     * @return array<int, array{nombre: string, cantidad_tipica: ?float, unidad_tipica: ?string, categoria: ?string, reason: ?string}>
     */
    private function parseWeeklySummaryEntries(array $body): array
    {
        $content = $body['content'][0]['text'] ?? null;
        if (! is_string($content)) {
            throw new ClaudeException('Claude response missing text content.');
        }

        $json = $this->extractJsonArray($content);

        if (! is_array($json)) {
            throw new ClaudeException('Claude response is not valid JSON array.');
        }

        $entries = [];
        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['nombre'])) {
                continue;
            }

            $entries[] = [
                'nombre' => trim((string) $row['nombre']),
                'cantidad_tipica' => isset($row['cantidad_tipica']) ? (float) $row['cantidad_tipica'] : null,
                'unidad_tipica' => isset($row['unidad_tipica']) ? (string) $row['unidad_tipica'] : null,
                'categoria' => isset($row['categoria']) ? (string) $row['categoria'] : null,
                'reason' => isset($row['reason']) ? (string) $row['reason'] : null,
            ];

            if (count($entries) >= 8) {
                break;
            }
        }

        return $entries;
    }

    private function buildUserMessage(string $query, array $context): string
    {
        $contextBlock = empty($context)
            ? '(no prior product history available)'
            : implode(', ', array_slice($context, 0, (int) config('ai.prompt.max_history_items_in_context', 20)));

        return "Query: {$query}\nRecent product context: {$contextBlock}";
    }

    /**
     * @return Suggestion[]
     */
    private function parseSuggestions(array $body): array
    {
        $content = $body['content'][0]['text'] ?? null;
        if (! is_string($content)) {
            throw new ClaudeException('Claude response missing text content.');
        }

        $json = $this->extractJsonArray($content);

        if (! is_array($json)) {
            throw new ClaudeException('Claude response is not valid JSON array.');
        }

        $suggestions = [];
        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['name'])) {
                continue;
            }

            $suggestions[] = new Suggestion(
                source: 'ai',
                name: (string) $row['name'],
                quantity: isset($row['quantity']) ? (float) $row['quantity'] : null,
                unit: isset($row['unit']) ? (string) $row['unit'] : null,
                category: isset($row['category']) ? (string) $row['category'] : null,
            );

            if (count($suggestions) >= 5) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * @return array<int, array{nombre: string, categoria: ?string, unidad_tipica: ?string, cantidad_tipica: ?float}>
     */
    private function parseCatalogEntries(array $body): array
    {
        $content = $body['content'][0]['text'] ?? null;
        if (! is_string($content)) {
            throw new ClaudeException('Claude response missing text content.');
        }

        $json = $this->extractJsonArray($content);

        if (! is_array($json)) {
            throw new ClaudeException('Claude response is not valid JSON array.');
        }

        $entries = [];
        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['nombre'])) {
                continue;
            }

            $entries[] = [
                'nombre' => trim((string) $row['nombre']),
                'categoria' => isset($row['categoria']) ? (string) $row['categoria'] : null,
                'unidad_tipica' => isset($row['unidad_tipica']) ? (string) $row['unidad_tipica'] : null,
                'cantidad_tipica' => isset($row['cantidad_tipica']) ? (float) $row['cantidad_tipica'] : null,
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, array{nombre: string, unidad_tipica: ?string, categoria: ?string}>
     */
    private function parseComplementEntries(array $body): array
    {
        $content = $body['content'][0]['text'] ?? null;
        if (! is_string($content)) {
            throw new ClaudeException('Claude response missing text content.');
        }

        $json = $this->extractJsonArray($content);

        if (! is_array($json)) {
            throw new ClaudeException('Claude response is not valid JSON array.');
        }

        $entries = [];
        foreach ($json as $row) {
            if (! is_array($row) || ! isset($row['nombre'])) {
                continue;
            }

            $entries[] = [
                'nombre' => trim((string) $row['nombre']),
                'unidad_tipica' => isset($row['unidad_tipica']) ? (string) $row['unidad_tipica'] : null,
                'categoria' => isset($row['categoria']) ? (string) $row['categoria'] : null,
            ];

            if (count($entries) >= 2) {
                break;
            }
        }

        return $entries;
    }

    private function extractJsonArray(string $raw): mixed
    {
        $trimmed = trim($raw);

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\[.*\]/s', $trimmed, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        Log::warning('Claude returned non-JSON body', ['raw' => mb_substr($trimmed, 0, 500)]);

        return null;
    }

    private function estimateCost(array $body): float
    {
        $usage = $body['usage'] ?? null;
        if (! is_array($usage)) {
            return (float) config('ai.cost_estimation.fallback_per_call_usd', 0.01);
        }

        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);

        return round(
            ($input / 1000) * (float) config('ai.cost_estimation.input_per_1k_tokens_usd', 0.003)
            + ($output / 1000) * (float) config('ai.cost_estimation.output_per_1k_tokens_usd', 0.015),
            4,
        );
    }
}
