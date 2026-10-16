<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public static function permissionSet(): array
    {
        return [
            // Admin Management
            ['key' => 'view_admins', 'name' => 'View Admins', 'category' => 'Admin Management', 'description' => 'View admin users list and details'],
            ['key' => 'create_admins', 'name' => 'Create Admins', 'category' => 'Admin Management', 'description' => 'Create new admin users'],
            ['key' => 'update_admins', 'name' => 'Update Admins', 'category' => 'Admin Management', 'description' => 'Edit admin users'],
            ['key' => 'delete_admins', 'name' => 'Delete Admins', 'category' => 'Admin Management', 'description' => 'Delete admin users'],
            ['key' => 'view_admin_roles', 'name' => 'View Admin Roles', 'category' => 'Admin Management', 'description' => 'View roles and permissions'],
            ['key' => 'manage_admin_roles', 'name' => 'Manage Admin Roles', 'category' => 'Admin Management', 'description' => 'Create, update, delete roles and permissions'],

            // Clients
            ['key' => 'view_clients', 'name' => 'View Clients', 'category' => 'Clients', 'description' => 'View clients list and details'],
            ['key' => 'create_clients', 'name' => 'Create Clients', 'category' => 'Clients', 'description' => 'Create clients'],
            ['key' => 'update_clients', 'name' => 'Update Clients', 'category' => 'Clients', 'description' => 'Edit clients'],
            ['key' => 'delete_clients', 'name' => 'Delete Clients', 'category' => 'Clients', 'description' => 'Delete clients'],

            // Subscriptions

            // Plans

            // Payment Gateways

            // Email
            ['key' => 'view_email_settings', 'name' => 'View Email Settings', 'category' => 'Email', 'description' => 'View email settings'],
            ['key' => 'manage_email_settings', 'name' => 'Manage Email Settings', 'category' => 'Email', 'description' => 'Manage email settings'],

            // Currencies
            ['key' => 'view_currencies', 'name' => 'View Currencies', 'category' => 'Currencies', 'description' => 'View currencies'],
            ['key' => 'manage_currencies', 'name' => 'Manage Currencies', 'category' => 'Currencies', 'description' => 'Manage currencies'],

            // Languages
            ['key' => 'view_languages', 'name' => 'View Languages', 'category' => 'Languages', 'description' => 'View languages/locales'],
            ['key' => 'manage_languages', 'name' => 'Manage Languages', 'category' => 'Languages', 'description' => 'Manage languages/locales'],

            // Settings
            ['key' => 'view_settings', 'name' => 'View Settings', 'category' => 'Settings', 'description' => 'View system settings'],
            ['key' => 'manage_settings', 'name' => 'Manage Settings', 'category' => 'Settings', 'description' => 'Manage system settings'],

            // Integrations (third-party credential management)
            ['key' => 'view_integrations',   'name' => 'View Integrations',   'category' => 'Integrations', 'description' => 'View integration configurations'],
            ['key' => 'manage_integrations',  'name' => 'Manage Integrations', 'category' => 'Integrations', 'description' => 'Create, update and test third-party integration credentials'],
        ];
    }

    public function run(): void
    {
        foreach (self::permissionSet() as $row) {
            Permission::firstOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }
}
