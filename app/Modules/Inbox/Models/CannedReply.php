<?php

namespace App\Modules\Inbox\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CannedReply extends Model
{
    protected $table = 'inbox_canned_replies';

    protected $fillable = ['workspace_id', 'shortcut', 'body'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
