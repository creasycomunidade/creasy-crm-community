<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totals = $this->totals_json ?? [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel,
            'channel_account_id' => $this->channel_account_id,
            'status' => $this->status,
            'audience_type' => $this->audience_type,
            'audience_ref' => $this->audience_ref,
            'template_ref' => $this->template_ref,
            'schedule_at' => $this->schedule_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'workspace_id' => $this->workspace_id,
            'stats' => [
                'total' => (int) ($totals['total'] ?? 0),
                'queued' => (int) ($totals['queued'] ?? 0),
                'sent' => (int) ($totals['sent'] ?? 0),
                'delivered' => (int) ($totals['delivered'] ?? 0),
                'read' => (int) ($totals['read'] ?? 0),
                'failed' => (int) ($totals['failed'] ?? 0),
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
