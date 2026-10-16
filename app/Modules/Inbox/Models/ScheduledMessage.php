<?php

namespace App\Modules\Inbox\Models;

use App\Models\User;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ScheduledMessage extends Model
{
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'workspace_id', 'conversation_id', 'created_by', 'body', 'timezone',
        'media_path', 'media_url', 'media_mime', 'media_name',
        'next_send_at', 'last_sent_at', 'recurrence_unit', 'recurrence_interval',
        'max_occurrences', 'occurrences_sent', 'ends_at', 'status',
        'failure_count', 'last_error', 'processing_at',
    ];

    protected function casts(): array
    {
        return [
            'next_send_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'ends_at' => 'datetime',
            'processing_at' => 'datetime',
            'recurrence_interval' => 'integer',
            'max_occurrences' => 'integer',
            'occurrences_sent' => 'integer',
            'failure_count' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nextOccurrence(): ?Carbon
    {
        if ($this->recurrence_unit === 'once') {
            return null;
        }

        $next = $this->next_send_at->copy()->setTimezone($this->timezone);
        $amount = max(1, (int) $this->recurrence_interval);
        do {
            $next = match ($this->recurrence_unit) {
                'minute' => $next->addMinutes($amount),
                'hour' => $next->addHours($amount),
                'day' => $next->addDays($amount),
                'week' => $next->addWeeks($amount),
                'month' => $next->addMonthsNoOverflow($amount),
            };
        } while ($next->lte(now($this->timezone)));

        if ($this->max_occurrences !== null && $this->occurrences_sent >= $this->max_occurrences) {
            return null;
        }

        if ($this->ends_at && $next->greaterThan($this->ends_at)) {
            return null;
        }

        return $next->setTimezone((string) config('app.timezone', 'UTC'));
    }
}
