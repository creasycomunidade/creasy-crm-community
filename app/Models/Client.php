<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'status',
        'base_currency',
        'currency_symbol',
        'currency_position',
        'logo_path',
        'logo_disk',
        'primary_color',
        'tagline',
        'custom_domain',
        'support_email',
    ];

    public function logoUrl(): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }

        $disk = $this->logo_disk ?? 'public';

        return Storage::disk($disk)->url($this->logo_path);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }




    /** Compatibility for shared integrations: Community has no commercial plan. */
    public function effectivePlan(): null
    {
        return null;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
