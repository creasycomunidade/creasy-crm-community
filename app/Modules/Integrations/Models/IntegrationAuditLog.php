<?php

namespace App\Modules\Integrations\Models;

use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'integration_config_id',
        'provider',
        'action',
        'diff_json',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'diff_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (is_null($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(IntegrationConfig::class, 'integration_config_id');
    }
}
