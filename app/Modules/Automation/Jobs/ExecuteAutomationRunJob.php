<?php

namespace App\Modules\Automation\Jobs;

use App\Events\AutomationFailed;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteAutomationRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $runId) {}

    public function handle(AutomationEngine $engine): void
    {
        $run = AutomationRun::with('automation')->find($this->runId);
        if (! $run || in_array($run->status, ['cancelled', 'failed'], true)) {
            return;
        }

        try {
            $engine->executeRun($run);
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            AutomationFailed::dispatch($run, $e->getMessage());
            throw $e;
        }
    }
}
