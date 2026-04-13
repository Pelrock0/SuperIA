<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function login(string $email, string $password, string $ipAddress, bool $remember = false): array
    {
        if ($this->isLockedOut($email)) {
            return [
                'success' => false,
                'error' => 'ACCOUNT_LOCKED',
                'message' => 'Cuenta bloqueada temporalmente. Intentalo de nuevo en 15 minutos.',
            ];
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            LoginAttempt::record($email, $ipAddress);

            return [
                'success' => false,
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'Credenciales incorrectas.',
            ];
        }

        if (!$user->is_active) {
            return [
                'success' => false,
                'error' => 'ACCOUNT_DEACTIVATED',
                'message' => 'Tu cuenta ha sido desactivada.',
            ];
        }

        if (!$user->email_verified_at) {
            return [
                'success' => false,
                'error' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Debes verificar tu email antes de iniciar sesión.',
            ];
        }

        LoginAttempt::clearForEmail($email);

        if ($remember) {
            JWTAuth::factory()->setTTL(config('jwt.refresh_ttl'));
        }

        $token = JWTAuth::fromUser($user);

        return [
            'success' => true,
            'token' => $token,
            'user' => $user,
        ];
    }

    public function logout(): void
    {
        JWTAuth::invalidate(JWTAuth::getToken());
    }

    public function refresh(): string
    {
        return JWTAuth::refresh(JWTAuth::getToken());
    }

    public function isLockedOut(string $email): bool
    {
        return LoginAttempt::recentFailedCount($email, self::LOCKOUT_MINUTES) >= self::MAX_ATTEMPTS;
    }
}
