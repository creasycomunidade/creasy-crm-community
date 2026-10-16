<?php

namespace App\Modules\Appointments\Models;

use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AppointmentSetting extends Model
{
    protected $fillable = [
        'workspace_id', 'public_token', 'channel_account_id', 'timezone', 'booking_horizon_days',
        'slot_interval_minutes', 'send_confirmation', 'send_reminders', 'send_status_updates',
        'confirmation_message', 'reminder_message', 'cancellation_message', 'reschedule_message',
        'google_calendar_enabled', 'google_calendar_invite_customer', 'google_calendar_id',
    ];

    protected function casts(): array
    {
        return [
            'send_confirmation' => 'boolean',
            'send_reminders' => 'boolean',
            'send_status_updates' => 'boolean',
            'booking_horizon_days' => 'integer',
            'slot_interval_minutes' => 'integer',
            'google_calendar_enabled' => 'boolean',
            'google_calendar_invite_customer' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $setting) => $setting->public_token ??= Str::random(48));
    }

    public function channelAccount(): BelongsTo
    {
        return $this->belongsTo(ChannelAccount::class);
    }

    public static function forWorkspace(int $workspaceId): self
    {
        return static::firstOrCreate(
            ['workspace_id' => $workspaceId],
            [
                'timezone' => 'America/Sao_Paulo',
                'booking_horizon_days' => 90,
                'slot_interval_minutes' => 30,
            ],
        );
    }
}
