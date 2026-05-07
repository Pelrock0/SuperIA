<?php

namespace Tests\Unit\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeleteExpiredAccountsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_hard_deletes_expired_accounts(): void
    {
        $user = User::factory()->createOne([
            'scheduled_hard_delete_at' => now()->subDay(),
        ]);
        $user->delete(); // soft delete

        $this->artisan('accounts:delete-expired')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_command_skips_non_expired_accounts(): void
    {
        $user = User::factory()->createOne([
            'scheduled_hard_delete_at' => now()->addDays(15),
        ]);
        $user->delete();

        $this->artisan('accounts:delete-expired')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_command_outputs_count_of_deleted_accounts(): void
    {
        $user = User::factory()->createOne([
            'scheduled_hard_delete_at' => now()->subDay(),
        ]);
        $user->delete();

        $this->artisan('accounts:delete-expired')
            ->expectsOutputToContain('Hard-deleted 1 expired account')
            ->assertSuccessful();
    }

    public function test_command_returns_zero_when_nothing_to_delete(): void
    {
        $this->artisan('accounts:delete-expired')
            ->expectsOutputToContain('Hard-deleted 0 expired account')
            ->assertSuccessful();
    }

    public function test_command_skips_active_users_without_schedule(): void
    {
        $user = User::factory()->createOne(['scheduled_hard_delete_at' => null]);

        $this->artisan('accounts:delete-expired')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
