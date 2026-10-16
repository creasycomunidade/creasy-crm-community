<?php

namespace App\Modules\Telegram\Services;

use App\Events\MessageReceived;
use App\Modules\Integrations\Services\ZernioOutboundMessageService;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelegramDriver implements ChannelDriverInterface
{
    public function send(Message $message): string
    {
        $message->loadMissing('conversation.channelAccount');
        if ($message->conversation?->channelAccount?->provider === 'zernio') {
            return app(ZernioOutboundMessageService::class)->send($message);
        }
        $conversation = $message->conversation;
        $account = $conversation?->channelAccount;
        abort_unless($account?->channel === 'telegram', 422, 'Telegram channel account not found.');

        $token = (string) ($account->credentials['bot_token'] ?? '');
        $chatId = (string) $conversation->external_thread_id;
        if ($token === '' || $chatId === '') {
            throw new \RuntimeException('Telegram bot token or chat identifier is missing.');
        }

        $payload = $message->payload ?? [];
        $providerBody = ($payload['provider_body'] ?? $message->body) ?: ' ';
        $method = 'sendMessage';
        $params = [
            'chat_id' => $chatId,
            'text' => $providerBody,
        ];
        if (($payload['provider_body_format'] ?? null) === 'html') {
            $params['parse_mode'] = 'HTML';
        }

        $mediaUrl = $payload['link'] ?? $payload['preview_url'] ?? null;
        if ($mediaUrl && $message->type === 'image') {
            $method = 'sendPhoto';
            $params = ['chat_id' => $chatId, 'photo' => $mediaUrl, 'caption' => $providerBody];
        } elseif ($mediaUrl && $message->type === 'document') {
            $method = 'sendDocument';
            $params = ['chat_id' => $chatId, 'document' => $mediaUrl, 'caption' => $providerBody];
        }
        if (($payload['provider_body_format'] ?? null) === 'html') {
            $params['parse_mode'] = 'HTML';
        }

        $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/{$method}", $params);
        if (! $response->successful() || ! $response->json('ok')) {
            throw new \RuntimeException('Telegram send failed: '.$response->json('description', $response->body()));
        }

        return (string) $response->json('result.message_id', '');
    }

    public function receiveWebhook(Request $request): array
    {
        return [];
    }

    public function verifyCreds(): bool
    {
        return true;
    }

    public function processUpdate(ChannelAccount $account, array $update): ?Message
    {
        $updateId = (string) ($update['update_id'] ?? '');
        if ($updateId !== '' && ! app(WebhookIdempotencyService::class)->isNewEvent('telegram_update', $updateId)) {
            return null;
        }

        $callback = $update['callback_query'] ?? null;
        $incoming = $update['message'] ?? $update['edited_message'] ?? ($callback['message'] ?? null);
        if (! is_array($incoming)) {
            return null;
        }

        $from = $callback['from'] ?? $incoming['from'] ?? [];
        $chat = $incoming['chat'] ?? [];
        $chatId = (string) ($chat['id'] ?? '');
        $telegramUserId = (string) ($from['id'] ?? $chatId);
        if ($chatId === '' || $telegramUserId === '') {
            return null;
        }

        $workspaceId = (int) $account->workspace_id;
        $contact = Contact::where('workspace_id', $workspaceId)
            ->whereJsonContains('custom_fields->telegram_user_id', $telegramUserId)
            ->first();

        $contactData = [
            'first_name' => $from['first_name'] ?? ($chat['first_name'] ?? null),
            'last_name' => $from['last_name'] ?? ($chat['last_name'] ?? null),
            'language' => $from['language_code'] ?? null,
            'source' => 'telegram_inbound',
            'last_seen_at' => now(),
            'custom_fields' => array_merge($contact?->custom_fields ?? [], [
                'telegram_user_id' => $telegramUserId,
                'telegram_username' => $from['username'] ?? ($chat['username'] ?? null),
                'telegram_chat_id' => $chatId,
            ]),
        ];

        if ($contact) {
            $contact->update($contactData);
        } else {
            $contact = Contact::create(array_merge($contactData, ['workspace_id' => $workspaceId]));
        }

        $conversation = Conversation::firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'channel_account_id' => $account->id,
                'external_thread_id' => $chatId,
            ],
            ['status' => 'open']
        );

        [$type, $body] = $this->messageContent($incoming, $callback);
        $providerId = $chatId.':'.(string) ($incoming['message_id'] ?? $updateId);

        $message = Message::firstOrCreate(
            ['provider_message_id' => $providerId],
            [
                'conversation_id' => $conversation->id,
                'direction' => 'in',
                'channel' => 'telegram',
                'type' => $type,
                'payload' => $update,
                'body' => $body,
                'status' => 'delivered',
                'sent_by' => 'human',
                'sent_at' => isset($incoming['date'])
                    ? now()->createFromTimestamp((int) $incoming['date'])
                    : now(),
            ]
        );

        $conversation->update([
            'last_message_at' => $message->sent_at,
            'last_inbound_at' => $message->sent_at,
            'status' => 'open',
            'unread_count' => $conversation->unread_count + ($message->wasRecentlyCreated ? 1 : 0),
        ]);

        if ($message->wasRecentlyCreated) {
            MessageReceived::dispatch($message);
        }

        return $message;
    }

    private function messageContent(array $message, ?array $callback): array
    {
        if ($callback) {
            return ['interactive', (string) ($callback['data'] ?? 'Ação do Telegram')];
        }
        if (isset($message['text'])) {
            return ['text', (string) $message['text']];
        }
        if (isset($message['photo'])) {
            return ['image', (string) ($message['caption'] ?? '🖼 Imagem')];
        }
        if (isset($message['document'])) {
            return ['document', (string) ($message['caption'] ?? ('📄 '.($message['document']['file_name'] ?? 'Documento')))];
        }
        if (isset($message['video'])) {
            return ['video', (string) ($message['caption'] ?? '🎬 Vídeo')];
        }
        if (isset($message['audio']) || isset($message['voice'])) {
            return ['audio', '🎤 Áudio'];
        }
        if (isset($message['sticker'])) {
            return ['sticker', '😊 Figurinha'];
        }
        if (isset($message['location'])) {
            return ['location', '📍 Localização'];
        }
        if (isset($message['contact'])) {
            return ['contacts', '👤 '.($message['contact']['first_name'] ?? 'Contato')];
        }

        return ['unsupported', 'Mensagem do Telegram'];
    }
}
