<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSetting extends Model
{
    protected $table = 'client_settings';

    protected $fillable = ['client_id', 'key', 'value'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get a setting for a client, returning $default if not found.
     */
    public static function get(int $clientId, string $key, mixed $default = null): mixed
    {
        $setting = static::where('client_id', $clientId)->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Set (upsert) a setting for a client.
     */
    public static function set(int $clientId, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['client_id' => $clientId, 'key' => $key],
            ['value' => $value],
        );
    }
}
