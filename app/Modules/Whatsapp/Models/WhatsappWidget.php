<?php

namespace App\Modules\Whatsapp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsappWidget extends Model
{
    protected $table = 'whatsapp_widgets';

    protected $fillable = [
        'workspace_id', 'widget_key', 'phone_number_id', 'display_phone',
        'name', 'prefilled_message', 'greeting_message', 'agent_name', 'agent_avatar_color',
        'button_color', 'position', 'allowed_domains', 'working_hours_json',
    ];

    protected function casts(): array
    {
        return [
            'allowed_domains' => 'array',
            'working_hours_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->widget_key)) {
                $model->widget_key = Str::random(32);
            }
        });
    }
}
