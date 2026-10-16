<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_admin_id',
        'user_id',
        'client_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'meta',
        'ip',
        'user_agent',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function actorAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'actor_admin_id');
    }

    public function auditable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
