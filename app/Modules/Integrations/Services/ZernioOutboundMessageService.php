<?php

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Models\ZernioAccountMapping;
use App\Modules\Shared\Models\Message;
use RuntimeException;

class ZernioOutboundMessageService
{
    public function send(Message $message): string
    {
        $message->loadMissing('conversation.channelAccount');
        $conversation = $message->conversation;
        $channelAccount = $conversation?->channelAccount;
        if (! $conversation || $channelAccount?->provider !== 'zernio') {
            throw new RuntimeException('A mensagem não pertence a um canal Zernio.');
        }

        $mapping = ZernioAccountMapping::where('workspace_id', $conversation->workspace_id)
            ->where('account_id', $channelAccount->external_account_id)
            ->where('status', 'connected')
            ->firstOrFail();

        $payload = $message->payload ?? [];
        $data = [
            'accountId' => $mapping->account_id,
            'message' => (string) ($payload['provider_body'] ?? $message->body ?? ''),
        ];
        $attachment = $payload['link'] ?? $payload['preview_url'] ?? null;
        if ($attachment) {
            $data['attachmentUrl'] = $attachment;
            $data['attachmentType'] = match ($message->type) {
                'image', 'video', 'audio' => $message->type,
                default => 'file',
            };
        }

        $result = app(ZernioClient::class)->sendMessage(
            (string) $conversation->external_thread_id,
            $data,
            hash('sha256', 'respondesk-message-'.$message->id),
        );

        return (string) ($result['messageId'] ?? data_get($result, 'message.id') ?? data_get($result, 'data.id') ?? '');
    }
}
