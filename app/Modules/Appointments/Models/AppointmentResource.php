<?php

namespace App\Modules\Appointments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AppointmentResource extends Model
{
    protected $fillable = ['uuid', 'workspace_id', 'name', 'position', 'email', 'phone', 'contact_id', 'user_id', 'timezone', 'active', 'booking_enabled'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'booking_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentService::class, 'appointment_service_resource', 'resource_id', 'service_id')->withTimestamps();
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(AppointmentAvailability::class, 'resource_id')->orderBy('weekday')->orderBy('starts_at');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'resource_id');
    }
}
