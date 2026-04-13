<?php

namespace App\Services;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\Ai\AiUsageTracker;
use App\Support\Ai\BudgetCap;
use App\Support\Ai\CircuitBreaker;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\PromptSanitizer;
use Illuminate\Support\Facades\DB;

class ComplementarySuggestionService
{
    public const MAX_SUGGESTIONS = 2;

    /**
     * Hard cap on rows fetched from the co-occurrence query before PHP-side ratio filtering.
     * Defense against slow-query DoS via users with very large histories. The top 50 most
     * co-occurring products is generous enough to find the 2 highest-ratio matches in any
     * realistic shopping pattern.
     */
    private const CO_OCCURRENCE_FETCH_LIMIT = 50;

    public function __construct(
        private ProductHistoryStatsService $stats,
        private PromptSanitizer $sanitizer,
        private BudgetCap $budgetCap,
        private AiUsageTracker $usageTracker,
        private CircuitBreaker $circuitBreaker,
        private ClaudeClientInterface $claude,
    ) {}

    /**
     * @return array{suggestions: array<int, array<string, mixed>>, ai_fallback_used: bool, ai_limit_reached: bool}
     */
    public function suggest(User $user, string $productName, int $listId): array
    {
        $currentListItems = $this->currentListProductNames($listId);
        $currentListLower = array_fill_keys(array_map('mb_strtolower', $currentListItems), true);

        $completedCount = $this->stats->completedListsCount($user);
        $minCompleted = (int) config('ai.thresholds.min_completed_lists', 5);

        if ($completedCount < $minCompleted) {
            return $this->tryAiFallback($user, $productName, $currentListLower);
        }

        $local = $this->localCooccurrence($user, $productName, $currentListLower);

        return [
            'suggestions' => $local,
            'ai_fallback_used' => false,
            'ai_limit_reached' => false,
        ];
    }

    /**
     * @param  array<string, bool>  $currentListLower
     * @return array<int, array<string, mixed>>
     */
    private function localCooccurrence(User $user, string $productName, array $currentListLower): array
    {
        $completedListIds = $this->stats->completedListIds($user);

        if (empty($completedListIds)) {
            return [];
        }

        $listsWithX = DB::table('producto_historial')
            ->where('user_id', $user->id)
            ->whereIn('lista_id', $completedListIds)
            ->whereRaw('LOWER(producto_nombre) = LOWER(?)', [$productName])
            ->distinct()
            ->pluck('lista_id')
            ->all();

        $listsWithXCount = count($listsWithX);
        if ($listsWithXCount === 0) {
            return [];
        }

        $rows = DB::table('producto_historial')
            ->selectRaw('
                producto_nombre,
                MAX(categoria) AS categoria,
                MAX(unidad) AS unidad,
                MAX(cantidad) AS cantidad,
                COUNT(DISTINCT lista_id) AS co_count
            ')
            ->where('user_id', $user->id)
            ->whereIn('lista_id', $listsWithX)
            ->whereRaw('LOWER(producto_nombre) <> LOWER(?)', [$productName])
            ->groupBy('producto_nombre')
            ->orderByDesc('co_count')
            ->limit(self::CO_OCCURRENCE_FETCH_LIMIT)
            ->get();

        $threshold = (float) config('ai.thresholds.co_occurrence_ratio', 0.60);

        $candidates = [];
        foreach ($rows as $row) {
            $ratio = (int) $row->co_count / $listsWithXCount;
            if ($ratio < $threshold) {
                continue;
            }

            $key = mb_strtolower($row->producto_nombre);
            if (isset($currentListLower[$key])) {
                continue;
            }

            $candidates[] = [
                'nombre' => $row->producto_nombre,
                'unidad_tipica' => $row->unidad,
                'categoria' => $row->categoria,
                'cantidad_tipica' => $row->cantidad !== null ? (float) $row->cantidad : null,
                'co_ratio' => round($ratio, 2),
                'source' => 'history',
            ];
        }

        usort($candidates, fn ($a, $b) => $b['co_ratio'] <=> $a['co_ratio']);

        return array_slice($candidates, 0, self::MAX_SUGGESTIONS);
    }

    /**
     * @param  array<string, bool>  $currentListLower
     * @return array{suggestions: array<int, array<string, mixed>>, ai_fallback_used: bool, ai_limit_reached: bool}
     */
    private function tryAiFallback(User $user, string $productName, array $currentListLower): array
    {
        if (! $this->budgetCap->canSpend()) {
            $this->usageTracker->record($user, AiOperation::Complement, AiUsageStatus::BudgetCapped);
            $this->budgetCap->notifyIfExceeded();
            return ['suggestions' => [], 'ai_fallback_used' => false, 'ai_limit_reached' => true];
        }

        if (! $this->usageTracker->canUse($user, AiOperation::Complement)) {
            $this->usageTracker->record($user, AiOperation::Complement, AiUsageStatus::UserCapped);
            return ['suggestions' => [], 'ai_fallback_used' => false, 'ai_limit_reached' => true];
        }

        if (! $this->circuitBreaker->allow()) {
            $this->usageTracker->record($user, AiOperation::Complement, AiUsageStatus::CircuitOpen);
            return ['suggestions' => [], 'ai_fallback_used' => false, 'ai_limit_reached' => true];
        }

        $cleanName = $this->sanitizer->clean($productName);

        try {
            $result = $this->claude->suggestComplements($cleanName);
            $this->circuitBreaker->recordSuccess();
            $this->usageTracker->record(
                $user,
                AiOperation::Complement,
                AiUsageStatus::Success,
                (float) $result['estimated_cost_usd'],
            );
        } catch (ClaudeException $e) {
            $this->circuitBreaker->recordFailure();
            $this->usageTracker->record($user, AiOperation::Complement, AiUsageStatus::Error);
            return ['suggestions' => [], 'ai_fallback_used' => false, 'ai_limit_reached' => false];
        }

        $suggestions = [];
        foreach ($result['products'] as $entry) {
            if (! isset($entry['nombre'])) {
                continue;
            }
            $key = mb_strtolower((string) $entry['nombre']);
            if (isset($currentListLower[$key])) {
                continue;
            }
            $suggestions[] = [
                'nombre' => (string) $entry['nombre'],
                'unidad_tipica' => $entry['unidad_tipica'] ?? null,
                'categoria' => $entry['categoria'] ?? null,
                'cantidad_tipica' => null,
                'co_ratio' => null,
                'source' => 'ai',
            ];
            if (count($suggestions) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return [
            'suggestions' => $suggestions,
            'ai_fallback_used' => ! empty($suggestions),
            'ai_limit_reached' => false,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function currentListProductNames(int $listId): array
    {
        return DB::table('list_items')
            ->where('shopping_list_id', $listId)
            ->pluck('name')
            ->all();
    }
}
