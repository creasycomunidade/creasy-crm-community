<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['name', 'key', 'description', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public const KEY_SUPER_ADMIN = 'SUPER_ADMIN';
    public const KEY_ADMIN = 'ADMIN';
    public const KEY_SUPPORT = 'SUPPORT';

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function adminUsers(): BelongsToMany
    {
        return $this->belongsToMany(AdminUser::class, 'admin_role');
    }
}
