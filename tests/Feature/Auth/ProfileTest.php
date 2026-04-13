<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileTest extends TestCase
{
    use DatabaseTransactions;

    private function authHeaders(User $user): array
    {
        $token = JWTAuth::fromUser($user);
        return ['Authorization' => "Bearer {$token}"];
    }

    // === Show Profile ===

    public function test_show_profile_returns_user_data(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'email_verified_at', 'created_at']])
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_show_profile_fails_without_auth(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertUnauthorized();
    }

    // === Update Profile ===

    public function test_update_profile_changes_name(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/profile', ['name' => 'Updated Name']);

        $response->assertOk()
            ->assertJsonPath('data.user.name', 'Updated Name');

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
    }

    public function test_update_profile_requires_name(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/profile', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_profile_rejects_empty_name(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/profile', ['name' => '']);

        $response->assertUnprocessable();
    }

    public function test_update_profile_rejects_name_over_255_chars(): void
    {
        $user = User::factory()->createOne();

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/profile', ['name' => str_repeat('a', 256)]);

        $response->assertUnprocessable();
    }

    public function test_update_profile_fails_without_auth(): void
    {
        $response = $this->putJson('/api/profile', ['name' => 'Name']);

        $response->assertUnauthorized();
    }

    // === Change Password ===

    public function test_change_password_with_correct_current_password(): void
    {
        $user = User::factory()->createOne(['password' => 'OldPassword1']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/profile/password', [
                'current_password' => 'OldPassword1',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Contrasena actualizada correctamente.');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1', $user->password));
    }

    public function test_change_password_increments_jwt_version(): void
    {
        $user = User::factory()->createOne(['password' => 'OldPassword1', 'jwt_version' => 0]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/profile/password', [
                'current_password' => 'OldPassword1',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $user->refresh();
        $this->assertEquals(1, $user->jwt_version);
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->createOne(['password' => 'OldPassword1']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/profile/password', [
                'current_password' => 'WrongPassword1',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PASSWORD');
    }

    public function test_change_password_fails_with_weak_new_password(): void
    {
        $user = User::factory()->createOne(['password' => 'OldPassword1']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/profile/password', [
                'current_password' => 'OldPassword1',
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ]);

        $response->assertUnprocessable();
    }

    public function test_change_password_fails_without_auth(): void
    {
        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'OldPassword1',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertUnauthorized();
    }
}
