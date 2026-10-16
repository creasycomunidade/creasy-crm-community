<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    use HasFactory;

    protected $table = 'cms_pages';

    protected $fillable = [
        'slug',
        'title',
        'content',
        'meta_title',
        'meta_description',
        'published',
        'layout',
    ];

    protected $casts = [
        'published' => 'boolean',
    ];

    public function getMetaTitleAttribute($value): string
    {
        return $value ?: $this->title;
    }
}
