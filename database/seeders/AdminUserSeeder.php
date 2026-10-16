<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL', 'admin@example.com');
        $password = env('ADMIN_SEED_PASSWORD') ?: Str::password(16);

        $adminExisted = AdminUser::where('email', $email)->exists();

        $admin = AdminUser::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_SEED_NAME', 'Super Admin'),
                'password' => Hash::make($password),
                'status' => AdminUser::STATUS_ACTIVE,
            ]
        );

        if (! $adminExisted && ! env('ADMIN_SEED_PASSWORD')) {
            $this->command?->warn("Super admin created: {$email} / {$password}");
            $this->command?->warn('Save this password now — it will not be shown again. Set ADMIN_SEED_PASSWORD to choose your own.');
        }

        $superAdminRole = Role::where('key', Role::KEY_SUPER_ADMIN)->first();
        if ($superAdminRole && ! $admin->roles()->where('roles.id', $superAdminRole->id)->exists()) {
            $admin->roles()->attach($superAdminRole->id);
        }
    }
}
