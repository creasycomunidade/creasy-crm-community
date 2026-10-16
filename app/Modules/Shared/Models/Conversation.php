<?php

namespace App\Modules\Shared\Models;

use App\Models\InternalNote;
use App\Models\User;
use App\Modules\Inbox\Models\InboxLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
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
        'workspace_id', 'channel_account_id', 'contact_id', 'external_thread_id',
        'status', 'assigned_user_id', 'assigned_to', 'handover_at',
        'last_message_at', 'unread_count',
        'first_response_at', 'resolved_at', 'last_inbound_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'handover_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(InternalNote::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(
            InboxLabel::class,
            'inbox_label_conversation',
            'conversation_id',
            'label_id'
        );
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Whether the WhatsApp customer-service window allows free-form (session) messages.
     *
     * Meta only allows non-template outbound content while a user-initiated
     * conversation is within the rolling ~24h window from the contact's last
     * **inbound** message. Sending a template (including campaigns) does not
     * open this window until the contact sends a message (including tapping a
     * template button).
     *
     * Inbounds are scoped by workspace + contact across all WhatsApp threads so
     * a campaign-mirrored conversation still reflects replies if webhooks
     * attached to a different row (e.g. mismatched channel_account_id).
     */
    public function isWhatsappWindowOpen(): bool
    {
        if ($this->channelAccount?->channel !== 'whatsapp'
            || in_array($this->channelAccount?->provider, ['evolution_go', 'waha', 'evolution_api'], true)) {
            return true;
        }

        $latestInbound = Message::query()
            ->where('direction', 'in')
            ->where('channel', 'whatsapp')
            ->whereHas('conversation', function ($q) {
                $q->where('workspace_id', $this->workspace_id)
                    ->where('contact_id', $this->contact_id);
            })
            ->latest('sent_at')
            ->value('sent_at');

        return (bool) $latestInbound && now()->diffInHours($latestInbound) < 24;
    }
}
