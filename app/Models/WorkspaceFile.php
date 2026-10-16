<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkspaceFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id', 'created_by', 'folder_id', 'name', 'extension', 'mime_type',
        'size', 'storage_path', 'content', 'source',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $file): void {
            $file->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isEditable(): bool
    {
        return in_array(strtolower((string) $this->extension), ['md', 'markdown', 'txt', 'html', 'htm', 'css', 'js', 'json', 'csv', 'xml', 'yaml', 'yml'], true);
    }

    public function folder()
    {
        return $this->belongsTo(WorkspaceFolder::class, 'folder_id');
    }

    public function shares()
    {
        return $this->hasMany(WorkspaceFileShare::class, 'workspace_file_id');
    }
}
