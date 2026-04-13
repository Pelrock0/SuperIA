<?php

namespace App\Services;

use App\Mail\AccountDeletionMail;
use App\Models\AccountDeletionLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AccountDeletionService
{
    public function initiateDelete(User $user, string $password): void
    {
        if (!Hash::check($password, $user->password)) {
            throw new \InvalidArgumentException('Contrasena incorrecta.');
        }

        $email = $user->email;
        $name = $user->name;

        DB::transaction(function () use ($user) {
            AccountDeletionLog::logDeletion($user->id, 'user_request');

            $user->shoppingLists()->delete();

            $user->incrementJwtVersion();

            $user->update([
                'scheduled_hard_delete_at' => now()->addDays(30),
            ]);

            $user->delete();
        });

        Mail::to($email)->queue(new AccountDeletionMail($name));
    }

    public function hardDeleteExpiredAccounts(): int
    {
        $users = User::onlyTrashed()
            ->whereNotNull('scheduled_hard_delete_at')
            ->where('scheduled_hard_delete_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($users as $user) {
            $user->forceDelete();
            $count++;
        }

        return $count;
    }
}
