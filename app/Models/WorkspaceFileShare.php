<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkspaceFileShare extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id', 'workspace_file_id', 'created_by', 'expires_at',
        'revoked_at', 'views', 'last_viewed_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $share): void {
            $share->uuid ??= (string) Str::uuid();
            $share->token ??= Str::random(48);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function file()
    {
        return $this->belongsTo(WorkspaceFile::class, 'workspace_file_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAvailable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->file !== null;
    }
}
