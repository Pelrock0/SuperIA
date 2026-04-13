<?php

namespace App\Support\Ai;

use App\Enums\AiUsageStatus;
use App\Mail\BudgetCapExceededAlert;
use App\Models\AiUsageLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class BudgetCap
{
    public function canSpend(): bool
    {
        $limit = (float) config('ai.budget_cap_monthly_usd', 0);

        if ($limit <= 0) {
            return true;
        }

        return $this->currentMonthSpendUsd() < $limit;
    }

    public function currentMonthSpendUsd(): float
    {
        $tz = config('ai.timezone', 'Europe/Madrid');
        $start = Carbon::now($tz)->startOfMonth();
        $end = Carbon::now($tz)->endOfMonth();

        return (float) AiUsageLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->where('status', AiUsageStatus::Success->value)
            ->sum('estimated_cost_usd');
    }

    public function notifyIfExceeded(): void
    {
        if ($this->canSpend()) {
            return;
        }

        $email = config('ai.admin_alert_email');
        if (! $email) {
            return;
        }

        $tz = config('ai.timezone', 'Europe/Madrid');
        $dedupKey = 'ai:budget_alert:'.Carbon::now($tz)->toDateString();

        Cache::remember($dedupKey, Carbon::now($tz)->endOfDay(), function () use ($email) {
            Mail::to($email)->queue(new BudgetCapExceededAlert($this->currentMonthSpendUsd()));
            return true;
        });
    }
}
