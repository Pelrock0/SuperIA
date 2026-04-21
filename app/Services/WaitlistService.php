<?php

namespace App\Services;

use App\Mail\AdminWaitlistNotificationMail;
use App\Mail\InvitationMail;
use App\Mail\WaitlistConfirmationMail;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WaitlistService
{
    public function register(string $name, string $email, ?string $shoppingCompanion): array
    {
        $existing = WaitlistEntry::where('email', $email)->first();

        if ($existing) {
            return [
                'message' => 'Te has registrado en la lista de espera',
                'position' => $this->pendingPosition($existing),
            ];
        }

        $entry = DB::transaction(function () use ($name, $email, $shoppingCompanion) {
            $position = WaitlistEntry::where('status', 'pending')->count() + 1;

            return WaitlistEntry::create([
                'name' => $name,
                'email' => $email,
                'shopping_companion' => $shoppingCompanion,
                'position' => $position,
                'status' => 'pending',
            ]);
        });

        $queuePosition = $this->pendingPosition($entry);

        Mail::to($entry->email)->queue(
            new WaitlistConfirmationMail($entry->name, $queuePosition)
        );

        $this->notifyAdmins($entry->name, $entry->email, $queuePosition);

        return [
            'message' => 'Te has registrado en la lista de espera',
            'position' => $queuePosition,
        ];
    }

    public function invite(WaitlistEntry $entry): void
    {
        if (!$entry->isPending()) {
            throw new \InvalidArgumentException('Solo se pueden invitar entradas pendientes.');
        }

        $token = hash_hmac('sha256', $entry->email . Str::random(32), config('app.key'));
        $expiresAt = now()->addDays(7);

        DB::transaction(function () use ($entry, $token, $expiresAt) {
            $entry->update([
                'status' => 'invited',
                'invitation_token' => $token,
                'invitation_sent_at' => now(),
                'invitation_expires_at' => $expiresAt,
            ]);
        });

        Mail::to($entry->email)->queue(
            new InvitationMail($entry->name, $token, $expiresAt)
        );
    }

    public function findByInvitationToken(string $token): ?WaitlistEntry
    {
        $entry = WaitlistEntry::where('invitation_token', $token)->first();

        if ($entry && !$entry->hasValidInvitation()) {
            return null;
        }

        return $entry;
    }

    private function notifyAdmins(string $name, string $email, int $position): void
    {
        $admins = User::role(['admin', 'superadmin'])->get();

        if ($admins->isEmpty()) {
            Log::warning('AdminWaitlistNotify: no admin users found to notify');

            return;
        }

        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(
                new AdminWaitlistNotificationMail($name, $email, $position)
            );
        }
    }

    private function pendingPosition(WaitlistEntry $entry): int
    {
        return WaitlistEntry::where('status', 'pending')
            ->where('id', '<=', $entry->id)
            ->count();
    }
}
