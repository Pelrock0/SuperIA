<?php

namespace App\Services;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Enums\ItemUnit;
use App\Enums\ListStatus;
use App\Enums\ProductCategory;
use App\Enums\WeeklySummaryStatus;
use App\Mail\WeeklySummaryMail;
use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Models\WeeklySummary;
use App\Support\Ai\BudgetCap;
use App\Support\Ai\CircuitBreaker;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\PromptSanitizer;
use App\Support\Ai\AiUsageTracker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class WeeklySummaryService
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
     * Stream of users eligible for a weekly summary this week.
     *
     * Criteria:
     * - Not soft-deleted
     * - Email verified
     * - Has at least one `producto_historial` row within the inactivity cutoff window (activity proxy; see 01-scope § Resolved Decisions #4)
     * - Has at least `min_history_weeks` distinct ISO weeks with purchase history
     *
     * @return Collection<int, User>
     */
    public function eligibleUsers(): Collection
    {
        $cutoffDays = (int) config('ai.weekly_summary.inactivity_cutoff_days', 60);
        $minWeeks = (int) config('ai.weekly_summary.min_history_weeks', 3);
        $cutoff = Carbon::now($this->timezone())->subDays($cutoffDays);

        $activeUserIds = ProductoHistorial::query()
            ->selectRaw('user_id')
            ->where('fecha_compra', '>=', $cutoff)
            ->groupBy('user_id')
            ->pluck('user_id')
            ->all();

        if (empty($activeUserIds)) {
            return collect();
        }

        $withEnoughHistoryIds = ProductoHistorial::query()
            ->selectRaw('user_id, COUNT(DISTINCT YEARWEEK(fecha_compra, 1)) as week_count')
            ->whereIn('user_id', $activeUserIds)
            ->groupBy('user_id')
            ->havingRaw('week_count >= ?', [$minWeeks])
            ->pluck('user_id')
            ->all();

        if (empty($withEnoughHistoryIds)) {
            return collect();
        }

        return User::query()
            ->whereNull('deleted_at')
            ->whereNotNull('email_verified_at')
            ->whereIn('id', $withEnoughHistoryIds)
            ->get();
    }

    /**
     * Generate (or fetch the existing) WeeklySummary row for a user for the current ISO week.
     * Idempotent: the unique constraint (user_id, week_start_date) is the source of truth.
     */
    public function generateForUser(User $user): WeeklySummary
    {
        $weekStart = $this->currentWeekStart();

        $existing = WeeklySummary::query()
            ->where('user_id', $user->id)
            ->whereDate('week_start_date', $weekStart)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $this->budgetCap->canSpend()) {
            $this->usageTracker->record($user, AiOperation::Summary, AiUsageStatus::BudgetCapped);
            $this->budgetCap->notifyIfExceeded();
            return $this->persistFailed($user, $weekStart, 'global budget exceeded');
        }

        if (! $this->usageTracker->canUse($user, AiOperation::Summary)) {
            $this->usageTracker->record($user, AiOperation::Summary, AiUsageStatus::UserCapped);
            return $this->persistFailed($user, $weekStart, 'user quota exceeded');
        }

        if (! $this->circuitBreaker->allow()) {
            $this->usageTracker->record($user, AiOperation::Summary, AiUsageStatus::CircuitOpen);
            return $this->persistFailed($user, $weekStart, 'circuit breaker open');
        }

        $context = $this->buildContext($user);

        try {
            $row = DB::transaction(function () use ($user, $weekStart) {
                return WeeklySummary::create([
                    'user_id' => $user->id,
                    'week_start_date' => $weekStart,
                    'status' => WeeklySummaryStatus::Pending,
                ]);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                $row = WeeklySummary::query()
                    ->where('user_id', $user->id)
                    ->whereDate('week_start_date', $weekStart)
                    ->first();

                if ($row !== null) {
                    return $row;
                }
            }
            throw $e;
        }

        try {
            $result = $this->claude->generateWeeklySummary($context);
            $this->circuitBreaker->recordSuccess();
        } catch (ClaudeException $e) {
            $this->circuitBreaker->recordFailure();
            $this->usageTracker->record($user, AiOperation::Summary, AiUsageStatus::Error);

            $row->update([
                'status' => WeeklySummaryStatus::Failed,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);

            return $row->refresh();
        }

        $row->update([
            'payload_json' => $result['products'],
            'claude_cost_usd' => (float) $result['estimated_cost_usd'],
            'status' => WeeklySummaryStatus::Pending,
        ]);

        $this->usageTracker->record(
            $user,
            AiOperation::Summary,
            AiUsageStatus::Success,
            (float) $result['estimated_cost_usd'],
        );

        return $row->refresh();
    }

    /**
     * Send the email for a generated summary IF the user has opted in.
     * Always re-reads the opt-in flag to minimize the race window between eligibility snapshot and dispatch.
     */
    public function dispatchEmailFor(WeeklySummary $summary): void
    {
        if ($summary->status === WeeklySummaryStatus::Failed) {
            return;
        }

        $user = $summary->user()->first();
        if ($user === null) {
            return;
        }

        $freshOptedIn = (bool) User::query()
            ->where('id', $user->id)
            ->value('weekly_summary_email_opted_in');

        if ($freshOptedIn) {
            Mail::to($user->email)->send(new WeeklySummaryMail($user, $summary));
        }

        $summary->update([
            'status' => WeeklySummaryStatus::Dispatched,
            'dispatched_at' => now(),
        ]);
    }

    public function markDismissed(User $user): void
    {
        $user->forceFill([
            'weekly_summary_in_app_dismissed_at' => now(),
        ])->save();
    }

    /**
     * Convert a weekly summary to a new shopping list.
     * Propagates OverflowException from ShoppingListService when freemium limit is hit.
     */
    public function convertToList(User $user, WeeklySummary $summary): ShoppingList
    {
        if ($summary->user_id !== $user->id) {
            abort(404);
        }

        $payload = $summary->payload_json ?? [];

        $name = 'Resumen semanal del '.Carbon::parse($summary->week_start_date)->format('d/m/Y');

        $list = $this->shoppingLists->create($user, [
            'name' => $name,
            'emoji' => '📅',
            'category' => null,
        ]);

        $position = 0;
        foreach ($payload as $product) {
            if (! is_array($product) || ! isset($product['nombre'])) {
                continue;
            }

            $unit = isset($product['unidad_tipica'])
                ? ItemUnit::tryFrom((string) $product['unidad_tipica'])
                : null;
            $category = isset($product['categoria'])
                ? ProductCategory::tryFrom((string) $product['categoria'])
                : null;

            $list->items()->create([
                'name' => (string) $product['nombre'],
                'quantity' => isset($product['cantidad_tipica']) ? (float) $product['cantidad_tipica'] : null,
                'unit' => $unit?->value,
                'category' => $category?->value,
                'is_purchased' => false,
                'position' => $position++,
            ]);
        }

        $list->update([
            'items_total' => $list->items()->count(),
            'items_completed' => $list->items()->where('is_purchased', true)->count(),
        ]);

        return $list->refresh();
    }

    public function currentWeekStart(): string
    {
        return Carbon::now($this->timezone())
            ->startOfWeek(Carbon::MONDAY)
            ->toDateString();
    }

    /**
     * @return array{history_weeks: array<int, array<int, string>>, active_list_items: array<int, string>, month: int}
     */
    private function buildContext(User $user): array
    {
        $weeksConfig = (int) config('ai.weekly_summary.history_weeks', 4);
        $history = $this->historyByWeek($user, $weeksConfig);
        $activeList = $this->activeListItems($user);
        $month = Carbon::now($this->timezone())->month;

        $cleanHistory = [];
        foreach ($history as $weekProducts) {
            $cleanHistory[] = array_map(fn (string $name) => $this->sanitizer->clean($name), $weekProducts);
        }
        $cleanActive = array_map(fn (string $name) => $this->sanitizer->clean($name), $activeList);

        return [
            'history_weeks' => $cleanHistory,
            'active_list_items' => $cleanActive,
            'month' => $month,
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function historyByWeek(User $user, int $weeks): array
    {
        $tz = $this->timezone();
        $startOfThisWeek = Carbon::now($tz)->startOfWeek(Carbon::MONDAY);

        $result = [];
        for ($i = 1; $i <= $weeks; $i++) {
            $weekStart = (clone $startOfThisWeek)->subWeeks($i);
            $weekEnd = (clone $weekStart)->addWeek();

            $products = ProductoHistorial::query()
                ->where('user_id', $user->id)
                ->where('fecha_compra', '>=', $weekStart)
                ->where('fecha_compra', '<', $weekEnd)
                ->pluck('producto_nombre')
                ->unique()
                ->values()
                ->all();

            $result[] = array_map(fn ($name) => (string) $name, $products);
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function activeListItems(User $user): array
    {
        $activeList = $user->shoppingLists()
            ->where('status', ListStatus::Active)
            ->orderByDesc('updated_at')
            ->first();

        if ($activeList === null) {
            return [];
        }

        return $activeList->items()
            ->pluck('name')
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    private function persistFailed(User $user, string $weekStart, string $reason): WeeklySummary
    {
        try {
            return WeeklySummary::create([
                'user_id' => $user->id,
                'week_start_date' => $weekStart,
                'status' => WeeklySummaryStatus::Failed,
                'error_message' => $reason,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                $existing = WeeklySummary::query()
                    ->where('user_id', $user->id)
                    ->whereDate('week_start_date', $weekStart)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    private function timezone(): string
    {
        return (string) config('ai.timezone', 'Europe/Madrid');
    }
}
