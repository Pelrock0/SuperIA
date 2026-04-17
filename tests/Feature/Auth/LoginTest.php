<?php

namespace Tests\Feature\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginTest extends TestCase
{
    use DatabaseTransactions;

    // === Login Success ===

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password1',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'email_verified_at'], 'token']])
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_login_response_includes_email_verified_at_for_verified_users(): void
    {
        $user = User::factory()->createOne([
            'password' => 'Password1',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password1',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.user.email_verified_at'));
    }

    public function test_login_clears_failed_attempts_on_success(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        LoginAttempt::record($user->email, '127.0.0.1');
        LoginAttempt::record($user->email, '127.0.0.1');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password1',
        ]);

        $this->assertEquals(0, LoginAttempt::recentFailedCount($user->email));
    }

    // === Login Failure ===

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword1',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS')
            ->assertJsonPath('error.message', 'Credenciales incorrectas.');
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'Password1',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('error.message', 'Credenciales incorrectas.');
    }

    public function test_login_records_failed_attempt(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword1',
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'email' => $user->email,
        ]);
    }

    public function test_login_fails_with_unverified_email(): void
    {
        $user = User::factory()->unverified()->createOne(['password' => 'Password1']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password1',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
    }

    // === Account Lockout ===

    public function test_login_locks_account_after_5_failed_attempts(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        for ($i = 0; $i < 5; $i++) {
            LoginAttempt::record($user->email, '127.0.0.1');
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password1',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'ACCOUNT_LOCKED');
    }

    public function test_login_succeeds_after_lockout_expires(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        for ($i = 0; $i < 5; $i++) {
            LoginAttempt::create([
                'email' => $user->email,
                'ip_address' => '127.0.0.1',
                'attempted_at' => now()->subMinutes(16),
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password1',
        ]);

        $response->assertOk();
    }

    // === Validation ===

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'Password1',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // === Logout ===

    public function test_logout_invalidates_token(): void
    {
        $user = User::factory()->createOne();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJsonPath('data.message', 'Sesion cerrada correctamente.');
    }

    public function test_logout_fails_without_token(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertUnauthorized();
    }

    // === Token Refresh ===

    public function test_refresh_returns_new_token(): void
    {
        $user = User::factory()->createOne();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_refresh_fails_without_token(): void
    {
        $response = $this->postJson('/api/auth/refresh');

        $response->assertUnauthorized();
    }
}
