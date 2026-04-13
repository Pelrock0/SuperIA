<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private function createAdmin(): User
    {
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = User::factory()->createOne();
        $admin->assignRole('superadmin');
        return $admin;
    }

    public function test_dashboard_renders_with_metrics(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, backpack_guard_name())
            ->get(backpack_url('dashboard'))
            ->assertOk()
            ->assertSee('Total Usuarios')
            ->assertSee('Listas creadas hoy')
            ->assertSee('Consumo IA');
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get(backpack_url('dashboard'))
            ->assertRedirect();
    }

    public function test_ai_usage_crud_renders(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, backpack_guard_name())
            ->get(backpack_url('ai-usage'))
            ->assertOk();
    }

    public function test_user_crud_renders(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, backpack_guard_name())
            ->get(backpack_url('user'))
            ->assertOk();
    }
}
