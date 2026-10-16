<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Locale extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'native_name', 'flag', 'enabled', 'is_default', 'is_rtl', 'sort_order'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'is_rtl' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getNativeNameAttribute($value): string
    {
        return $value ?? $this->name ?? $this->code;
    }

    /** Scope: only enabled locales. */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /** Get the default locale code from DB. */
    public static function defaultCode(): string
    {
        $locale = static::where('is_default', true)->where('enabled', true)->first();

        return $locale ? $locale->code : 'pt';
    }

    /** Get enabled locales ordered for switcher. */
    public static function forSwitcher(): \Illuminate\Database\Eloquent\Collection
    {
        return static::enabled()->orderByRaw('is_default DESC')->orderBy('sort_order')->orderBy('code')->get();
    }
}
