<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelAccount extends Model
{
    protected $fillable = [
        'workspace_id', 'channel', 'provider', 'credentials',
        'display_name', 'phone_number_id', 'business_account_id', 'status', 'meta_json',
        'external_account_id', 'webhook_token_hash',
    ];

    protected $hidden = ['credentials', 'webhook_token_hash'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'meta_json' => 'array',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
