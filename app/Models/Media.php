<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size_bytes',
        'collection',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'size_bytes' => 'integer',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        $this->ensureDiskConfigured();

        return Storage::disk($this->disk)->url($this->path);
    }

    public function delete(): bool
    {
        $this->ensureDiskConfigured();
        Storage::disk($this->disk)->delete($this->path);

        return parent::delete();
    }

    private function ensureDiskConfigured(): void
    {
        app(\App\Services\StorageManager::class)->ensureDiskReady($this->disk);
    }
}
