<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    // === Forgot Password ===

    public function test_forgot_password_always_returns_same_message(): void
    {
        $user = User::factory()->createOne();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Si el email esta registrado, recibiras un enlace de recuperacion.');
    }

    public function test_forgot_password_returns_same_message_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Si el email esta registrado, recibiras un enlace de recuperacion.');
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();
    }

    // === Reset Password ===

    public function test_reset_password_with_valid_token(): void
    {
        $user = User::factory()->createOne(['password' => 'OldPassword1']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Contrasena restablecida correctamente.');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1', $user->password));
    }

    public function test_reset_password_increments_jwt_version(): void
    {
        $user = User::factory()->createOne(['jwt_version' => 0]);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $user->refresh();
        $this->assertEquals(1, $user->jwt_version);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->createOne();

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'RESET_FAILED');
    }

    public function test_reset_password_fails_with_weak_password(): void
    {
        $user = User::factory()->createOne();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertUnprocessable();
    }

    public function test_reset_password_requires_all_fields(): void
    {
        $response = $this->postJson('/api/auth/reset-password', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    public function test_reset_password_token_is_single_use(): void
    {
        $user = User::factory()->createOne();
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AnotherPassword1',
            'password_confirmation' => 'AnotherPassword1',
        ]);

        $response->assertStatus(422);
    }
}
