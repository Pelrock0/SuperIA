<?php

namespace Tests\Unit\Services;

use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use App\Models\ShoppingList;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\AdminMetricsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminMetricsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AdminMetricsService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminMetricsService();
    }

    public function test_returns_user_counts(): void
    {
        User::factory()->count(3)->createOne();

        $metrics = $this->service->getMetrics();

        $this->assertArrayHasKey('users_total', $metrics);
        $this->assertGreaterThanOrEqual(1, $metrics['users_total']);
    }

    public function test_returns_list_counts(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->create(['user_id' => $user->id]);

        $metrics = $this->service->getMetrics();

        $this->assertArrayHasKey('lists_total', $metrics);
        $this->assertGreaterThanOrEqual(1, $metrics['lists_total']);
    }

    public function test_returns_ai_metrics(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->create(['user_id' => $user->id, 'status' => AiUsageStatus::Success]);

        $metrics = $this->service->getMetrics();

        $this->assertArrayHasKey('ai_calls_today', $metrics);
        $this->assertArrayHasKey('ai_calls_month', $metrics);
        $this->assertArrayHasKey('ai_cost_month', $metrics);
    }

    public function test_returns_waitlist_count(): void
    {
        $metrics = $this->service->getMetrics();

        $this->assertArrayHasKey('waitlist_pending', $metrics);
    }

    public function test_returns_all_expected_keys(): void
    {
        $metrics = $this->service->getMetrics();

        $expected = ['users_total', 'users_active_7d', 'lists_created_today', 'lists_total', 'ai_calls_today', 'ai_calls_month', 'ai_cost_month', 'waitlist_pending'];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $metrics);
        }
    }
}
