<?php

namespace App\Services;

use App\Mail\VerificationMail;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RegistrationService
{
    public function __construct(
        private WaitlistService $waitlistService,
        private ?ListCollaboratorService $collaboratorService = null,
    ) {
        $this->collaboratorService ??= new ListCollaboratorService();
    }

    public function validateInvitationToken(string $token): ?WaitlistEntry
    {
        return $this->waitlistService->findByInvitationToken($token);
    }

    public function register(string $token, string $name, string $password, array $sessionUuids = []): User
    {
        $entry = $this->waitlistService->findByInvitationToken($token);

        if (!$entry) {
            throw new \InvalidArgumentException('Token de invitacion invalido o expirado.');
        }

        $existingUser = User::where('email', $entry->email)->first();
        if ($existingUser) {
            throw new \InvalidArgumentException('Este email ya esta registrado.');
        }

        $user = DB::transaction(function () use ($entry, $name, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $entry->email,
                'password' => $password,
                'privacy_accepted_at' => now(),
            ]);

            $entry->update(['status' => 'registered']);

            return $user;
        });

        $this->sendVerificationEmail($user);

        if (! empty($sessionUuids)) {
            $this->collaboratorService->linkRetroactive($user, $sessionUuids);
        }

        return $user;
    }

    public function sendVerificationEmail(User $user): void
    {
        $verificationUrl = URL::temporarySignedRoute(
            'auth.verify-email',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        Mail::to($user->email)->queue(new VerificationMail($user->name, $verificationUrl));
    }

    public function verifyEmail(int $userId, string $hash): User
    {
        $user = User::findOrFail($userId);

        if (sha1($user->email) !== $hash) {
            throw new \InvalidArgumentException('Enlace de verificacion invalido.');
        }

        if ($user->email_verified_at) {
            return $user;
        }

        $user->update(['email_verified_at' => now()]);

        return $user->refresh();
    }
}
