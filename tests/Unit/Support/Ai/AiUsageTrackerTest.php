<?php

namespace Tests\Unit\Support\Ai;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Support\Ai\AiUsageTracker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AiUsageTrackerTest extends TestCase
{
    use DatabaseTransactions;

    private AiUsageTracker $tracker;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.rate_limits.free.suggestions_per_day' => 20]);
        $this->tracker = new AiUsageTracker();
    }

    public function test_can_use_when_under_quota(): void
    {
        $user = User::factory()->createOne();

        $this->assertTrue($this->tracker->canUse($user, AiOperation::Suggestion));
    }

    public function test_cannot_use_when_at_quota(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->count(20)->create([
            'user_id' => $user->id,
            'operation' => AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
        ]);

        $this->assertFalse($this->tracker->canUse($user, AiOperation::Suggestion));
    }

    public function test_used_today_for_operation_counts_only_success(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->count(3)->create([
            'user_id' => $user->id,
            'operation' => AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
        ]);
        AiUsageLog::factory()->error()->createOne([
            'user_id' => $user->id,
            'operation' => AiOperation::Suggestion,
        ]);

        $this->assertSame(3, $this->tracker->usedTodayForOperation($user, AiOperation::Suggestion));
    }

    public function test_used_today_for_operation_excludes_other_users(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        AiUsageLog::factory()->count(5)->create([
            'user_id' => $other->id,
            'operation' => AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
        ]);

        $this->assertSame(0, $this->tracker->usedTodayForOperation($user, AiOperation::Suggestion));
    }

    public function test_used_today_across_all_operations_sums_them(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->count(5)->create([
            'user_id' => $user->id,
            'operation' => AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
        ]);
        AiUsageLog::factory()->count(3)->create([
            'user_id' => $user->id,
            'operation' => AiOperation::Replenishment,
            'status' => AiUsageStatus::Success,
        ]);
        AiUsageLog::factory()->count(2)->create([
            'user_id' => $user->id,
            'operation' => AiOperation::Complement,
            'status' => AiUsageStatus::Success,
        ]);

        $this->assertSame(10, $this->tracker->usedTodayAcrossAllOperations($user));
    }

    public function test_record_creates_log_row(): void
    {
        $user = User::factory()->createOne();

        $log = $this->tracker->record($user, AiOperation::Suggestion, AiUsageStatus::Success, 0.02);

        $this->assertDatabaseHas('ai_usage_log', [
            'id' => $log->id,
            'user_id' => $user->id,
            'operation' => AiOperation::Suggestion->value,
            'status' => AiUsageStatus::Success->value,
        ]);
        $this->assertSame('0.0200', (string) $log->fresh()->estimated_cost_usd);
    }

    public function test_record_accepts_null_user(): void
    {
        $log = $this->tracker->record(null, AiOperation::Suggestion, AiUsageStatus::BudgetCapped);

        $this->assertNull($log->user_id);
    }

    public function test_quota_is_shared_across_all_operations(): void
    {
        $user = User::factory()->createOne();
        AiUsageLog::factory()->count(15)->create([
            'user_id' => $user->id,
            'operation' => AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
        ]);
        AiUsageLog::factory()->count(5)->create([
            'user_id' => $user->id,
            'operation' => AiOperation::Replenishment,
            'status' => AiUsageStatus::Success,
        ]);

        // 15 + 5 = 20 = quota, so NO operation can run anymore
        $this->assertFalse($this->tracker->canUse($user, AiOperation::Suggestion));
        $this->assertFalse($this->tracker->canUse($user, AiOperation::Replenishment));
        $this->assertFalse($this->tracker->canUse($user, AiOperation::Complement));
    }
}
