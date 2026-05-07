<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckIfAdminMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private function createAdmin(): User
    {
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = User::factory()->createOne();
        $admin->assignRole('superadmin');
        return $admin;
    }

    private function createNonAdmin(): User
    {
        return User::factory()->createOne();
    }

    public function test_guest_request_is_redirected(): void
    {
        $this->get(backpack_url('dashboard'))
            ->assertRedirect();
    }

    public function test_json_guest_request_returns_401(): void
    {
        $this->getJson(backpack_url('dashboard'))
            ->assertUnauthorized();
    }

    public function test_admin_with_superadmin_role_can_access(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, backpack_guard_name())
            ->get(backpack_url('dashboard'))
            ->assertOk();
    }

    public function test_admin_with_admin_role_can_access(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->createOne();
        $admin->assignRole('admin');

        $this->actingAs($admin, backpack_guard_name())
            ->get(backpack_url('dashboard'))
            ->assertOk();
    }

    public function test_non_admin_user_is_redirected_to_root(): void
    {
        $user = $this->createNonAdmin();

        $this->actingAs($user, backpack_guard_name())
            ->get(backpack_url('dashboard'))
            ->assertRedirect('/');
    }

    public function test_non_admin_session_is_invalidated_on_access_attempt(): void
    {
        $user = $this->createNonAdmin();

        $response = $this->actingAs($user, backpack_guard_name())
            ->get(backpack_url('dashboard'));

        $response->assertRedirect('/');
        // After the middleware logs out the user, the backpack guard session is cleared
        $this->assertGuest(backpack_guard_name());
    }
}
