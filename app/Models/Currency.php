<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'symbol',
        'decimals',
        'exchange_rate',
        'is_default',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'is_default' => 'boolean',
            'enabled' => 'boolean',
            'decimals' => 'integer',
        ];
    }

    public static function defaultCode(): ?string
    {
        $default = static::where('is_default', true)->where('enabled', true)->first();

        return $default?->code;
    }
}
