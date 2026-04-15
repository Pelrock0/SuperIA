<?php

namespace App\Console\Commands;

use App\Jobs\InferItemCategoryJob;
use App\Models\ListItem;
use Illuminate\Console\Command;

class BackfillItemCategories extends Command
{
    protected $signature = 'items:backfill-categories {--limit=500 : Max items to process}';

    protected $description = 'Dispatch AI category inference jobs for items that have no category.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $items = ListItem::whereNull('category')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->pluck('id');

        if ($items->isEmpty()) {
            $this->info('No uncategorized items found.');
            return self::SUCCESS;
        }

        foreach ($items as $id) {
            InferItemCategoryJob::dispatch($id);
        }

        $this->info("Dispatched {$items->count()} category inference jobs.");

        return self::SUCCESS;
    }
}
