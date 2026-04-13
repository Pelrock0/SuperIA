<?php

namespace Tests\Feature\Auth;

use App\Mail\AccountDeletionMail;
use App\Models\AccountDeletionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AccountDeletionTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        $token = JWTAuth::fromUser($user);
        return ['Authorization' => "Bearer {$token}"];
    }

    // === Account Deletion ===

    public function test_delete_account_soft_deletes_user(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/delete-account', [
                'password' => 'Password1',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Tu cuenta ha sido eliminada. Recibiras un email de confirmacion.');

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $user->refresh();
        $this->assertNotNull($user->scheduled_hard_delete_at);
    }

    public function test_delete_account_creates_audit_log(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1']);
        $hashedId = hash('sha256', (string) $user->id);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/delete-account', [
                'password' => 'Password1',
            ]);

        $this->assertDatabaseHas('account_deletion_logs', [
            'hashed_user_id' => $hashedId,
            'reason' => 'user_request',
        ]);
    }

    public function test_delete_account_sends_confirmation_email(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/delete-account', [
                'password' => 'Password1',
            ]);

        Mail::assertQueued(AccountDeletionMail::class);
    }

    public function test_delete_account_increments_jwt_version(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1', 'jwt_version' => 0]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/delete-account', [
                'password' => 'Password1',
            ]);

        $user->refresh();
        $this->assertEquals(1, $user->jwt_version);
    }

    public function test_delete_account_fails_with_wrong_password(): void
    {
        $user = User::factory()->createOne(['password' => 'Password1']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/delete-account', [
                'password' => 'WrongPassword1',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DELETION_FAILED');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_delete_account_fails_without_auth(): void
    {
        $response = $this->postJson('/api/auth/delete-account', [
            'password' => 'Password1',
        ]);

        $response->assertUnauthorized();
    }

    public function test_delete_account_requires_password(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/delete-account', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // === Audit Log No PII ===

    public function test_audit_log_contains_no_pii(): void
    {
        Mail::fake();
        $user = User::factory()->createOne(['password' => 'Password1']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/delete-account', [
                'password' => 'Password1',
            ]);

        $log = AccountDeletionLog::first();

        $this->assertNotEquals((string) $user->id, $log->hashed_user_id);
        $this->assertEquals(hash('sha256', (string) $user->id), $log->hashed_user_id);
        $this->assertStringNotContainsString($user->email, json_encode($log->toArray()));
        $this->assertStringNotContainsString($user->name, json_encode($log->toArray()));
    }
}
