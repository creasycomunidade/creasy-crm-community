<?php

namespace App\Modules\Leads\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    protected $fillable = ['task_id', 'title', 'completed', 'position'];

    protected function casts(): array
    {
        return ['completed' => 'boolean'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TaskCard::class, 'task_id');
    }
}
