<?php

namespace App\Events;

use App\Modules\Broadcasting\Models\Campaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Campaign $campaign) {}

    public function broadcastOn(): array
    {
        $wsId = $this->campaign->workspace_id;

        return [new PrivateChannel("workspace.{$wsId}")];
    }

    public function broadcastAs(): string
    {
        return 'CampaignCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->campaign->id,
            'name' => $this->campaign->name,
            'status' => $this->campaign->status,
            'sent_count' => $this->campaign->sent_count ?? 0,
            'failed' => $this->campaign->failed_count ?? 0,
        ];
    }
}
