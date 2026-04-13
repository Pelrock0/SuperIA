<?php

namespace App\Services;

use App\Mail\InvitationMail;
use App\Mail\WaitlistConfirmationMail;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;
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
                'position' => $this->approximatePosition($existing->position),
            ];
        }

        $entry = DB::transaction(function () use ($name, $email, $shoppingCompanion) {
            $position = WaitlistEntry::count() + 1;

            return WaitlistEntry::create([
                'name' => $name,
                'email' => $email,
                'shopping_companion' => $shoppingCompanion,
                'position' => $position,
                'status' => 'pending',
            ]);
        });

        Mail::to($entry->email)->queue(
            new WaitlistConfirmationMail($entry->name, $this->approximatePosition($entry->position))
        );

        return [
            'message' => 'Te has registrado en la lista de espera',
            'position' => $this->approximatePosition($entry->position),
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

    private function approximatePosition(int $realPosition): int
    {
        $offset = random_int(-5, 5);
        return max(1, $realPosition + $offset);
    }
}
