<?php

namespace App\Modules\Appointments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentAvailability extends Model
{
    protected $fillable = ['workspace_id', 'resource_id', 'weekday', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(AppointmentResource::class, 'resource_id');
    }
}
