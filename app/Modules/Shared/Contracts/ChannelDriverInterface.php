<?php

namespace App\Modules\Shared\Contracts;

use App\Modules\Shared\Models\Message;
use Illuminate\Http\Request;

interface ChannelDriverInterface
{
    /** Send an outbound message. Returns the provider message ID. */
    public function send(Message $message): string;

    /** Handle an inbound webhook payload, persist and return messages. */
    public function receiveWebhook(Request $request): array;

    /** Verify that the channel account credentials are valid. */
    public function verifyCreds(): bool;
}
