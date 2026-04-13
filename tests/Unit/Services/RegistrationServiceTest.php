<?php

namespace Tests\Unit\Services;

use App\Mail\VerificationMail;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\RegistrationService;
use App\Services\WaitlistService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RegistrationService(new WaitlistService());
    }

    public function test_validate_invitation_token_returns_entry_for_valid_token(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $result = $this->service->validateInvitationToken($entry->invitation_token);

        $this->assertNotNull($result);
        $this->assertEquals($entry->email, $result->email);
    }

    public function test_validate_invitation_token_returns_null_for_invalid_token(): void
    {
        $result = $this->service->validateInvitationToken('invalid-token');

        $this->assertNull($result);
    }

    public function test_validate_invitation_token_returns_null_for_expired_token(): void
    {
        $entry = WaitlistEntry::factory()->expiredInvitation()->createOne();

        $result = $this->service->validateInvitationToken($entry->invitation_token);

        $this->assertNull($result);
    }

    public function test_register_creates_user_and_updates_waitlist(): void
    {
        Mail::fake();
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $user = $this->service->register($entry->invitation_token, 'Test User', 'Password1');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($entry->email, $user->email);
        $this->assertEquals('Test User', $user->name);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->privacy_accepted_at);

        $entry->refresh();
        $this->assertEquals('registered', $entry->status);
    }

    public function test_register_sends_verification_email(): void
    {
        Mail::fake();
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $this->service->register($entry->invitation_token, 'Test User', 'Password1');

        Mail::assertQueued(VerificationMail::class);
    }

    public function test_register_throws_for_invalid_token(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Token de invitacion invalido o expirado.');

        $this->service->register('invalid-token', 'Test User', 'Password1');
    }

    public function test_register_throws_for_duplicate_email(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();
        User::factory()->createOne(['email' => $entry->email]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Este email ya esta registrado.');

        $this->service->register($entry->invitation_token, 'Test User', 'Password1');
    }

    public function test_verify_email_sets_verified_timestamp(): void
    {
        $user = User::factory()->unverified()->createOne();

        $result = $this->service->verifyEmail($user->id, sha1($user->email));

        $this->assertNotNull($result->email_verified_at);
    }

    public function test_verify_email_throws_for_wrong_hash(): void
    {
        $user = User::factory()->unverified()->createOne();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->verifyEmail($user->id, 'wrong-hash');
    }

    public function test_verify_email_is_idempotent(): void
    {
        $user = User::factory()->createOne(['email_verified_at' => now()]);
        $original = $user->email_verified_at;

        $result = $this->service->verifyEmail($user->id, sha1($user->email));

        $this->assertEquals($original->timestamp, $result->email_verified_at->timestamp);
    }
}
