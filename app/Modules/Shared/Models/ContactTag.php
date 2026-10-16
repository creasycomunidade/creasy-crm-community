<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactTag extends Model
{
    protected $table = 'contact_tags';

    protected $fillable = ['workspace_id', 'name', 'color'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag_pivot', 'tag_id', 'contact_id');
    }
}
