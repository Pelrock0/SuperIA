<?php

namespace App\Console\Commands;

use App\Models\AiUsageLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ResetAiDailyUsage extends Command
{
    protected $signature = 'ai:reset-daily-usage';

    protected $description = 'Daily marker command for AI usage counters. Also prunes usage rows older than 90 days.';

    public function handle(): int
    {
        $tz = config('ai.timezone', 'Europe/Madrid');
        $threshold = Carbon::now($tz)->subDays(90)->startOfDay();

        $pruned = AiUsageLog::query()
            ->where('created_at', '<', $threshold)
            ->delete();

        $this->info("AI usage reset boundary reached. Pruned {$pruned} row(s) older than 90 days.");

        return self::SUCCESS;
    }
}
