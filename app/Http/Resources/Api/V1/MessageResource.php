<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Demo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction,
            'channel' => $this->channel,
            'type' => $this->type,
            'body' => Demo::text($this->body),
            'status' => $this->status,
            'provider_message_id' => $this->provider_message_id,
            'sent_by' => $this->sent_by,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
