<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return url("/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}");
        });

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'data' => [
                'message' => 'Si el email esta registrado, recibiras un enlace de recuperacion.',
            ],
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update([
                    'password' => $password,
                ]);
                $user->incrementJwtVersion();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'data' => ['message' => 'Contrasena restablecida correctamente.'],
            ]);
        }

        return response()->json([
            'error' => [
                'code' => 'RESET_FAILED',
                'message' => 'El enlace de restablecimiento es invalido o ha expirado.',
            ],
        ], 422);
    }
}
