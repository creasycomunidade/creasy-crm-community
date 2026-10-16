<?php

namespace App\Modules\Shared\Models;

use App\Support\Concerns\MasksDemoData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use MasksDemoData;

    protected $fillable = [
        'conversation_id', 'direction', 'channel', 'type', 'payload', 'body',
        'media_id', 'status', 'provider_message_id', 'error_json',
        'sent_by', 'user_id', 'sent_at',
    ];

    /**
     * Scrub emails / phone numbers embedded in message text in demo mode. The
     * structured payload is left intact so interactive messages still render.
     *
     * @return array<string, string>
     */
    protected function demoMask(): array
    {
        return ['body' => 'text'];
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'error_json' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
