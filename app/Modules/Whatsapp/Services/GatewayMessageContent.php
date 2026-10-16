<?php

namespace App\Modules\Whatsapp\Services;

/** Normalize gateway webhooks into the inbox attachment and reaction contract. */
class GatewayMessageContent
{
    public static function parse(array $data, bool $waha): array
    {
        $message = $waha ? (data_get($data, '_data.message') ?? []) : ($data['message'] ?? []);
        for ($i = 0; $i < 5; $i++) {
            $inner = null;
            foreach (['ephemeralMessage', 'viewOnceMessage', 'viewOnceMessageV2', 'documentWithCaptionMessage'] as $wrapper) {
                if (is_array($message[$wrapper]['message'] ?? null)) {
                    $inner = $message[$wrapper]['message'];
                    break;
                }
            }
            if ($inner === null) break;
            $message = $inner;
        }
        $body = $waha ? ($data['body'] ?? '') : ($message['conversation'] ?? data_get($message, 'extendedTextMessage.text', ''));
        $body = is_string($body) ? $body : '';
        $reaction = $waha ? ($data['reaction'] ?? null) : ($message['reactionMessage'] ?? null);
        if (is_array($reaction)) {
            $emoji = (string) ($reaction['text'] ?? '');
            return ['reaction', $emoji ?: 'Reação removida', ['reaction' => [
                'emoji' => $emoji, 'action' => $emoji === '' ? 'removed' : 'added',
                'target_provider_id' => $reaction['messageId'] ?? data_get($reaction, 'key.id'),
            ]]];
        }
        foreach (['image', 'audio', 'video', 'document', 'sticker'] as $type) {
            $media = $message[$type.'Message'] ?? null;
            if (is_array($media)) {
                return self::media($type, $body ?: ($media['caption'] ?? ''), $data, $media, $waha);
            }
        }
        if ($waha && (! empty($data['hasMedia']) || is_array($data['media'] ?? null))) {
            $media = $data['media'] ?? [];
            $mime = (string) ($media['mimetype'] ?? '');
            $type = match (true) {
                ($data['_data']['type'] ?? '') === 'sticker' => 'sticker',
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'audio/') => 'audio',
                str_starts_with($mime, 'video/') => 'video',
                default => 'document',
            };
            return self::media($type, $body, $data, $media, true);
        }
        return ['text', $body ?: '[Mensagem não suportada]', ['body' => $body ?: '[Mensagem não suportada]']];
    }

    private static function media(string $type, string $caption, array $data, array $media, bool $waha): array
    {
        $id = $waha ? WhatsappGatewayClient::wahaMessageId($data) : data_get($data, 'key.id');
        $payload = ['media_id' => $id, 'caption' => $caption,
            'mime_type' => $data['media']['mimetype'] ?? $media['mimetype'] ?? null,
            'filename' => $data['media']['filename'] ?? $media['fileName'] ?? null,
            'gateway_media' => $waha
                ? ['url' => is_string($data['media']['url'] ?? null) ? parse_url($data['media']['url'], PHP_URL_PATH) : null, 'id' => $id,
                    'chat_id' => WhatsappGatewayClient::wahaChatId($data)]
                : ['id' => $id],
        ];
        return [$type, $caption, $payload];
    }
}
