<?php

namespace App\Modules\Automation\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRunLog extends Model
{
    protected $table = 'automation_run_logs';

    protected $fillable = ['run_id', 'node_id', 'node_type', 'result', 'message', 'output'];

    protected function casts(): array
    {
        return ['output' => 'array'];
    }

    public function run()
    {
        return $this->belongsTo(AutomationRun::class, 'run_id');
    }
}
