<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Segment extends Model
{
    protected $fillable = ['workspace_id', 'name', 'type', 'rules_json', 'contact_count'];

    protected function casts(): array
    {
        return [
            'rules_json' => 'array',
            'contact_count' => 'integer',
        ];
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'segment_contact');
    }
}
