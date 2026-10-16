<?php

namespace App\Modules\Appointments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AppointmentService extends Model
{
    protected $fillable = ['uuid', 'workspace_id', 'name', 'description', 'duration_minutes', 'buffer_minutes', 'price', 'currency', 'color', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'price' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentResource::class, 'appointment_service_resource', 'service_id', 'resource_id')->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }
}
