<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkspaceFolder extends Model
{
    use SoftDeletes;

    protected $fillable = ['workspace_id', 'parent_id', 'created_by', 'name'];

    protected static function booted(): void
    {
        static::creating(function (self $folder): void {
            $folder->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function mindMaps()
    {
        return $this->hasMany(\App\Modules\AI\Models\AiMindMap::class, 'workspace_folder_id');
    }

    public function files()
    {
        return $this->hasMany(WorkspaceFile::class, 'folder_id');
    }
}
