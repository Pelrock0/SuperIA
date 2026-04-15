<?php

namespace App\Services;

use App\Enums\ListStatus;
use App\Models\ShoppingList;
use App\Models\User;
use App\Services\ListCollaboratorService;
use Illuminate\Support\Facades\DB;

class ShoppingListService
{
    private const FREEMIUM_LIMIT = 3;

    public function __construct(
        private ?ListCollaboratorService $collaboratorService = null,
    ) {
        $this->collaboratorService ??= new ListCollaboratorService();
    }

    public function getListsForUser(User $user): array
    {
        $lists = $user->shoppingLists()
            ->orderByRaw("FIELD(status, 'active', 'archived')")
            ->orderBy('updated_at', 'desc')
            ->get();

        $collaborated = $this->collaboratorService->collaboratedListsForUser($user);

        return [
            'active' => $lists->where('status', ListStatus::Active)->values(),
            'archived' => $lists->where('status', ListStatus::Archived)->values(),
            'collaborated' => $collaborated->values(),
        ];
    }

    public function create(User $user, array $data): ShoppingList
    {
        return DB::transaction(function () use ($user, $data) {
            $activeCount = $user->shoppingLists()
                ->where('status', ListStatus::Active)
                ->lockForUpdate()
                ->count();

            if ($activeCount >= self::FREEMIUM_LIMIT) {
                throw new \OverflowException(
                    'Has alcanzado el limite de 3 listas activas. Archiva o elimina una lista para crear otra nueva.'
                );
            }

            return $user->shoppingLists()->create([
                'name' => $data['name'],
                'emoji' => $data['emoji'] ?? null,
                'category' => $data['category'] ?? null,
                'status' => ListStatus::Active,
            ]);
        });
    }

    public function update(ShoppingList $list, array $data): ShoppingList
    {
        $list->update($data);
        return $list->refresh();
    }

    public function archive(ShoppingList $list): ShoppingList
    {
        $list->update(['status' => ListStatus::Archived]);
        return $list->refresh();
    }

    public function restore(ShoppingList $list): ShoppingList
    {
        return DB::transaction(function () use ($list) {
            $activeCount = $list->user->shoppingLists()
                ->where('status', ListStatus::Active)
                ->lockForUpdate()
                ->count();

            if ($activeCount >= self::FREEMIUM_LIMIT) {
                throw new \OverflowException(
                    'Has alcanzado el limite de 3 listas activas. Archiva o elimina una lista antes de restaurar.'
                );
            }

            $list->update(['status' => ListStatus::Active]);
            return $list->refresh();
        });
    }

    public function delete(ShoppingList $list): void
    {
        $list->delete();
    }
}
