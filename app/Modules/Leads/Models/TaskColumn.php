<?php

namespace App\Modules\Leads\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TaskColumn extends Model
{
    protected $fillable = ['workspace_id', 'board_id', 'name', 'color', 'position'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TaskCard::class, 'column_id')->orderBy('position')->orderBy('id');
    }
}
