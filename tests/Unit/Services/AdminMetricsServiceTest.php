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

    public function test_active_users_7d_excludes_activity_older_than_7_days(): void
    {
        $user = User::factory()->createOne();
        // Insert historial row 8 days ago — must NOT count
        \Illuminate\Support\Facades\DB::table('producto_historial')->insert([
            'user_id' => $user->id,
            'producto_nombre' => 'Old',
            'fecha_compra' => now()->subDays(8)->toDateString(),
            'lista_id' => null,
        ]);

        $before = $this->service->getMetrics()['users_active_7d'];

        // Insert one from today — must count
        \Illuminate\Support\Facades\DB::table('producto_historial')->insert([
            'user_id' => $user->id,
            'producto_nombre' => 'New',
            'fecha_compra' => now()->toDateString(),
            'lista_id' => null,
        ]);

        $after = $this->service->getMetrics()['users_active_7d'];

        $this->assertGreaterThan($before, $after);
    }

    public function test_ai_cost_month_is_float_rounded_to_2_decimals(): void
    {
        $baseline = $this->service->getMetrics()['ai_cost_month'];

        $user = User::factory()->createOne();
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 1.50,
        ]);

        $metrics = $this->service->getMetrics();

        $this->assertIsFloat($metrics['ai_cost_month']);
        $this->assertSame(round($baseline + 1.50, 2), $metrics['ai_cost_month']);
    }

    public function test_active_users_7d_is_integer(): void
    {
        $metrics = $this->service->getMetrics();

        $this->assertIsInt($metrics['users_active_7d']);
    }
}
