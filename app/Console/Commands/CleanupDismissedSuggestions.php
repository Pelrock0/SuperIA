<?php

namespace App\Console\Commands;

use App\Models\AiDismissedSuggestion;
use Illuminate\Console\Command;

class CleanupDismissedSuggestions extends Command
{
    protected $signature = 'ai:cleanup-dismissed-suggestions';

    protected $description = 'Delete ai_dismissed_suggestions rows past their TTL. Prevents slow growth of the dismiss table over time.';

    public function handle(): int
    {
        $deleted = AiDismissedSuggestion::query()
            ->where('dismissed_until', '<', now())
            ->delete();

        $this->info("Deleted {$deleted} expired dismissed suggestion row(s).");

        return self::SUCCESS;
    }
}
