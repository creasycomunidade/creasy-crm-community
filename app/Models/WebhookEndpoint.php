<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'url',
        'secret',
        'events',
        'enabled',
        'description',
    ];

    protected $casts = [
        'events' => 'array',
        'enabled' => 'boolean',
    ];

    protected $hidden = ['secret'];

    public static function generateSecret(): string
    {
        return 'whsec_' . Str::random(48);
    }

    public function signature(string $payload): string
    {
        $timestamp = now()->timestamp;
        $body = "{$timestamp}.{$payload}";

        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $body, $this->secret);
    }

    public function listensTo(string $event): bool
    {
        if (empty($this->events)) {
            return true; // subscribed to all events
        }

        return in_array($event, $this->events);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
