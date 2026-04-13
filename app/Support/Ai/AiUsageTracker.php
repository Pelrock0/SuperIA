<?php

namespace App\Support\Ai;

use App\Enums\AiOperation;
use App\Enums\AiPlan;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class AiUsageTracker
{
    /**
     * Shared daily quota check across ALL AI operations.
     * A user's total of (suggestion + replenishment + complement + ...) must not exceed the plan's limit.
     */
    public function canUse(User $user, AiOperation $operation): bool
    {
        $plan = $this->planFor($user);
        $defaultQuota = $plan->dailySuggestionQuota();
        $quota = $user->ai_daily_limit_override ?? $defaultQuota;

        if ($quota === null) {
            return true;
        }

        return $this->usedTodayAcrossAllOperations($user) < $quota;
    }

    /**
     * Per-operation daily cap check. Used for operations with their own
     * rate limit independent of the shared pool (e.g. generation = 5/day).
     */
    public function canUseOperation(User $user, AiOperation $operation, int $limit): bool
    {
        return $this->usedTodayForOperation($user, $operation) < $limit;
    }

    /**
     * Per-operation counter retained for admin analytics / breakdown reports.
     */
    public function usedTodayForOperation(User $user, AiOperation $operation): int
    {
        return AiUsageLog::query()
            ->where('user_id', $user->id)
            ->where('operation', $operation->value)
            ->where('status', AiUsageStatus::Success->value)
            ->whereDate('date', $this->madridToday())
            ->count();
    }

    public function usedTodayAcrossAllOperations(User $user): int
    {
        return AiUsageLog::query()
            ->where('user_id', $user->id)
            ->where('status', AiUsageStatus::Success->value)
            ->whereDate('date', $this->madridToday())
            ->count();
    }

    public function record(
        ?User $user,
        AiOperation $operation,
        AiUsageStatus $status,
        float $costUsd = 0,
    ): AiUsageLog {
        return AiUsageLog::create([
            'user_id' => $user?->id,
            'operation' => $operation->value,
            'status' => $status->value,
            'date' => $this->madridToday(),
            'estimated_cost_usd' => $costUsd,
            'created_at' => now(),
        ]);
    }

    private function planFor(User $user): AiPlan
    {
        $raw = $user->plan ?? AiPlan::Free->value;

        return AiPlan::tryFrom($raw) ?? AiPlan::Free;
    }

    private function madridToday(): string
    {
        return Carbon::now(config('ai.timezone', 'Europe/Madrid'))->toDateString();
    }
}
