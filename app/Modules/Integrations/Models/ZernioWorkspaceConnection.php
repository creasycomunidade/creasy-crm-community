<?php

namespace App\Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZernioWorkspaceConnection extends Model
{
    protected $fillable = ['workspace_id', 'profile_id', 'profile_name', 'status', 'last_synced_at', 'last_error', 'settings'];

    protected function casts(): array
    {
        return ['last_synced_at' => 'datetime', 'settings' => 'array'];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ZernioAccountMapping::class, 'connection_id');
    }
}
