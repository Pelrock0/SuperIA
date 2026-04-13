<?php

namespace App\Console\Commands;

use App\Enums\ActorType;
use App\Models\ListActivityLog;
use App\Models\ListCollaboratorSession;
use App\Models\ListShareToken;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CleanupExpiredCollaboratorData extends Command
{
    protected $signature = 'app:cleanup-collaborator-data';

    protected $description = 'Delete stale collaborator sessions, expire anonymous activity older than 30 days, and purge anonymous data tied to revoked tokens (RGPD compliance).';

    public function handle(): int
    {
        $staleSessions = $this->deleteStaleSessions();
        $expiredLogs = $this->deleteExpiredAnonymousLogs();
        $revokedLogs = $this->purgeRevokedTokenLogs();

        $this->info("Deleted {$staleSessions} stale session(s).");
        $this->info("Deleted {$expiredLogs} expired anonymous log entr(ies).");
        $this->info("Purged {$revokedLogs} log entr(ies) tied to revoked tokens.");

        return self::SUCCESS;
    }

    private function deleteStaleSessions(): int
    {
        $threshold = Carbon::now()->subMinutes(5);

        return ListCollaboratorSession::where('last_heartbeat_at', '<', $threshold)->delete();
    }

    private function deleteExpiredAnonymousLogs(): int
    {
        $threshold = Carbon::now()->subDays(30);

        return ListActivityLog::where('actor_type', ActorType::Anonymous->value)
            ->where('created_at', '<', $threshold)
            ->delete();
    }

    private function purgeRevokedTokenLogs(): int
    {
        $threshold = Carbon::now()->subHours(24);

        $tokenIds = ListShareToken::whereNotNull('revoked_at')
            ->where('revoked_at', '<', $threshold)
            ->pluck('id');

        if ($tokenIds->isEmpty()) {
            return 0;
        }

        return ListActivityLog::whereIn('list_share_token_id', $tokenIds)->delete();
    }
}
