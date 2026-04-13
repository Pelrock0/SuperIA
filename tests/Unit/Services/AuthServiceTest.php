<?php

namespace Tests\Unit\Services;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AuthService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
    }

    public function test_is_locked_out_returns_false_under_threshold(): void
    {
        $email = 'test@example.com';

        for ($i = 0; $i < 4; $i++) {
            LoginAttempt::record($email, '127.0.0.1');
        }

        $this->assertFalse($this->service->isLockedOut($email));
    }

    public function test_is_locked_out_returns_true_at_threshold(): void
    {
        $email = 'test@example.com';

        for ($i = 0; $i < 5; $i++) {
            LoginAttempt::record($email, '127.0.0.1');
        }

        $this->assertTrue($this->service->isLockedOut($email));
    }

    public function test_is_locked_out_ignores_old_attempts(): void
    {
        $email = 'test@example.com';

        for ($i = 0; $i < 5; $i++) {
            LoginAttempt::create([
                'email' => $email,
                'ip_address' => '127.0.0.1',
                'attempted_at' => now()->subMinutes(16),
            ]);
        }

        $this->assertFalse($this->service->isLockedOut($email));
    }

    public function test_login_returns_error_when_locked(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        for ($i = 0; $i < 5; $i++) {
            LoginAttempt::record($user->email, '127.0.0.1');
        }

        $result = $this->service->login($user->email, 'Password1', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertEquals('ACCOUNT_LOCKED', $result['error']);
    }

    public function test_login_returns_error_for_wrong_password(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        $result = $this->service->login($user->email, 'Wrong1', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $result['error']);
        $this->assertEquals(1, LoginAttempt::where('email', $user->email)->count());
    }

    public function test_login_returns_error_for_unverified_email(): void
    {
        $user = User::factory()->unverified()->createOne(['password' => 'Password1']);

        $result = $this->service->login($user->email, 'Password1', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertEquals('EMAIL_NOT_VERIFIED', $result['error']);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        $result = $this->service->login($user->email, 'Password1', '127.0.0.1');

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['token']);
        $this->assertEquals($user->id, $result['user']->id);
    }

    public function test_login_returns_error_for_nonexistent_user(): void
    {
        $result = $this->service->login('nonexistent@example.com', 'Password1', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $result['error']);
    }
}
