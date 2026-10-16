<?php

namespace App\Modules\AI\Models;

use Database\Factories\AiChatbotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiChatbot extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return AiChatbotFactory::new();
    }

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

    protected $table = 'ai_chatbots';

    protected $fillable = ['workspace_id', 'name', 'ai_kb_id', 'system_prompt', 'tone', 'max_context_chunks', 'fallback_reply', 'channels', 'enabled'];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'enabled' => 'boolean',
            'max_context_chunks' => 'integer',
        ];
    }

    public function knowledgeBase()
    {
        return $this->belongsTo(AiKnowledgeBase::class, 'ai_kb_id');
    }

    public function runs()
    {
        return $this->hasMany(AiRun::class, 'chatbot_id');
    }
}
