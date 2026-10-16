<?php

namespace App\Modules\Broadcasting\Models;

use Illuminate\Database\Eloquent\Model;

class SmsProviderConfig extends Model
{
    protected $table = 'sms_provider_configs';

    protected $fillable = [
        'workspace_id', 'provider', 'credentials', 'sender_id', 'default',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'default' => 'boolean',
        ];
    }
}
