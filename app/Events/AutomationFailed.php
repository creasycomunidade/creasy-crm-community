<?php

namespace App\Events;

use App\Modules\Automation\Models\AutomationRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AutomationFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AutomationRun $run,
        public readonly string $errorMessage,
    ) {}

    public function broadcastOn(): array
    {
        $wsId = $this->run->automation->workspace_id ?? null;

        if (! $wsId) {
            return [];
        }

        return [new PrivateChannel("workspace.{$wsId}")];
    }

    public function broadcastAs(): string
    {
        return 'AutomationFailed';
    }

    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->run->id,
            'automation' => $this->run->automation?->name,
            'error' => $this->errorMessage,
        ];
    }
}
