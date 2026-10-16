<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Shared\Models\Message;
use Illuminate\Support\Facades\Storage;

class GatewayMediaStorage
{
    public function cache(Message $message): void
    {
        $payload = $message->payload ?? [];
        if (!is_array($payload['gateway_media'] ?? null)) return;
        $disk = Storage::disk('local');
        if (!empty($payload['private_media_path']) && $disk->exists($payload['private_media_path'])) return;
        $message->loadMissing('conversation.channelAccount');
        $download = WhatsappGatewayClient::forAccount($message->conversation->channelAccount)->downloadMedia($payload['gateway_media']);
        $path = 'message-media/gateway/'.$message->id.'.bin';
        if (!$disk->put($path, $download['bytes'])) throw new \RuntimeException('Não foi possível armazenar a mídia.');
        $message->refresh();
        $message->update(['payload' => array_merge($message->payload ?? [], [
            'private_media_path' => $path, 'mime_type' => $download['mime_type'],
        ])]);
    }
}
