<?php

namespace App\Modules\Appointments\Models;

use App\Modules\Shared\Models\Contact;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Appointment extends Model
{
    protected $fillable = ['uuid', 'reference', 'workspace_id', 'service_id', 'resource_id', 'contact_id', 'customer_name', 'customer_email', 'customer_phone', 'starts_at', 'ends_at', 'timezone', 'status', 'notes', 'cancel_reason', 'source', 'external_calendar_id', 'external_calendar_link', 'external_calendar_synced_at', 'external_calendar_error', 'customer_reminder_at', 'customer_reminder_sent_at', 'professional_reminder_at', 'professional_reminder_sent_at', 'channel_confirmation_sent_at', 'channel_reminder_sent_at', 'channel_status_sent_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'external_calendar_synced_at' => 'datetime', 'customer_reminder_at' => 'datetime', 'customer_reminder_sent_at' => 'datetime', 'professional_reminder_at' => 'datetime', 'professional_reminder_sent_at' => 'datetime', 'channel_confirmation_sent_at' => 'datetime', 'channel_reminder_sent_at' => 'datetime', 'channel_status_sent_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            $m->uuid ??= (string) Str::uuid();
            $m->reference ??= 'AG-'.strtoupper(Str::random(8));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function service()
    {
        return $this->belongsTo(AppointmentService::class, 'service_id');
    }

    public function resource()
    {
        return $this->belongsTo(AppointmentResource::class, 'resource_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
