<?php

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderConfig extends Model
{
    protected $table = 'ai_provider_configs';

    protected $fillable = ['workspace_id', 'provider', 'credentials', 'default_model_chat', 'default_model_embed', 'enabled'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'enabled' => 'boolean'];
    }
}
