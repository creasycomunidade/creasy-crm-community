<?php

namespace App\Events;

use App\Modules\Shared\Models\Contact;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Contact $contact) {}

    public function broadcastOn(): array
    {
        $wsId = $this->contact->workspace_id;

        return [new PrivateChannel("workspace.{$wsId}")];
    }

    public function broadcastAs(): string
    {
        return 'ContactCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->contact->id,
            'name' => $this->contact->name,
            'phone' => $this->contact->phone,
            'email' => $this->contact->email,
            'created_at' => $this->contact->created_at?->toIso8601String(),
        ];
    }
}
