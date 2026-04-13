<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Models\ListActivityLog;
use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Collection;

class ActivityLogService
{
    public const MAX_ENTRIES_PER_LIST = 50;

    public function record(
        ShoppingList $list,
        ActorType $actorType,
        ActivityAction $action,
        string $itemName,
        ?int $shareTokenId = null,
    ): ListActivityLog {
        $log = ListActivityLog::create([
            'shopping_list_id' => $list->id,
            'list_share_token_id' => $shareTokenId,
            'actor_type' => $actorType,
            'action' => $action,
            'item_name' => mb_substr($itemName, 0, 80),
            'created_at' => now(),
        ]);

        $this->enforceRollingLimit($list->id);

        return $log;
    }

    public function getRecent(ShoppingList $list, int $limit = self::MAX_ENTRIES_PER_LIST): Collection
    {
        return ListActivityLog::where('shopping_list_id', $list->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function enforceRollingLimit(int $listId): void
    {
        $thresholdId = ListActivityLog::where('shopping_list_id', $listId)
            ->orderByDesc('id')
            ->skip(self::MAX_ENTRIES_PER_LIST)
            ->value('id');

        if ($thresholdId !== null) {
            ListActivityLog::where('shopping_list_id', $listId)
                ->where('id', '<=', $thresholdId)
                ->delete();
        }
    }
}
