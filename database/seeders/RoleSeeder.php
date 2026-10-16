<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::firstOrCreate(
            ['key' => Role::KEY_SUPER_ADMIN],
            [
                'name' => 'Super Admin',
                'description' => 'Full system access',
                'is_system' => true,
            ]
        );

        Role::firstOrCreate(
            ['key' => Role::KEY_ADMIN],
            [
                'name' => 'Admin',
                'description' => 'Administrative access',
                'is_system' => false,
            ]
        );

        Role::firstOrCreate(
            ['key' => Role::KEY_SUPPORT],
            [
                'name' => 'Support',
                'description' => 'View clients and support requests',
                'is_system' => false,
            ]
        );

        // Assign ALL permissions to Super Admin
        $allPermissionIds = Permission::pluck('id')->all();
        $superAdmin->permissions()->sync($allPermissionIds);
    }
}
