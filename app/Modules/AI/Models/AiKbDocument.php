<?php

namespace App\Modules\AI\Models;

use App\Modules\Integrations\Models\WorkspaceIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiKbDocument extends Model
{
    protected $table = 'ai_kb_documents';

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

    protected $fillable = [
        'kb_id',
        'workspace_integration_id',
        'source_type',
        'external_provider',
        'source_ref',
        'content_override',
        'content_override_updated_at',
        'source_metadata',
        'source_version',
        'sync_enabled',
        'title',
        'status',
        'tokens',
        'last_indexed_at',
        'last_synced_at',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'source_metadata' => 'array',
            'content_override_updated_at' => 'datetime',
            'sync_enabled' => 'boolean',
            'last_indexed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'tokens' => 'integer',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeBase::class, 'kb_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiKbChunk::class, 'document_id');
    }

    public function workspaceIntegration(): BelongsTo
    {
        return $this->belongsTo(WorkspaceIntegration::class, 'workspace_integration_id');
    }
}
