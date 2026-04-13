<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperadminRoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    }
}
