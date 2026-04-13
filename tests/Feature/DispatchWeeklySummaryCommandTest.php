<?php

namespace Tests\Feature;

use App\Enums\WeeklySummaryStatus;
use App\Mail\WeeklySummaryMail;
use App\Models\User;
use App\Models\WeeklySummary;
use App\Support\Ai\CircuitBreaker;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\FakeClaudeClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeedsProductHistory;
use Tests\TestCase;

class DispatchWeeklySummaryCommandTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsProductHistory;

    private FakeClaudeClient $fakeClaude;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->fakeClaude = new FakeClaudeClient();
        $this->fakeClaude->cannedWeeklySummary = [
            ['nombre' => 'Leche', 'cantidad_tipica' => 1.0, 'unidad_tipica' => 'L', 'categoria' => 'lacteos_huevos', 'reason' => null],
        ];
        $this->app->instance(ClaudeClientInterface::class, $this->fakeClaude);
        (new CircuitBreaker())->reset();
    }

    public function test_happy_path_generates_and_dispatches(): void
    {
        Mail::fake();
        $user = $this->makeEligible(['weekly_summary_email_opted_in' => true]);

        $this->artisan('ai:dispatch-weekly-summary')->assertSuccessful();

        Mail::assertQueued(WeeklySummaryMail::class);
        $this->assertDatabaseHas('weekly_summaries', [
            'user_id' => $user->id,
            'status' => WeeklySummaryStatus::Dispatched->value,
        ]);
    }

    public function test_kill_switch_disabled_prevents_any_dispatch(): void
    {
        Mail::fake();
        config(['ai.weekly_summary.enabled' => false]);
        $user = $this->makeEligible(['weekly_summary_email_opted_in' => true]);

        $this->artisan('ai:dispatch-weekly-summary')
            ->expectsOutputToContain('disabled by config')
            ->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertSame(0, WeeklySummary::count());
    }

    public function test_failure_isolation_one_user_fails_others_succeed(): void
    {
        Mail::fake();
        $good1 = $this->makeEligible(['weekly_summary_email_opted_in' => true]);
        $good2 = $this->makeEligible(['weekly_summary_email_opted_in' => true]);

        // Throw on the second Claude call only. We toggle `shouldThrow` mid-run by swapping a decorator.
        $failing = new class extends FakeClaudeClient {
            private int $callCount = 0;

            #[\Override]
            public function generateWeeklySummary(array $context): array
            {
                $this->callCount++;
                if ($this->callCount === 2) {
                    throw new ClaudeException('simulated failure');
                }
                return [
                    'products' => [['nombre' => 'Leche', 'cantidad_tipica' => 1.0, 'unidad_tipica' => 'L', 'categoria' => 'lacteos_huevos', 'reason' => null]],
                    'estimated_cost_usd' => 0.001,
                ];
            }
        };
        $this->app->instance(ClaudeClientInterface::class, $failing);

        $this->artisan('ai:dispatch-weekly-summary')->assertSuccessful();

        $this->assertSame(2, WeeklySummary::count());
        $successful = WeeklySummary::where('status', WeeklySummaryStatus::Dispatched->value)->count();
        $failed = WeeklySummary::where('status', WeeklySummaryStatus::Failed->value)->count();
        $this->assertSame(1, $successful);
        $this->assertSame(1, $failed);
    }

    public function test_exit_code_zero_even_when_no_eligible_users(): void
    {
        Mail::fake();

        $this->artisan('ai:dispatch-weekly-summary')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_logs_metrics_on_done(): void
    {
        Mail::fake();
        $this->makeEligible(['weekly_summary_email_opted_in' => false]);

        $this->artisan('ai:dispatch-weekly-summary')
            ->expectsOutputToContain('weekly_summary.dispatch.done')
            ->assertSuccessful();
    }

    public function test_idempotent_second_run_same_week_does_not_duplicate(): void
    {
        Mail::fake();
        $user = $this->makeEligible(['weekly_summary_email_opted_in' => true]);

        $this->artisan('ai:dispatch-weekly-summary')->assertSuccessful();
        $this->artisan('ai:dispatch-weekly-summary')->assertSuccessful();

        $this->assertSame(1, WeeklySummary::where('user_id', $user->id)->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeEligible(array $attributes = []): User
    {
        $user = User::factory()->createOne(array_merge([
            'email_verified_at' => now(),
        ], $attributes));
        $this->seedWeeklyHistory($user, 4);
        return $user;
    }
}
