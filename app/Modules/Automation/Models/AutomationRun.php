<?php

namespace App\Modules\Automation\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    protected $table = 'automation_runs';

    protected $fillable = ['automation_id', 'contact_id', 'status', 'context', 'current_node_id', 'resume_node_id', 'error', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function automation()
    {
        return $this->belongsTo(Automation::class);
    }

    public function logs()
    {
        return $this->hasMany(AutomationRunLog::class, 'run_id');
    }
}
