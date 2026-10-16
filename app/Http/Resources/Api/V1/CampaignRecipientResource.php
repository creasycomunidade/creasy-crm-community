<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignRecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'contact_id' => $this->contact_id,
            'status' => $this->status,
            'provider_message_id' => $this->provider_message_id,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toIso8601String() ?? null,
        ];
    }
}
