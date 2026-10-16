<?php

namespace App\Modules\Leads\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Pipeline extends Model
{
    protected $table = 'crm_pipelines';

    protected $fillable = ['workspace_id', 'name', 'color', 'is_default', 'position'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $pipeline): void {
            $pipeline->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class, 'pipeline_id')->orderBy('position')->orderBy('id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'pipeline_id');
    }
}
