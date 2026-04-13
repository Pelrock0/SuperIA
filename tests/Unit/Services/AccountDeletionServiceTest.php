<?php

namespace Tests\Unit\Services;

use App\Mail\AccountDeletionMail;
use App\Models\AccountDeletionLog;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AccountDeletionService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountDeletionService();
    }

    public function test_initiate_delete_soft_deletes_user(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1']);

        $this->service->initiateDelete($user, 'Password1');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_initiate_delete_sets_hard_delete_date(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1']);

        $this->service->initiateDelete($user, 'Password1');

        $user->refresh();
        $this->assertNotNull($user->scheduled_hard_delete_at);
        $this->assertTrue($user->scheduled_hard_delete_at->isFuture());
    }

    public function test_initiate_delete_creates_audit_log(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1']);

        $this->service->initiateDelete($user, 'Password1');

        $this->assertDatabaseCount('account_deletion_logs', 1);
        $log = AccountDeletionLog::first();
        $this->assertEquals(hash('sha256', (string) $user->id), $log->hashed_user_id);
        $this->assertEquals('user_request', $log->reason);
    }

    public function test_initiate_delete_increments_jwt_version(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1', 'jwt_version' => 0]);

        $this->service->initiateDelete($user, 'Password1');

        $user->refresh();
        $this->assertEquals(1, $user->jwt_version);
    }

    public function test_initiate_delete_sends_email(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1']);

        $this->service->initiateDelete($user, 'Password1');

        Mail::assertQueued(AccountDeletionMail::class);
    }

    public function test_initiate_delete_throws_for_wrong_password(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contrasena incorrecta.');

        $this->service->initiateDelete($user, 'WrongPassword');
    }

    public function test_hard_delete_expired_accounts_removes_users(): void
    {
        $user = User::factory()->createOne([
            'scheduled_hard_delete_at' => now()->subDay(),
        ]);
        $user->delete(); // soft delete

        $count = $this->service->hardDeleteExpiredAccounts();

        $this->assertEquals(1, $count);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_hard_delete_skips_non_expired_accounts(): void
    {
        $user = User::factory()->createOne([
            'scheduled_hard_delete_at' => now()->addDays(29),
        ]);
        $user->delete();

        $count = $this->service->hardDeleteExpiredAccounts();

        $this->assertEquals(0, $count);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_hard_delete_skips_users_without_schedule(): void
    {
        $user = User::factory()->createOne();
        $user->delete();

        $count = $this->service->hardDeleteExpiredAccounts();

        $this->assertEquals(0, $count);
    }
}
