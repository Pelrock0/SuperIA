<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebauthnCredential;
use App\Services\WebauthnService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class WebauthnTest extends TestCase
{
    use DatabaseTransactions;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('webauthn.enabled', true);
        config()->set('webauthn.rp.id', 'superia.com.local');
        config()->set('webauthn.rp.name', 'Superia');
        config()->set('webauthn.origins', ['http://superia.com.local']);
    }

    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    private function makeCredential(User $user, array $overrides = []): WebauthnCredential
    {
        return WebauthnCredential::create(array_merge([
            'user_id' => $user->id,
            'credential_id' => WebauthnCredential::base64UrlEncode(random_bytes(32)),
            'public_key' => WebauthnCredential::base64UrlEncode(random_bytes(64)),
            'sign_count' => 0,
            'transports' => ['internal'],
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'attestation_type' => 'none',
            'name' => 'Test Device',
        ], $overrides));
    }

    // --- AC-11: Feature flag ---

    public function test_feature_flag_disabled_returns_404_for_all_endpoints(): void
    {
        config()->set('webauthn.enabled', false);
        $user = User::factory()->create();
        $cred = $this->makeCredential($user);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/webauthn/register/begin')
            ->assertNotFound();

        $this->postJson('/api/auth/webauthn/authenticate/begin', ['email' => $user->email])
            ->assertNotFound();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/profile/webauthn-credentials')
            ->assertNotFound();

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/profile/webauthn-credentials/{$cred->id}")
            ->assertNotFound();
    }

    // --- Authentication required ---

    public function test_begin_registration_requires_authentication(): void
    {
        $this->postJson('/api/auth/webauthn/register/begin')
            ->assertUnauthorized();
    }

    public function test_list_credentials_requires_authentication(): void
    {
        $this->getJson('/api/profile/webauthn-credentials')
            ->assertUnauthorized();
    }

    public function test_update_credential_requires_authentication(): void
    {
        $user = User::factory()->create();
        $cred = $this->makeCredential($user);

        $this->patchJson("/api/profile/webauthn-credentials/{$cred->id}", ['name' => 'Nuevo'])
            ->assertUnauthorized();
    }

    public function test_delete_credential_requires_authentication(): void
    {
        $user = User::factory()->create();
        $cred = $this->makeCredential($user);

        $this->deleteJson("/api/profile/webauthn-credentials/{$cred->id}")
            ->assertUnauthorized();
    }

    // --- Authorization: user cannot touch another user's credentials ---

    public function test_user_cannot_rename_another_users_credential(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $cred = $this->makeCredential($owner);

        $this->withHeaders($this->authHeaders($stranger))
            ->patchJson("/api/profile/webauthn-credentials/{$cred->id}", ['name' => 'Pwned'])
            ->assertForbidden();

        $this->assertEquals('Test Device', $cred->fresh()->name);
    }

    public function test_user_cannot_delete_another_users_credential(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $cred = $this->makeCredential($owner);

        $this->withHeaders($this->authHeaders($stranger))
            ->deleteJson("/api/profile/webauthn-credentials/{$cred->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('webauthn_credentials', ['id' => $cred->id]);
    }

    // --- List credentials (AC-6 multi-device display) ---

    public function test_list_credentials_returns_user_credentials_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $c1 = $this->makeCredential($user, ['name' => 'iPhone']);
        $c2 = $this->makeCredential($user, ['name' => 'Laptop']);
        $this->makeCredential($other, ['name' => 'Other Device']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/profile/webauthn-credentials');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $names = collect($data)->pluck('name')->all();
        $this->assertContains('iPhone', $names);
        $this->assertContains('Laptop', $names);
        $this->assertNotContains('Other Device', $names);
    }

    public function test_list_credentials_empty_returns_empty_array(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/profile/webauthn-credentials')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    // --- AC-7: Rename credential ---

    public function test_user_can_rename_own_credential(): void
    {
        $user = User::factory()->create();
        $cred = $this->makeCredential($user, ['name' => 'Old Name']);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/profile/webauthn-credentials/{$cred->id}", ['name' => 'Mi iPhone'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Mi iPhone');

        $this->assertEquals('Mi iPhone', $cred->fresh()->name);
    }

    public function test_rename_rejects_empty_name(): void
    {
        $user = User::factory()->create();
        $cred = $this->makeCredential($user);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/profile/webauthn-credentials/{$cred->id}", ['name' => ''])
            ->assertUnprocessable();
    }

    public function test_rename_rejects_name_over_50_chars(): void
    {
        $user = User::factory()->create();
        $cred = $this->makeCredential($user);
        $long = str_repeat('a', 51);

        $this->withHeaders($this->authHeaders($user))
            ->patchJson("/api/profile/webauthn-credentials/{$cred->id}", ['name' => $long])
            ->assertUnprocessable();
    }

    // --- AC-8: Revoke credential ---

    public function test_user_can_delete_own_credential(): void
    {
        $user = User::factory()->create();
        $cred = $this->makeCredential($user);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/profile/webauthn-credentials/{$cred->id}")
            ->assertOk();

        $this->assertDatabaseMissing('webauthn_credentials', ['id' => $cred->id]);
    }

    // --- Begin registration returns options ---

    public function test_begin_registration_returns_options(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/webauthn/register/begin');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('handle', $data);
        $this->assertArrayHasKey('options', $data);
        $this->assertEquals('superia.com.local', $data['options']['rp']['id']);
        $this->assertEquals($user->email, $data['options']['user']['name']);
        $this->assertArrayHasKey('challenge', $data['options']);
    }

    public function test_begin_registration_excludes_existing_credentials(): void
    {
        $user = User::factory()->create();
        $this->makeCredential($user);
        $this->makeCredential($user);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/webauthn/register/begin');

        $response->assertOk();
        $exclude = $response->json('data.options.excludeCredentials');
        $this->assertCount(2, $exclude);
    }

    // --- Begin authentication ---

    public function test_begin_authentication_with_email_returns_allow_credentials(): void
    {
        $user = User::factory()->create();
        $this->makeCredential($user);

        $response = $this->postJson('/api/auth/webauthn/authenticate/begin', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('handle', $data);
        $this->assertArrayHasKey('options', $data);
        $this->assertCount(1, $data['options']['allowCredentials']);
    }

    public function test_begin_authentication_without_email_returns_empty_allow_credentials(): void
    {
        User::factory()->create();

        $response = $this->postJson('/api/auth/webauthn/authenticate/begin', []);

        $response->assertOk();
        $this->assertEquals([], $response->json('data.options.allowCredentials'));
    }

    public function test_begin_authentication_unknown_email_returns_empty_allow_credentials(): void
    {
        $response = $this->postJson('/api/auth/webauthn/authenticate/begin', [
            'email' => 'doesnotexist@example.com',
        ]);

        $response->assertOk();
        $this->assertEquals([], $response->json('data.options.allowCredentials'));
    }

    public function test_begin_authentication_rejects_invalid_email_format(): void
    {
        $this->postJson('/api/auth/webauthn/authenticate/begin', [
            'email' => 'not-an-email',
        ])->assertUnprocessable();
    }

    // --- Complete endpoints validation ---

    public function test_complete_registration_requires_handle_and_credential(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/webauthn/register/complete', [])
            ->assertUnprocessable();
    }

    public function test_complete_registration_rejects_invalid_name(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/webauthn/register/complete', [
                'handle' => '00000000-0000-0000-0000-000000000000',
                'name' => str_repeat('a', 51),
                'credential' => ['foo' => 'bar'],
            ])
            ->assertUnprocessable();
    }

    public function test_complete_authentication_rejects_invalid_handle(): void
    {
        $this->postJson('/api/auth/webauthn/authenticate/complete', [
            'handle' => 'not-a-uuid',
            'credential' => ['foo' => 'bar'],
        ])->assertUnprocessable();
    }

    // --- Crypto failure paths: invalid handle / expired challenge ---

    public function test_complete_registration_with_expired_handle_fails(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/auth/webauthn/register/complete', [
                'handle' => '00000000-0000-0000-0000-000000000000',
                'name' => 'My device',
                'credential' => [
                    'id' => 'fake',
                    'type' => 'public-key',
                    'rawId' => 'fake',
                    'response' => ['clientDataJSON' => 'fake', 'attestationObject' => 'fake'],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('webauthn_credentials', 0);
    }

    public function test_complete_authentication_with_expired_handle_fails(): void
    {
        $this->postJson('/api/auth/webauthn/authenticate/complete', [
            'handle' => '00000000-0000-0000-0000-000000000000',
            'credential' => [
                'id' => 'fake',
                'type' => 'public-key',
                'rawId' => 'fake',
                'response' => [
                    'clientDataJSON' => 'fake',
                    'authenticatorData' => 'fake',
                    'signature' => 'fake',
                    'userHandle' => null,
                ],
            ],
        ])->assertStatus(401);
    }

    // --- AC-9: Password reset revokes all credentials ---

    public function test_password_reset_revokes_all_webauthn_credentials(): void
    {
        $user = User::factory()->create();
        $this->makeCredential($user, ['name' => 'iPhone']);
        $this->makeCredential($user, ['name' => 'Laptop']);
        $this->assertEquals(2, $user->webauthnCredentials()->count());

        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'password' => 'NewStrongPass1',
            'password_confirmation' => 'NewStrongPass1',
            'token' => $token,
        ])->assertOk();

        $this->assertEquals(0, $user->fresh()->webauthnCredentials()->count());
    }

    public function test_password_reset_does_not_affect_other_users_credentials(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makeCredential($user);
        $this->makeCredential($other, ['name' => 'Other']);

        $token = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'password' => 'NewStrongPass1',
            'password_confirmation' => 'NewStrongPass1',
            'token' => $token,
        ])->assertOk();

        $this->assertEquals(0, $user->fresh()->webauthnCredentials()->count());
        $this->assertEquals(1, $other->fresh()->webauthnCredentials()->count());
    }

    // --- AC-10: Password change from profile does NOT revoke credentials ---

    public function test_password_change_from_profile_does_not_revoke_credentials(): void
    {
        $user = User::factory()->create(['password' => 'CurrentPass1']);
        $this->makeCredential($user, ['name' => 'iPhone']);
        $this->makeCredential($user, ['name' => 'Laptop']);

        $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/profile/password', [
                'current_password' => 'CurrentPass1',
                'password' => 'NewStrongPass1',
                'password_confirmation' => 'NewStrongPass1',
            ])
            ->assertOk();

        $this->assertEquals(2, $user->fresh()->webauthnCredentials()->count());
    }

    // --- WebauthnService::revokeAllForUser unit-level check ---

    public function test_revoke_all_for_user_deletes_only_that_users_credentials(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->makeCredential($user);
        $this->makeCredential($user);
        $this->makeCredential($other);

        $service = app(WebauthnService::class);
        $deleted = $service->revokeAllForUser($user);

        $this->assertEquals(2, $deleted);
        $this->assertEquals(0, $user->fresh()->webauthnCredentials()->count());
        $this->assertEquals(1, $other->fresh()->webauthnCredentials()->count());
    }
}
