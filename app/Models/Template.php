<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = ['name', 'slug', 'subject', 'type', 'content', 'meta', 'enabled'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'enabled' => 'boolean',
        ];
    }
}
