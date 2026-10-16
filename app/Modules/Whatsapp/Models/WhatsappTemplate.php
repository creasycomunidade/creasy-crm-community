<?php

namespace App\Modules\Whatsapp\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'workspace_id', 'waba_id', 'name', 'language', 'category',
        'status', 'components', 'rejection_reason', 'meta_template_id',
    ];

    protected function casts(): array
    {
        return ['components' => 'array'];
    }
}
