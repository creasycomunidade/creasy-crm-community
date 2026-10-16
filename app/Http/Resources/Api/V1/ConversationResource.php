<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channelAccount?->channel,
            'channel_account_id' => $this->channel_account_id,
            'contact_id' => $this->contact_id,
            'status' => $this->status,
            'assigned_user_id' => $this->assigned_user_id,
            'unread_count' => (int) $this->unread_count,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'workspace_id' => $this->workspace_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
