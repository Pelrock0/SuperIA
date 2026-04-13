<?php

namespace Tests\Unit\Support\Ai;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Mail\BudgetCapExceededAlert;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Support\Ai\BudgetCap;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BudgetCapTest extends TestCase
{
    use DatabaseTransactions;

    private BudgetCap $cap;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Mail::fake();
        config(['ai.budget_cap_monthly_usd' => 10]);
        config(['ai.admin_alert_email' => 'admin@superia.test']);
        $this->cap = new BudgetCap();
    }

    public function test_can_spend_when_below_limit(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 5,
        ]);

        $this->assertTrue($this->cap->canSpend());
    }

    public function test_cannot_spend_when_at_or_over_limit(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 15,
        ]);

        $this->assertFalse($this->cap->canSpend());
    }

    public function test_current_month_spend_sums_only_success(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 3,
        ]);
        AiUsageLog::factory()->error()->createOne([
            'user_id' => $user->id,
            'estimated_cost_usd' => 99,
        ]);

        $spend = $this->cap->currentMonthSpendUsd();
        // The spend includes the 3.0 we just created; other tests may add rows in the same transaction.
        // Assert the error row (99) is NOT counted by checking spend is well below 99.
        $this->assertGreaterThanOrEqual(3.0, $spend);
        $this->assertLessThan(50.0, $spend);
    }

    public function test_notify_if_exceeded_queues_alert(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 15,
        ]);

        $this->cap->notifyIfExceeded();

        Mail::assertQueued(BudgetCapExceededAlert::class);
    }

    public function test_notify_is_dedup_per_day(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 15,
        ]);

        $this->cap->notifyIfExceeded();
        $this->cap->notifyIfExceeded();
        $this->cap->notifyIfExceeded();

        Mail::assertQueued(BudgetCapExceededAlert::class, 1);
    }

    public function test_notify_noop_when_below_limit(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 5,
        ]);

        $this->cap->notifyIfExceeded();

        Mail::assertNothingQueued();
    }

    public function test_zero_limit_means_unlimited(): void
    {
        config(['ai.budget_cap_monthly_usd' => 0]);

        $user = User::factory()->createOne();
        AiUsageLog::factory()->createOne([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 9999,
        ]);

        $this->assertTrue($this->cap->canSpend());
    }
}
