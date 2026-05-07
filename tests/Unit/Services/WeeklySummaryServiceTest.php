<?php

namespace Tests\Unit\Services;

use App\Enums\AiUsageStatus;
use App\Enums\ListStatus;
use App\Enums\WeeklySummaryStatus;
use App\Mail\WeeklySummaryMail;
use App\Models\AiUsageLog;
use App\Models\ShoppingList;
use App\Models\User;
use App\Models\WeeklySummary;
use App\Services\WeeklySummaryService;
use App\Support\Ai\CircuitBreaker;
use App\Support\Ai\ClaudeClientInterface;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\FakeClaudeClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeedsProductHistory;
use Tests\TestCase;

class WeeklySummaryServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsProductHistory;

    private FakeClaudeClient $fakeClaude;
    private WeeklySummaryService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->fakeClaude = new FakeClaudeClient();
        $this->fakeClaude->cannedWeeklySummary = [
            ['nombre' => 'Leche', 'cantidad_tipica' => 1.0, 'unidad_tipica' => 'L', 'categoria' => 'lacteos_huevos', 'reason' => 'Compra habitual'],
            ['nombre' => 'Pan', 'cantidad_tipica' => 1.0, 'unidad_tipica' => 'ud', 'categoria' => 'panaderia', 'reason' => null],
        ];
        $this->app->instance(ClaudeClientInterface::class, $this->fakeClaude);
        $this->service = $this->app->make(WeeklySummaryService::class);
        (new CircuitBreaker())->reset();
    }

    public function test_eligible_users_excludes_soft_deleted(): void
    {
        $user = $this->makeActiveUser();
        $user->delete(); // soft delete

        $eligible = $this->service->eligibleUsers();

        $this->assertFalse($eligible->contains('id', $user->id));
    }

    public function test_eligible_users_excludes_unverified(): void
    {
        $user = $this->makeActiveUser(['email_verified_at' => null]);
        $this->seedWeeklyHistory($user, 4);

        $eligible = $this->service->eligibleUsers();

        $this->assertFalse($eligible->contains('id', $user->id));
    }

    public function test_eligible_users_excludes_inactive_users(): void
    {
        $user = $this->makeActiveUser();
        // Seed history older than inactivity cutoff (60 days by default)
        $date = Carbon::now('Europe/Madrid')->subDays(90);
        \DB::table('producto_historial')->insert([
            'user_id' => $user->id,
            'producto_nombre' => 'Old Product',
            'categoria' => 'otros',
            'cantidad' => 1,
            'unidad' => 'ud',
            'precio_real' => null,
            'fecha_compra' => $date,
            'lista_id' => null,
        ]);

        $eligible = $this->service->eligibleUsers();

        $this->assertFalse($eligible->contains('id', $user->id));
    }

    public function test_eligible_users_excludes_under_three_weeks_history(): void
    {
        $user = $this->makeActiveUser();
        $this->seedWeeklyHistory($user, 2); // only 2 weeks

        $eligible = $this->service->eligibleUsers();

        $this->assertFalse($eligible->contains('id', $user->id));
    }

    public function test_eligible_users_includes_user_with_three_weeks(): void
    {
        $user = $this->makeActiveUser();
        $this->seedWeeklyHistory($user, 3);

        $eligible = $this->service->eligibleUsers();

        $this->assertTrue($eligible->contains('id', $user->id));
    }

    public function test_generate_for_user_happy_path_persists_row_and_tracks_usage(): void
    {
        $user = $this->makeActiveUser();
        $this->seedWeeklyHistory($user, 4);

        $summary = $this->service->generateForUser($user);

        $this->assertInstanceOf(WeeklySummary::class, $summary);
        $this->assertSame(WeeklySummaryStatus::Pending, $summary->status);
        $this->assertNotNull($summary->payload_json);
        $this->assertNotEmpty($summary->payload_json);
        $this->assertCount(1, $this->fakeClaude->weeklySummaryCalls);
        $this->assertDatabaseHas('ai_usage_log', [
            'user_id' => $user->id,
            'operation' => 'summary',
            'status' => AiUsageStatus::Success->value,
        ]);
    }

    public function test_generate_for_user_idempotent_via_unique_constraint(): void
    {
        $user = $this->makeActiveUser();
        $this->seedWeeklyHistory($user, 4);

        $first = $this->service->generateForUser($user);
        $second = $this->service->generateForUser($user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, WeeklySummary::where('user_id', $user->id)->count());
        // Claude should be called only once — the second pass hits the early return
        $this->assertCount(1, $this->fakeClaude->weeklySummaryCalls);
    }

    public function test_generate_for_user_blocks_when_user_quota_exceeded(): void
    {
        config(['ai.rate_limits.free.suggestions_per_day' => 1]);
        $user = $this->makeActiveUser();
        $this->seedWeeklyHistory($user, 4);
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'operation' => \App\Enums\AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
        ]);

        $summary = $this->service->generateForUser($user);

        $this->assertSame(WeeklySummaryStatus::Failed, $summary->status);
        $this->assertStringContainsString('user quota', (string) $summary->error_message);
        $this->assertCount(0, $this->fakeClaude->weeklySummaryCalls);
    }

    public function test_generate_for_user_blocks_when_global_budget_exceeded(): void
    {
        config(['ai.budget_cap_monthly_usd' => 0.001]);
        $user = $this->makeActiveUser();
        $this->seedWeeklyHistory($user, 4);
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 1.0,
        ]);

        $summary = $this->service->generateForUser($user);

        $this->assertSame(WeeklySummaryStatus::Failed, $summary->status);
        $this->assertStringContainsString('budget', (string) $summary->error_message);
    }

    public function test_generate_for_user_handles_claude_exception(): void
    {
        $user = $this->makeActiveUser();
        $this->seedWeeklyHistory($user, 4);
        $this->fakeClaude->shouldThrow = new ClaudeException('api failed');

        $summary = $this->service->generateForUser($user);

        $this->assertSame(WeeklySummaryStatus::Failed, $summary->status);
        $this->assertStringContainsString('api failed', (string) $summary->error_message);
        $this->assertDatabaseHas('ai_usage_log', [
            'user_id' => $user->id,
            'operation' => 'summary',
            'status' => AiUsageStatus::Error->value,
        ]);
    }

    public function test_dispatch_email_sends_only_when_opted_in(): void
    {
        Mail::fake();
        $user = $this->makeActiveUser(['weekly_summary_email_opted_in' => true]);
        $this->seedWeeklyHistory($user, 4);
        $summary = $this->service->generateForUser($user);

        $this->service->dispatchEmailFor($summary);

        Mail::assertQueued(WeeklySummaryMail::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertSame(WeeklySummaryStatus::Dispatched, $summary->refresh()->status);
        $this->assertNotNull($summary->dispatched_at);
    }

    public function test_dispatch_email_skips_when_not_opted_in(): void
    {
        Mail::fake();
        $user = $this->makeActiveUser(['weekly_summary_email_opted_in' => false]);
        $this->seedWeeklyHistory($user, 4);
        $summary = $this->service->generateForUser($user);

        $this->service->dispatchEmailFor($summary);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        $this->assertSame(WeeklySummaryStatus::Dispatched, $summary->refresh()->status);
    }

    public function test_dispatch_email_rereads_opt_in_flag_to_close_race_window(): void
    {
        Mail::fake();
        $user = $this->makeActiveUser(['weekly_summary_email_opted_in' => true]);
        $this->seedWeeklyHistory($user, 4);
        $summary = $this->service->generateForUser($user);

        // Simulate the user opting out mid-run (after eligibleUsers snapshot)
        $user->forceFill(['weekly_summary_email_opted_in' => false])->save();

        $this->service->dispatchEmailFor($summary);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    public function test_dispatch_email_noop_for_failed_summary(): void
    {
        Mail::fake();
        $user = $this->makeActiveUser(['weekly_summary_email_opted_in' => true]);
        $summary = WeeklySummary::factory()->failed()->create(['user_id' => $user->id]);

        $this->service->dispatchEmailFor($summary);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
        $this->assertSame(WeeklySummaryStatus::Failed, $summary->refresh()->status);
    }

    public function test_mark_dismissed_sets_timestamp(): void
    {
        $user = $this->makeActiveUser();

        $this->service->markDismissed($user);

        $this->assertNotNull($user->refresh()->weekly_summary_in_app_dismissed_at);
    }

    public function test_save_selection_creates_new_list_with_all_items_marks_actioned(): void
    {
        $user = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);
        // Factory provides 2 items by default
        $result = $this->service->saveSelection($user, $summary, [0, 1], null);

        $this->assertInstanceOf(ShoppingList::class, $result['list']);
        $this->assertSame($user->id, $result['list']->user_id);
        $this->assertStringContainsString('Resumen semanal', $result['list']->name);
        $this->assertSame(2, $result['list']->items()->count());
        $this->assertSame(WeeklySummaryStatus::Actioned, $result['summary']->status);
        $this->assertSame([], $result['summary']->payload_json);
    }

    public function test_save_selection_partial_keeps_unselected_items_in_payload(): void
    {
        $user = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $result = $this->service->saveSelection($user, $summary, [0], null);

        $this->assertSame(1, $result['list']->items()->count());
        $this->assertSame(WeeklySummaryStatus::Pending, $result['summary']->status);
        $this->assertCount(1, $result['summary']->payload_json);
        $this->assertSame('Pan', $result['summary']->payload_json[0]['nombre']);
    }

    public function test_save_selection_appends_to_existing_active_list(): void
    {
        $user = $this->makeActiveUser();
        $existingList = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Active,
        ]);
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $result = $this->service->saveSelection($user, $summary, [0, 1], $existingList->id);

        $this->assertSame($existingList->id, $result['list']->id);
        $this->assertSame(2, $existingList->refresh()->items()->count());
    }

    public function test_save_selection_increments_quantity_for_same_name_and_unit_pending(): void
    {
        $user = $this->makeActiveUser();
        $existingList = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Active,
        ]);
        $existingList->items()->create([
            'name' => 'Leche',
            'quantity' => 1.0,
            'unit' => 'L',
            'is_purchased' => false,
            'position' => 0,
        ]);
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $this->service->saveSelection($user, $summary, [0], $existingList->id);

        $this->assertSame(1, $existingList->refresh()->items()->count());
        $item = $existingList->items()->first();
        $this->assertSame('2.00', (string) $item->quantity);
    }

    public function test_save_selection_creates_new_item_when_existing_has_different_unit(): void
    {
        $user = $this->makeActiveUser();
        $existingList = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Active,
        ]);
        $existingList->items()->create([
            'name' => 'Leche',
            'quantity' => 1.0,
            'unit' => 'ml',
            'is_purchased' => false,
            'position' => 0,
        ]);
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $this->service->saveSelection($user, $summary, [0], $existingList->id);

        $this->assertSame(2, $existingList->refresh()->items()->count());
    }

    public function test_save_selection_creates_new_item_when_existing_match_is_purchased(): void
    {
        $user = $this->makeActiveUser();
        $existingList = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Active,
        ]);
        $existingList->items()->create([
            'name' => 'Leche',
            'quantity' => 1.0,
            'unit' => 'L',
            'is_purchased' => true,
            'position' => 0,
        ]);
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $this->service->saveSelection($user, $summary, [0], $existingList->id);

        $this->assertSame(2, $existingList->refresh()->items()->count());
    }

    public function test_save_selection_normalizes_name_case_and_whitespace_for_match(): void
    {
        $user = $this->makeActiveUser();
        $existingList = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Active,
        ]);
        $existingList->items()->create([
            'name' => '  leche  ',
            'quantity' => 1.0,
            'unit' => 'L',
            'is_purchased' => false,
            'position' => 0,
        ]);
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $this->service->saveSelection($user, $summary, [0], $existingList->id);

        $this->assertSame(1, $existingList->refresh()->items()->count());
    }

    public function test_save_selection_respects_freemium_limit_for_new_list(): void
    {
        $user = $this->makeActiveUser();
        ShoppingList::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => ListStatus::Active,
        ]);
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $this->expectException(\OverflowException::class);
        $this->service->saveSelection($user, $summary, [0, 1], null);
    }

    public function test_save_selection_rejects_other_users_summary(): void
    {
        $a = $this->makeActiveUser();
        $b = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $b->id]);

        try {
            $this->service->saveSelection($a, $summary, [0], null);
            $this->fail('Expected HttpException');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_save_selection_rejects_target_list_of_other_user(): void
    {
        $a = $this->makeActiveUser();
        $b = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $a->id]);
        $foreignList = ShoppingList::factory()->create([
            'user_id' => $b->id,
            'status' => ListStatus::Active,
        ]);

        try {
            $this->service->saveSelection($a, $summary, [0], $foreignList->id);
            $this->fail('Expected HttpException');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_save_selection_rejects_archived_target_list(): void
    {
        $user = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);
        $archived = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Archived,
        ]);

        try {
            $this->service->saveSelection($user, $summary, [0], $archived->id);
            $this->fail('Expected HttpException');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_save_selection_rejects_empty_indices(): void
    {
        $user = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->saveSelection($user, $summary, [], null);
    }

    public function test_save_selection_rejects_out_of_range_indices(): void
    {
        $user = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->saveSelection($user, $summary, [0, 5], null);
    }

    public function test_save_selection_dedupes_repeated_indices(): void
    {
        $user = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $result = $this->service->saveSelection($user, $summary, [0, 0, 0], null);

        $this->assertSame(1, $result['list']->items()->count());
        $this->assertCount(1, $result['summary']->payload_json);
    }

    public function test_save_selection_uses_custom_new_list_name_when_provided(): void
    {
        $user = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $result = $this->service->saveSelection($user, $summary, [0], null, 'Mi compra');

        $this->assertSame('Mi compra', $result['list']->name);
    }

    public function test_save_selection_falls_back_to_default_name_when_blank(): void
    {
        $user = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $result = $this->service->saveSelection($user, $summary, [0], null, '   ');

        $this->assertStringContainsString('Resumen semanal', $result['list']->name);
    }

    public function test_save_selection_skips_payload_entries_without_nombre(): void
    {
        $user = $this->makeActiveUser();
        $summary = WeeklySummary::factory()->create([
            'user_id' => $user->id,
            'payload_json' => [
                ['nombre' => 'Leche', 'cantidad_tipica' => 1.0, 'unidad_tipica' => 'L'],
                ['cantidad_tipica' => 1.0], // missing nombre
            ],
        ]);

        $result = $this->service->saveSelection($user, $summary, [0, 1], null);

        $this->assertSame(1, $result['list']->items()->count());
        // Both indices removed from payload regardless (selected_set).
        $this->assertSame([], $result['summary']->payload_json);
        $this->assertSame(WeeklySummaryStatus::Actioned, $result['summary']->status);
    }

    public function test_current_week_start_returns_monday(): void
    {
        $start = $this->service->currentWeekStart();
        $this->assertSame(
            Carbon::parse($start)->dayOfWeek,
            Carbon::MONDAY,
        );
    }

    public function test_generate_budget_exceeded_records_budget_capped_in_log(): void
    {
        config(['ai.budget_cap_monthly_usd' => 0.001]);
        $user = $this->makeActiveUser();
        $this->seedWeeklyHistory($user, 4);
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'status' => AiUsageStatus::Success,
            'estimated_cost_usd' => 1.0,
        ]);

        $this->service->generateForUser($user);

        $this->assertDatabaseHas('ai_usage_log', [
            'user_id' => $user->id,
            'status' => AiUsageStatus::BudgetCapped->value,
            'operation' => 'summary',
        ]);
    }

    public function test_generate_user_quota_exceeded_records_user_capped_in_log(): void
    {
        config(['ai.rate_limits.free.suggestions_per_day' => 1]);
        $user = $this->makeActiveUser();
        $this->seedWeeklyHistory($user, 4);
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'operation' => \App\Enums\AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
        ]);

        $this->service->generateForUser($user);

        $this->assertDatabaseHas('ai_usage_log', [
            'user_id' => $user->id,
            'status' => AiUsageStatus::UserCapped->value,
            'operation' => 'summary',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeActiveUser(array $attributes = []): User
    {
        return User::factory()->createOne(array_merge([
            'email_verified_at' => now(),
        ], $attributes));
    }
}
