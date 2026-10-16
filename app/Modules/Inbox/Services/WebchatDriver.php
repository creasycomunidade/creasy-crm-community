<?php

namespace App\Modules\Inbox\Services;

use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class WebchatDriver implements ChannelDriverInterface
{
    public function send(Message $message): string
    {
        $account = $message->conversation?->channelAccount;

        if (! $account || $account->channel !== 'webchat' || $account->status !== 'active') {
            throw new RuntimeException('O canal de chat do site não está ativo.');
        }

        return 'webchat_'.Str::uuid();
    }

    public function receiveWebhook(Request $request): array
    {
        return [];
    }

    public function verifyCreds(): bool
    {
        return true;
    }
}
