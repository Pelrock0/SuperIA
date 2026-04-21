<?php

namespace App\Services;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Models\ListItem;
use App\Models\ProductoCatalogo;
use App\Models\User;
use App\Support\Ai\AiUsageTracker;
use App\Support\Ai\BudgetCap;
use App\Support\Ai\CircuitBreaker;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Dto\Suggestion;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\HistoryAnonymizer;
use App\Support\Ai\PromptSanitizer;

class ProductSuggestionService
{
    private const LOCAL_LIMIT = 5;
    private const AI_FALLBACK_THRESHOLD = 3;

    public function __construct(
        private ProductHistoryWeightingService $history,
        private PromptSanitizer $sanitizer,
        private HistoryAnonymizer $anonymizer,
        private BudgetCap $budgetCap,
        private AiUsageTracker $usageTracker,
        private CircuitBreaker $circuitBreaker,
        private ClaudeClientInterface $claude,
    ) {}

    /**
     * @return array{suggestions: array<int, array<string, mixed>>, ai_fallback_used: bool, ai_limit_reached: bool}
     */
    public function suggest(User $user, string $query, bool $includeAi = false): array
    {
        $layer1 = $this->history->search($user, $query, self::LOCAL_LIMIT);
        $layerList = $this->searchListItems($user, $query, self::LOCAL_LIMIT);
        $layer2 = $this->searchCatalog($query, self::LOCAL_LIMIT);

        $merged = $this->dedup([...$layer1, ...$layerList, ...$layer2], self::LOCAL_LIMIT);

        $aiFallbackUsed = false;
        $aiLimitReached = false;

        $shouldTryAi = $includeAi && count($merged) < self::AI_FALLBACK_THRESHOLD;

        if ($shouldTryAi) {
            $aiResult = $this->tryAiFallback($user, $query);
            $aiLimitReached = $aiResult['limit_reached'];

            if (! empty($aiResult['suggestions'])) {
                $merged = $this->dedup([...$merged, ...$aiResult['suggestions']], self::LOCAL_LIMIT);
                $aiFallbackUsed = true;
            }
        }

        return [
            'suggestions' => array_map(fn (Suggestion $s) => $s->toArray(), $merged),
            'ai_fallback_used' => $aiFallbackUsed,
            'ai_limit_reached' => $aiLimitReached,
        ];
    }

    /**
     * @return Suggestion[]
     */
    private function searchListItems(User $user, string $query, int $limit): array
    {
        $trimmed = trim($query);

        if ($trimmed === '') {
            return [];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $trimmed);

        return ListItem::query()
            ->select('list_items.name')
            ->join('shopping_lists', 'shopping_lists.id', '=', 'list_items.shopping_list_id')
            ->where('shopping_lists.user_id', $user->id)
            ->where('list_items.name', 'LIKE', $escaped.'%')
            ->distinct()
            ->orderBy('list_items.name')
            ->limit($limit)
            ->get()
            ->toBase()
            ->map(fn (object $row) => new Suggestion(
                source: 'list',
                name: $row->name,
            ))
            ->all();
    }

    /**
     * @return Suggestion[]
     */
    private function searchCatalog(string $query, int $limit): array
    {
        $trimmed = trim($query);

        if ($trimmed === '') {
            return [];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $trimmed);

        return ProductoCatalogo::query()
            ->where('nombre', 'LIKE', $escaped.'%')
            ->orderBy('nombre')
            ->limit($limit)
            ->get()
            ->toBase()
            ->map(fn (ProductoCatalogo $row) => new Suggestion(
                source: 'catalog',
                name: $row->nombre,
                quantity: $row->cantidad_tipica !== null ? (float) $row->cantidad_tipica : null,
                unit: $row->unidad_tipica?->value,
                category: $row->categoria?->value,
            ))
            ->all();
    }

    /**
     * @return array{suggestions: Suggestion[], limit_reached: bool}
     */
    private function tryAiFallback(User $user, string $query): array
    {
        if (! $this->budgetCap->canSpend()) {
            $this->usageTracker->record($user, AiOperation::Suggestion, AiUsageStatus::BudgetCapped);
            $this->budgetCap->notifyIfExceeded();
            return ['suggestions' => [], 'limit_reached' => true];
        }

        if (! $this->usageTracker->canUse($user, AiOperation::Suggestion)) {
            $this->usageTracker->record($user, AiOperation::Suggestion, AiUsageStatus::UserCapped);
            return ['suggestions' => [], 'limit_reached' => true];
        }

        if (! $this->circuitBreaker->allow()) {
            $this->usageTracker->record($user, AiOperation::Suggestion, AiUsageStatus::CircuitOpen);
            return ['suggestions' => [], 'limit_reached' => true];
        }

        $cleanQuery = $this->sanitizer->clean($query);
        $context = $this->anonymizer->topProducts(
            $user,
            (int) config('ai.prompt.max_history_items_in_context', 20),
        );

        try {
            $result = $this->claude->suggest($cleanQuery, $context);
            $this->circuitBreaker->recordSuccess();
            $this->usageTracker->record(
                $user,
                AiOperation::Suggestion,
                AiUsageStatus::Success,
                (float) $result['estimated_cost_usd'],
            );

            return [
                'suggestions' => $result['suggestions'],
                'limit_reached' => false,
            ];
        } catch (ClaudeException $e) {
            $this->circuitBreaker->recordFailure();
            $this->usageTracker->record($user, AiOperation::Suggestion, AiUsageStatus::Error);
            return ['suggestions' => [], 'limit_reached' => false];
        }
    }

    /**
     * @param  Suggestion[]  $suggestions
     * @return Suggestion[]
     */
    private function dedup(array $suggestions, int $limit): array
    {
        $seen = [];
        $result = [];

        foreach ($suggestions as $s) {
            $key = mb_strtolower(trim($s->name));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $s;
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

}
