<?php

namespace App\Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

class ZernioAccountMapping extends Model
{
    protected $fillable = ['workspace_id', 'connection_id', 'profile_id', 'account_id', 'platform', 'username', 'display_name', 'status', 'capabilities', 'metadata'];

    protected function casts(): array
    {
        return ['capabilities' => 'array', 'metadata' => 'array'];
    }
}
