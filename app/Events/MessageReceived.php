<?php

namespace App\Events;

use App\Modules\Shared\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Message $message) {}

    public function broadcastOn(): array
    {
        $convId = $this->message->conversation_id;
        $accountId = $this->message->conversation->channel_account_id ?? null;

        $channels = [new PrivateChannel("conversation.{$convId}")];

        if ($accountId) {
            $channels[] = new PrivateChannel("channel-account.{$accountId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'MessageReceived';
    }

    public function broadcastWith(): array
    {
        $conversation = $this->message->conversation;
        $contact = $conversation?->contact;

        return [
            'id' => $this->message->id,
            'provider_message_id' => $this->message->provider_message_id,
            'conversation_id' => $this->message->conversation_id,
            'conversation_uuid' => $conversation?->uuid,
            'channel_account_id' => $conversation?->channel_account_id,
            'contact_name' => $contact?->full_name ?: $contact?->phone_e164,
            'direction' => $this->message->direction,
            'channel' => $this->message->channel,
            'type' => $this->message->type,
            'body' => $this->message->body,
            'payload' => $this->message->payload,
            'status' => $this->message->status,
            'sent_at' => $this->message->sent_at?->toIso8601String(),
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
