<?php

namespace Tests\Feature\Auth;

use App\Mail\VerificationMail;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use DatabaseTransactions;

    // === Validate Invitation Token ===

    public function test_validate_token_returns_user_data_for_valid_token(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $response = $this->getJson("/api/auth/invitation/{$entry->invitation_token}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['email', 'name']])
            ->assertJsonPath('data.email', $entry->email);
    }

    public function test_validate_token_returns_404_for_invalid_token(): void
    {
        $response = $this->getJson('/api/auth/invitation/invalid-token');

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'INVALID_TOKEN');
    }

    public function test_validate_token_returns_404_for_expired_invitation(): void
    {
        $entry = WaitlistEntry::factory()->expiredInvitation()->createOne();

        $response = $this->getJson("/api/auth/invitation/{$entry->invitation_token}");

        $response->assertNotFound();
    }

    // === Registration ===

    public function test_register_creates_user_with_valid_data(): void
    {
        Mail::fake();
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $response = $this->postJson('/api/auth/register', [
            'token' => $entry->invitation_token,
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'privacy_accepted' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.message', 'Registro exitoso. Revisa tu email para verificar tu cuenta.');

        $this->assertDatabaseHas('users', [
            'email' => $entry->email,
            'name' => 'Test User',
        ]);

        $entry->refresh();
        $this->assertEquals('registered', $entry->status);

        Mail::assertQueued(VerificationMail::class);
    }

    public function test_register_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'token' => 'invalid-token',
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'privacy_accepted' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REGISTRATION_FAILED');
    }

    public function test_register_fails_with_expired_token(): void
    {
        $entry = WaitlistEntry::factory()->expiredInvitation()->createOne();

        $response = $this->postJson('/api/auth/register', [
            'token' => $entry->invitation_token,
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'privacy_accepted' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_if_email_already_registered(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();
        User::factory()->createOne(['email' => $entry->email]);

        $response = $this->postJson('/api/auth/register', [
            'token' => $entry->invitation_token,
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'privacy_accepted' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_without_privacy_acceptance(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $response = $this->postJson('/api/auth/register', [
            'token' => $entry->invitation_token,
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);

        $response->assertUnprocessable();
    }

    public function test_register_fails_with_weak_password(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $response = $this->postJson('/api/auth/register', [
            'token' => $entry->invitation_token,
            'name' => 'Test User',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'privacy_accepted' => true,
        ]);

        $response->assertUnprocessable();
    }

    public function test_register_fails_with_password_no_uppercase(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $response = $this->postJson('/api/auth/register', [
            'token' => $entry->invitation_token,
            'name' => 'Test User',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'privacy_accepted' => true,
        ]);

        $response->assertUnprocessable();
    }

    public function test_register_fails_with_password_no_number(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $response = $this->postJson('/api/auth/register', [
            'token' => $entry->invitation_token,
            'name' => 'Test User',
            'password' => 'Passwordd',
            'password_confirmation' => 'Passwordd',
            'privacy_accepted' => true,
        ]);

        $response->assertUnprocessable();
    }

    public function test_register_fails_with_mismatched_passwords(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $response = $this->postJson('/api/auth/register', [
            'token' => $entry->invitation_token,
            'name' => 'Test User',
            'password' => 'Password1',
            'password_confirmation' => 'Different1',
            'privacy_accepted' => true,
        ]);

        $response->assertUnprocessable();
    }

    // === Email Verification ===

    public function test_verify_email_activates_account(): void
    {
        $user = User::factory()->unverified()->createOne();

        $url = URL::temporarySignedRoute(
            'auth.verify-email',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Email verificado correctamente. Ya puedes iniciar sesión.');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_email_fails_with_invalid_signature(): void
    {
        $user = User::factory()->unverified()->createOne();

        $response = $this->getJson("/api/auth/verify-email/{$user->id}/" . sha1($user->email));

        $response->assertForbidden();
    }

    public function test_verify_email_fails_with_wrong_hash(): void
    {
        $user = User::factory()->unverified()->createOne();

        $url = URL::temporarySignedRoute(
            'auth.verify-email',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => 'wrong-hash']
        );

        $response = $this->getJson($url);

        $response->assertStatus(422);
    }

    public function test_verify_email_is_idempotent(): void
    {
        $user = User::factory()->createOne(['email_verified_at' => now()]);

        $url = URL::temporarySignedRoute(
            'auth.verify-email',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->getJson($url);

        $response->assertOk();
    }
}
