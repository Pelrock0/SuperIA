<?php

namespace App\Services;

use App\Models\ListCollaboratorSession;
use App\Models\ShoppingList;
use App\Support\ShareTokenContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CollaboratorPresenceService
{
    public const ACTIVE_WINDOW_SECONDS = 30;
    public const STALE_CLEANUP_MINUTES = 5;
    public const COUNT_CACHE_TTL_SECONDS = 5;

    public function heartbeat(ShareTokenContext $context, string $sessionUuid): ListCollaboratorSession
    {
        $session = ListCollaboratorSession::updateOrCreate(
            [
                'list_share_token_id' => $context->tokenId(),
                'session_uuid' => $sessionUuid,
            ],
            [
                'last_heartbeat_at' => now(),
                'created_at' => now(),
            ],
        );

        Cache::forget($this->cacheKey($context->list->id));

        return $session;
    }

    public function countActive(ShoppingList $list): int
    {
        return Cache::remember(
            $this->cacheKey($list->id),
            self::COUNT_CACHE_TTL_SECONDS,
            fn () => $this->queryActiveCount($list),
        );
    }

    public function deleteStale(): int
    {
        $threshold = Carbon::now()->subMinutes(self::STALE_CLEANUP_MINUTES);

        return ListCollaboratorSession::where('last_heartbeat_at', '<', $threshold)->delete();
    }

    private function queryActiveCount(ShoppingList $list): int
    {
        $threshold = Carbon::now()->subSeconds(self::ACTIVE_WINDOW_SECONDS);

        return DB::table('list_collaborator_sessions as s')
            ->join('list_share_tokens as t', 's.list_share_token_id', '=', 't.id')
            ->where('t.shopping_list_id', $list->id)
            ->whereNull('t.revoked_at')
            ->where('s.last_heartbeat_at', '>=', $threshold)
            ->count('s.id');
    }

    private function cacheKey(int $listId): string
    {
        return 'list_collaborators_count:'.$listId;
    }
}
