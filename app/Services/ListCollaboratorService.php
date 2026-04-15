<?php

namespace App\Services;

use App\Models\ListCollaborator;
use App\Models\ListCollaboratorSession;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\ShareTokenContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListCollaboratorService
{
    public function linkUser(User $user, ShareTokenContext $context): ListCollaborator
    {
        return DB::transaction(function () use ($user, $context) {
            return ListCollaborator::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'shopping_list_id' => $context->list->id,
                ],
                [
                    'mode' => $context->mode->value,
                    'share_token_id' => $context->token->id,
                ],
            );
        });
    }

    public function isLinked(int $userId, int $listId): bool
    {
        return ListCollaborator::where('user_id', $userId)
            ->where('shopping_list_id', $listId)
            ->exists();
    }

    public function findForAccess(int $userId, int $listId): ?ListCollaborator
    {
        return ListCollaborator::where('user_id', $userId)
            ->where('shopping_list_id', $listId)
            ->first();
    }

    public function collaboratedListsForUser(User $user): Collection
    {
        return $user->collaboratedLists()
            ->with('user:id,name')
            ->get()
            ->map(function (ShoppingList $list) {
                $list->setAttribute('collaborator_mode', $list->pivot->mode);
                $list->setAttribute('owner_name', $list->user->name);
                return $list;
            });
    }

    public function collaboratorsForList(ShoppingList $list): Collection
    {
        return $list->collaborators()
            ->with('user:id,name,email')
            ->get()
            ->map(fn (ListCollaborator $c) => [
                'id' => $c->id,
                'user_id' => $c->user_id,
                'name' => $c->user->name,
                'email' => $c->user->email,
                'mode' => $c->mode->value,
                'linked_at' => $c->created_at->toIso8601String(),
            ]);
    }

    public function removeByToken(int $shareTokenId): int
    {
        return ListCollaborator::where('share_token_id', $shareTokenId)->delete();
    }

    public function linkRetroactive(User $user, array $sessionUuids): int
    {
        if (empty($sessionUuids)) {
            return 0;
        }

        $sessions = ListCollaboratorSession::whereIn('session_uuid', $sessionUuids)
            ->with('shareToken.shoppingList')
            ->get();

        $linked = 0;

        DB::transaction(function () use ($user, $sessions, &$linked) {
            foreach ($sessions as $session) {
                $token = $session->shareToken;
                if (! $token || $token->isRevoked()) {
                    continue;
                }

                $list = $token->shoppingList;
                if (! $list || $list->user_id === $user->id) {
                    continue;
                }

                $created = ListCollaborator::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'shopping_list_id' => $list->id,
                    ],
                    [
                        'mode' => $token->mode->value,
                        'share_token_id' => $token->id,
                    ],
                );

                if ($created->wasRecentlyCreated) {
                    $linked++;
                }
            }
        });

        return $linked;
    }
}
