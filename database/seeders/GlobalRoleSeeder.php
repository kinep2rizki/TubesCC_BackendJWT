<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class GlobalRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create global roles
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'api']);
    }
}
