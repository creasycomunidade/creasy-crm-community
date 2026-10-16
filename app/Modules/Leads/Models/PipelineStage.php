<?php

namespace App\Modules\Leads\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PipelineStage extends Model
{
    protected $table = 'crm_pipeline_stages';

    protected $fillable = ['workspace_id', 'pipeline_id', 'name', 'color', 'conversion_event', 'position'];

    protected static function booted(): void
    {
        static::creating(function (self $stage): void {
            $stage->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'stage_id')->orderBy('position')->orderBy('id');
    }
}
