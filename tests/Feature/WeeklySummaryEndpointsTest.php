<?php

namespace Tests\Feature;

use App\Enums\ListStatus;
use App\Enums\WeeklySummaryStatus;
use App\Models\ShoppingList;
use App\Models\User;
use App\Models\WeeklySummary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class WeeklySummaryEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    private function currentWeekStart(): string
    {
        return Carbon::now('Europe/Madrid')->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    // ---- GET /api/weekly-summary/latest ----

    public function test_latest_returns_current_week_summary(): void
    {
        $user = User::factory()->createOne();
        $summary = WeeklySummary::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $this->currentWeekStart(),
            'status' => WeeklySummaryStatus::Pending,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/weekly-summary/latest');

        $response->assertOk()
            ->assertJsonPath('data.summary.id', $summary->id)
            ->assertJsonPath('data.summary.week_start_date', $this->currentWeekStart());
    }

    public function test_latest_returns_404_when_no_summary_this_week(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/weekly-summary/latest');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_SUMMARY_THIS_WEEK');
    }

    public function test_latest_returns_404_when_dismissed(): void
    {
        $weekStart = $this->currentWeekStart();
        $user = User::factory()->createOne([
            'weekly_summary_in_app_dismissed_at' => Carbon::parse($weekStart)->addHour(),
        ]);
        WeeklySummary::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $this->currentWeekStart(),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/weekly-summary/latest');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'DISMISSED');
    }

    public function test_latest_returns_404_when_summary_failed(): void
    {
        $user = User::factory()->createOne();
        WeeklySummary::factory()->failed()->create([
            'user_id' => $user->id,
            'week_start_date' => $this->currentWeekStart(),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/weekly-summary/latest');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_SUMMARY_THIS_WEEK');
    }

    public function test_latest_returns_404_when_summary_actioned(): void
    {
        $user = User::factory()->createOne();
        WeeklySummary::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $this->currentWeekStart(),
            'status' => WeeklySummaryStatus::Actioned,
            'payload_json' => [],
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/weekly-summary/latest');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_SUMMARY_THIS_WEEK');
    }

    public function test_latest_requires_auth(): void
    {
        $this->getJson('/api/weekly-summary/latest')->assertStatus(401);
    }

    // ---- POST /api/weekly-summary/dismiss ----

    public function test_dismiss_marks_banner_as_dismissed(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/weekly-summary/dismiss');

        $response->assertOk();
        $this->assertNotNull($user->refresh()->weekly_summary_in_app_dismissed_at);
    }

    public function test_dismiss_requires_auth(): void
    {
        $this->postJson('/api/weekly-summary/dismiss')->assertStatus(401);
    }

    // ---- POST /api/weekly-summary/{id}/save ----

    public function test_save_creates_new_list_and_marks_actioned_when_all_selected(): void
    {
        $user = User::factory()->createOne();
        $summary = WeeklySummary::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $this->currentWeekStart(),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => [0, 1],
                'target_list_id' => null,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.list.user_id', $user->id)
            ->assertJsonPath('data.summary.is_actioned', true)
            ->assertJsonPath('data.summary.status', 'actioned');
        $this->assertSame(1, $user->refresh()->shoppingLists()->count());
        $this->assertSame(WeeklySummaryStatus::Actioned, $summary->refresh()->status);
    }

    public function test_save_partial_keeps_summary_pending(): void
    {
        $user = User::factory()->createOne();
        $summary = WeeklySummary::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $this->currentWeekStart(),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => [0],
                'target_list_id' => null,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.is_actioned', false)
            ->assertJsonPath('data.summary.status', 'pending');
        $this->assertCount(1, $response->json('data.summary.remaining_items'));
    }

    public function test_save_appends_to_existing_list(): void
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Active,
        ]);
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => [0, 1],
                'target_list_id' => $list->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.list.id', $list->id);
        $this->assertSame(2, $list->refresh()->items()->count());
        $this->assertSame(1, $user->refresh()->shoppingLists()->count());
    }

    public function test_save_returns_403_freemium_limit_for_new_list(): void
    {
        $user = User::factory()->createOne();
        ShoppingList::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => ListStatus::Active,
        ]);
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => [0, 1],
                'target_list_id' => null,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FREEMIUM_LIMIT');
    }

    public function test_save_returns_404_for_other_users_summary(): void
    {
        $owner = User::factory()->createOne();
        $intruder = User::factory()->createOne();
        $summary = WeeklySummary::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeaders($this->authHeaders($intruder))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => [0],
                'target_list_id' => null,
            ]);

        $response->assertStatus(404);
    }

    public function test_save_returns_404_for_other_users_target_list(): void
    {
        $owner = User::factory()->createOne();
        $intruder = User::factory()->createOne();
        $summary = WeeklySummary::factory()->create(['user_id' => $intruder->id]);
        $foreignList = ShoppingList::factory()->create([
            'user_id' => $owner->id,
            'status' => ListStatus::Active,
        ]);

        $response = $this->withHeaders($this->authHeaders($intruder))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => [0],
                'target_list_id' => $foreignList->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_save_returns_404_for_archived_target_list(): void
    {
        $user = User::factory()->createOne();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);
        $archived = ShoppingList::factory()->create([
            'user_id' => $user->id,
            'status' => ListStatus::Archived,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => [0],
                'target_list_id' => $archived->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_save_returns_422_on_empty_selection(): void
    {
        $user = User::factory()->createOne();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => [],
                'target_list_id' => null,
            ]);

        $response->assertStatus(422);
    }

    public function test_save_returns_422_on_out_of_range_indices(): void
    {
        $user = User::factory()->createOne();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => [0, 5],
                'target_list_id' => null,
            ]);

        $response->assertStatus(422);
    }

    public function test_save_returns_422_on_non_integer_indices(): void
    {
        $user = User::factory()->createOne();
        $summary = WeeklySummary::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/weekly-summary/{$summary->id}/save", [
                'selected_indices' => ['foo'],
                'target_list_id' => null,
            ]);

        $response->assertStatus(422);
    }

    public function test_save_requires_auth(): void
    {
        $summary = WeeklySummary::factory()->create();
        $this->postJson("/api/weekly-summary/{$summary->id}/save", [
            'selected_indices' => [0],
            'target_list_id' => null,
        ])->assertStatus(401);
    }

    // ---- POST /api/settings/weekly-summary-email ----

    public function test_toggle_email_opts_in(): void
    {
        $user = User::factory()->createOne(['weekly_summary_email_opted_in' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/settings/weekly-summary-email', ['enabled' => true]);

        $response->assertOk()->assertJsonPath('data.weekly_summary_email_opted_in', true);
        $this->assertTrue((bool) $user->refresh()->weekly_summary_email_opted_in);
    }

    public function test_toggle_email_opts_out(): void
    {
        $user = User::factory()->createOne(['weekly_summary_email_opted_in' => true]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/settings/weekly-summary-email', ['enabled' => false]);

        $response->assertOk()->assertJsonPath('data.weekly_summary_email_opted_in', false);
        $this->assertFalse((bool) $user->refresh()->weekly_summary_email_opted_in);
    }

    public function test_toggle_email_rejects_missing_enabled_field(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/settings/weekly-summary-email', []);

        $response->assertStatus(422);
    }

    public function test_toggle_email_requires_auth(): void
    {
        $this->postJson('/api/settings/weekly-summary-email', ['enabled' => true])->assertStatus(401);
    }

    // ---- GET /unsubscribe/weekly-summary/{user} (public, signed) ----

    public function test_unsubscribe_valid_signed_url_flips_opt_out(): void
    {
        $user = User::factory()->createOne(['weekly_summary_email_opted_in' => true]);
        $url = URL::temporarySignedRoute(
            'weekly-summary.unsubscribe',
            now()->addDays(30),
            ['user' => $user->id],
        );

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSeeText('dado de baja', false);
        $this->assertFalse((bool) $user->refresh()->weekly_summary_email_opted_in);
    }

    public function test_unsubscribe_expired_signed_url_rejected(): void
    {
        $user = User::factory()->createOne(['weekly_summary_email_opted_in' => true]);
        $url = URL::temporarySignedRoute(
            'weekly-summary.unsubscribe',
            now()->subMinute(), // already expired
            ['user' => $user->id],
        );

        $response = $this->get($url);

        $response->assertStatus(403);
        $this->assertTrue((bool) $user->refresh()->weekly_summary_email_opted_in);
    }

    public function test_unsubscribe_tampered_signature_rejected(): void
    {
        $user = User::factory()->createOne(['weekly_summary_email_opted_in' => true]);
        $url = URL::temporarySignedRoute(
            'weekly-summary.unsubscribe',
            now()->addDays(30),
            ['user' => $user->id],
        );
        $tampered = preg_replace('/signature=[a-f0-9]+/', 'signature=deadbeefdeadbeef', $url);

        $response = $this->get($tampered);

        $response->assertStatus(403);
        $this->assertTrue((bool) $user->refresh()->weekly_summary_email_opted_in);
    }

    public function test_unsubscribe_replay_is_idempotent(): void
    {
        $user = User::factory()->createOne(['weekly_summary_email_opted_in' => true]);
        $url = URL::temporarySignedRoute(
            'weekly-summary.unsubscribe',
            now()->addDays(30),
            ['user' => $user->id],
        );

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $this->assertFalse((bool) $user->refresh()->weekly_summary_email_opted_in);
    }
}
