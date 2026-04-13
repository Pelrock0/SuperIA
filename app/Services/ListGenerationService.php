<?php

namespace App\Services;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Enums\ItemUnit;
use App\Enums\ProductCategory;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\Ai\AiUsageTracker;
use App\Support\Ai\BudgetCap;
use App\Support\Ai\CircuitBreaker;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\PromptSanitizer;

class ListGenerationService
{
    public function __construct(
        private PromptSanitizer $sanitizer,
        private BudgetCap $budgetCap,
        private AiUsageTracker $usageTracker,
        private CircuitBreaker $circuitBreaker,
        private ClaudeClientInterface $claude,
        private ShoppingListService $shoppingLists,
    ) {}

    /**
     * Generate a shopping list from a natural language description.
     * Checks shared quota + per-operation cap + budget + circuit breaker.
     * Retries once silently on invalid JSON.
     *
     * @return array{products: array<int, array<string, mixed>>, meta: array{people: int, description_used: string}}
     *
     * @throws \App\Support\Ai\Exceptions\ClaudeException on double failure
     * @throws \RuntimeException on quota/budget/circuit limits (caller translates to HTTP 429)
     */
    public function generate(User $user, string $description, int $people = 2): array
    {
        $this->checkQuotas($user);

        $maxChars = (int) config('ai.generation.max_prompt_chars', 500);
        $sanitized = $this->sanitizer->clean($description, $maxChars);

        $context = [
            'description' => $sanitized,
            'people' => $people,
        ];

        $result = $this->callWithRetry($context);

        $this->usageTracker->record(
            $user,
            AiOperation::Generation,
            AiUsageStatus::Success,
            (float) $result['estimated_cost_usd'],
        );

        return [
            'products' => $result['products'],
            'meta' => [
                'people' => $people,
                'description_used' => $sanitized,
            ],
        ];
    }

    /**
     * Create a new ShoppingList from confirmed preview items.
     * Propagates OverflowException when freemium limit is hit.
     */
    public function confirmAsNewList(User $user, array $items, string $name): ShoppingList
    {
        $list = $this->shoppingLists->create($user, [
            'name' => $name,
            'emoji' => '🤖',
            'category' => null,
        ]);

        $this->insertItems($list, $items);
        $this->syncCounters($list);

        return $list->refresh();
    }

    /**
     * Append confirmed preview items to an existing list.
     */
    public function confirmAddToExisting(User $user, ShoppingList $list, array $items): ShoppingList
    {
        if ($list->user_id !== $user->id) {
            abort(404);
        }

        $this->insertItems($list, $items);
        $this->syncCounters($list);

        return $list->refresh();
    }

    private function checkQuotas(User $user): void
    {
        if (! $this->budgetCap->canSpend()) {
            $this->usageTracker->record($user, AiOperation::Generation, AiUsageStatus::BudgetCapped);
            $this->budgetCap->notifyIfExceeded();
            throw new \RuntimeException('BUDGET_CAPPED');
        }

        if (! $this->usageTracker->canUse($user, AiOperation::Generation)) {
            $this->usageTracker->record($user, AiOperation::Generation, AiUsageStatus::UserCapped);
            throw new \RuntimeException('AI_LIMIT');
        }

        $defaultPerDay = (int) config('ai.generation.generation_per_day', 5);
        $perDayLimit = $user->ai_daily_limit_override !== null
            ? (int) $user->ai_daily_limit_override
            : (($user->plan === 'premium') ? $defaultPerDay * 10 : $defaultPerDay);
        if (! $this->usageTracker->canUseOperation($user, AiOperation::Generation, $perDayLimit)) {
            $this->usageTracker->record($user, AiOperation::Generation, AiUsageStatus::UserCapped);
            throw new \RuntimeException('GENERATION_LIMIT');
        }

        if (! $this->circuitBreaker->allow()) {
            $this->usageTracker->record($user, AiOperation::Generation, AiUsageStatus::CircuitOpen);
            throw new \RuntimeException('CIRCUIT_OPEN');
        }
    }

    /**
     * @return array{products: array<int, array<string, mixed>>, estimated_cost_usd: float}
     */
    private function callWithRetry(array $context): array
    {
        try {
            $result = $this->claude->generateListFromContext($context);
            $this->circuitBreaker->recordSuccess();
            return $result;
        } catch (ClaudeException $e) {
            // Silent retry once
            try {
                $result = $this->claude->generateListFromContext($context);
                $this->circuitBreaker->recordSuccess();
                return $result;
            } catch (ClaudeException $e2) {
                $this->circuitBreaker->recordFailure();
                throw $e2;
            }
        }
    }

    private function insertItems(ShoppingList $list, array $items): void
    {
        $position = $list->items()->count();
        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['nombre'])) {
                continue;
            }

            $unit = isset($item['unidad_tipica'])
                ? ItemUnit::tryFrom((string) $item['unidad_tipica'])
                : null;
            $category = isset($item['categoria'])
                ? ProductCategory::tryFrom((string) $item['categoria'])
                : null;

            $list->items()->create([
                'name' => (string) $item['nombre'],
                'quantity' => isset($item['cantidad_tipica']) ? (float) $item['cantidad_tipica'] : null,
                'unit' => $unit?->value,
                'category' => $category?->value,
                'is_purchased' => false,
                'position' => $position++,
            ]);
        }
    }

    private function syncCounters(ShoppingList $list): void
    {
        $total = $list->items()->count();
        $completed = $list->items()->where('is_purchased', true)->count();
        $list->update([
            'items_total' => $total,
            'items_completed' => $completed,
        ]);
    }
}
