<?php

namespace App\Modules\Leads\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TaskBoard extends Model
{
    protected $fillable = ['uuid', 'workspace_id', 'name', 'is_default', 'position'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function columns(): HasMany
    {
        return $this->hasMany(TaskColumn::class, 'board_id')->orderBy('position')->orderBy('id');
    }
}
