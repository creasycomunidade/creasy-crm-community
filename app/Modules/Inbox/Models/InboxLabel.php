<?php

namespace App\Modules\Inbox\Models;

use App\Models\Workspace;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InboxLabel extends Model
{
    protected $table = 'inbox_labels';

    protected $fillable = ['workspace_id', 'name', 'color'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'inbox_label_conversation', 'label_id', 'conversation_id');
    }
}
