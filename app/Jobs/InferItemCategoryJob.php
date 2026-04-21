<?php

namespace App\Jobs;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Enums\ProductCategory;
use App\Models\AiUsageLog;
use App\Models\ListItem;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InferItemCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private readonly int $listItemId,
    ) {}

    public function handle(ClaudeClientInterface $claude): void
    {
        $item = ListItem::find($this->listItemId);
        if (! $item || $item->category !== null) {
            return;
        }

        try {
            $result = $claude->inferCategory($item->name);
        } catch (ClaudeException $e) {
            Log::warning('AI category inference failed', [
                'item_id' => $this->listItemId,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $categoryValue = $result['category'] ?? null;
        if ($categoryValue === null) {
            return;
        }

        $category = ProductCategory::tryFrom($categoryValue);
        if ($category === null) {
            return;
        }

        // Only update if still uncategorized (avoid race condition)
        ListItem::where('id', $this->listItemId)
            ->whereNull('category')
            ->update(['category' => $category->value]);

        $userId = $item->shoppingList?->user_id;

        AiUsageLog::create([
            'user_id' => $userId,
            'operation' => AiOperation::CategoryInference,
            'status' => AiUsageStatus::Success,
            'date' => now()->toDateString(),
            'estimated_cost_usd' => $result['estimated_cost_usd'],
            'input_tokens' => $result['input_tokens'] ?? null,
            'output_tokens' => $result['output_tokens'] ?? null,
            'created_at' => now(),
        ]);
    }
}
