<?php

namespace App\Console\Commands;

use App\Services\AccountDeletionService;
use Illuminate\Console\Command;

class DeleteExpiredAccountsCommand extends Command
{
    protected $signature = 'accounts:delete-expired';

    protected $description = 'Hard-delete user accounts that have been soft-deleted for more than 30 days (RGPD compliance)';

    public function handle(AccountDeletionService $service): int
    {
        $count = $service->hardDeleteExpiredAccounts();

        $this->info("Hard-deleted {$count} expired account(s).");

        return self::SUCCESS;
    }
}
