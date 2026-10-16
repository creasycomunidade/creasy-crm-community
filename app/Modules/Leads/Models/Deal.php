<?php

namespace App\Modules\Leads\Models;

use App\Models\User;
use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Deal extends Model
{
    protected $table = 'crm_deals';

    protected $fillable = [
        'workspace_id', 'pipeline_id', 'stage_id', 'contact_id',
        'assigned_user_id', 'title', 'value_cents', 'currency', 'notes', 'position',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $deal): void {
            $deal->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }
}
