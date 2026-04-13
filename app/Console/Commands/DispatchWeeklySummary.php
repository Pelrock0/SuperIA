<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WeeklySummary;
use App\Services\WeeklySummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchWeeklySummary extends Command
{
    protected $signature = 'ai:dispatch-weekly-summary';

    protected $description = 'Generate and dispatch the weekly shopping summary for every eligible user. Safe to rerun — idempotent via unique constraint on (user_id, week_start_date).';

    public function handle(WeeklySummaryService $service): int
    {
        if (! config('ai.weekly_summary.enabled', true)) {
            $this->info('Weekly summary disabled by config. Exiting.');
            Log::info('weekly_summary.dispatch.disabled');
            return self::SUCCESS;
        }

        $users = $service->eligibleUsers();
        $processed = 0;
        $succeeded = 0;
        $emailSent = 0;
        $failed = 0;
        $totalCost = 0.0;

        foreach ($users as $user) {
            $processed++;
            try {
                $summary = $service->generateForUser($user);
                $totalCost += (float) ($summary->claude_cost_usd ?? 0);

                if ($summary->status->value === 'failed') {
                    $failed++;
                    continue;
                }

                $beforeDispatchedAt = $summary->dispatched_at;
                $service->dispatchEmailFor($summary);
                $summary->refresh();

                $succeeded++;
                if ($this->wasEmailSent($user, $beforeDispatchedAt, $summary)) {
                    $emailSent++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('weekly_summary.dispatch.user_failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $metrics = [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'email_sent' => $emailSent,
            'failed' => $failed,
            'total_cost_usd' => round($totalCost, 4),
        ];

        $this->info('weekly_summary.dispatch.done '.json_encode($metrics));
        Log::info('weekly_summary.dispatch.done', $metrics);

        return self::SUCCESS;
    }

    private function wasEmailSent(User $user, $beforeDispatchedAt, WeeklySummary $after): bool
    {
        if (! $user->weekly_summary_email_opted_in) {
            return false;
        }
        return $beforeDispatchedAt === null && $after->dispatched_at !== null;
    }
}
