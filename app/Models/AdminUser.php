<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'admin_users';

    protected $fillable = ['name', 'email', 'password', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_role');
    }

    /** Permission keys this admin has (via all roles). */
    public function permissionKeys(): array
    {
        $keys = $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('key'))
            ->unique()
            ->values()
            ->all();

        return array_values($keys);
    }

    public function hasPermissionTo(string $permissionKey): bool
    {
        return in_array($permissionKey, $this->permissionKeys(), true);
    }

    public function hasAnyPermission(array $permissionKeys): bool
    {
        $mine = $this->permissionKeys();
        foreach ($permissionKeys as $key) {
            if (in_array($key, $mine, true)) {
                return true;
            }
        }
        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('key', Role::KEY_SUPER_ADMIN)->exists();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
