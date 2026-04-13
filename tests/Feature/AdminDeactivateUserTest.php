<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminDeactivateUserTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->createOne([
            'password' => 'Password1',
            'is_active' => false,
        ]);

        $service = new AuthService();
        $result = $service->login($user->email, 'Password1', '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertSame('ACCOUNT_DEACTIVATED', $result['error']);
    }

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->createOne([
            'password' => 'Password1',
            'is_active' => true,
        ]);

        $service = new AuthService();
        $result = $service->login($user->email, 'Password1', '127.0.0.1');

        $this->assertTrue($result['success']);
    }

    public function test_is_active_defaults_to_true(): void
    {
        $user = User::factory()->createOne();

        $this->assertTrue((bool) $user->is_active);
    }

    public function test_ai_daily_limit_override_respected(): void
    {
        $user = User::factory()->createOne(['ai_daily_limit_override' => 50]);
        $tracker = app(\App\Support\Ai\AiUsageTracker::class);

        $this->assertTrue($tracker->canUse($user, \App\Enums\AiOperation::Suggestion));
    }

    public function test_ai_daily_limit_override_null_uses_default(): void
    {
        $user = User::factory()->createOne(['ai_daily_limit_override' => null]);
        $tracker = app(\App\Support\Ai\AiUsageTracker::class);

        $this->assertTrue($tracker->canUse($user, \App\Enums\AiOperation::Suggestion));
    }
}
