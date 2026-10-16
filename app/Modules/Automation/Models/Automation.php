<?php

namespace App\Modules\Automation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Automation extends Model
{
    protected $table = 'automations';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = ['workspace_id', 'name', 'status', 'trigger_type', 'trigger_config', 'trigger_token', 'nodes', 'edges', 'run_count'];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'nodes' => 'array',
            'edges' => 'array',
        ];
    }

    public function runs()
    {
        return $this->hasMany(AutomationRun::class, 'automation_id');
    }

    public function isActive()
    {
        return $this->status === 'active';
    }
}
