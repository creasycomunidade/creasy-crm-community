<?php

namespace App\Events;

use App\Modules\Shared\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a message's delivery status changes (sent/delivered/read/failed)
 * so the inbox UI can update the ✓ ✓✓ indicators in real time.
 */
class MessageStatusUpdated implements ShouldBroadcast
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
        return 'MessageStatusUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'status' => $this->message->status,
            'provider_message_id' => $this->message->provider_message_id,
        ];
    }
}
