<?php

namespace App\Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkspaceIntegration extends Model
{
    protected $table = 'workspace_integration_connections';
    protected $fillable = ['uuid', 'workspace_id', 'provider', 'name', 'credentials', 'settings', 'status', 'last_tested_at', 'last_error'];
    protected $hidden = ['credentials'];
    protected function casts(): array { return ['credentials' => 'encrypted:array', 'settings' => 'array', 'last_tested_at' => 'datetime']; }
    protected static function booted(): void { static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid()); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
