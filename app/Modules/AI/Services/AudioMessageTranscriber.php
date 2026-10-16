<?php

namespace App\Modules\AI\Services;

use App\Modules\Shared\Models\Message;
use App\Modules\Whatsapp\Services\CloudApiClient;
use App\Modules\Whatsapp\Services\EvolutionGoClient;
use App\Services\StorageManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AudioMessageTranscriber
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly LlmGateway $llm,
        private readonly StorageManager $storage,
    ) {}

    public function transcribe(Message $message): ?string
    {
        if ($message->type !== 'audio') {
            return null;
        }

        $payload = $message->payload ?? [];
        $existing = trim((string) ($payload['transcription'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        try {
            [$bytes, $mimeType] = $this->audioBytes($message);
            if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) {
                throw new \RuntimeException('O áudio está vazio ou excede o limite de 20 MB para transcrição.');
            }

            $conversation = $message->conversation()->with('channelAccount')->firstOrFail();
            $transcription = $this->llm->transcribe(
                (int) $conversation->workspace_id,
                $bytes,
                $mimeType,
                ['language' => 'pt'],
                (int) $conversation->id,
            );
            if ($transcription === '') {
                throw new \RuntimeException('O provedor não retornou uma transcrição.');
            }

            $message->update(['payload' => array_merge($payload, [
                'transcription' => $transcription,
                'transcription_status' => 'completed',
                'transcribed_at' => now()->toIso8601String(),
            ])]);

            return $transcription;
        } catch (\Throwable $error) {
            $message->update(['payload' => array_merge($payload, [
                'transcription_status' => 'failed',
                'transcription_error' => 'Não foi possível transcrever este áudio.',
            ])]);
            Log::warning('audio_message.transcription_failed', [
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'error' => $error->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array{0:string,1:string} */
    private function audioBytes(Message $message): array
    {
        $payload = $message->payload ?? [];
        $mimeType = (string) ($payload['mime_type'] ?? $payload['audio']['mime_type'] ?? 'audio/ogg');
        $privateMediaPath = (string) ($payload['private_media_path'] ?? '');
        if (str_starts_with($privateMediaPath, 'message-media/')
            && Storage::disk('local')->exists($privateMediaPath)) {
            return [Storage::disk('local')->get($privateMediaPath), $mimeType];
        }
        $previewUrl = (string) ($payload['preview_url'] ?? $payload['audio']['preview_url'] ?? '');

        if ($previewUrl !== '') {
            $path = parse_url($previewUrl, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/storage/')) {
                $storagePath = ltrim(substr($path, strlen('/storage/')), '/');
                $disk = $this->storage->disk();
                if ($disk->exists($storagePath)) {
                    return [$disk->get($storagePath), $mimeType];
                }
            }
        }

        $conversation = $message->conversation()->with('channelAccount')->firstOrFail();
        if ($conversation->channelAccount?->provider === 'evolution_go') {
            $raw = data_get($payload, 'evolution_go.Message') ?? data_get($payload, 'evolution_go.message');
            if (is_array($raw)) {
                $download = EvolutionGoClient::forAccount($conversation->channelAccount)->downloadMedia($raw);

                return [$download['bytes'], $download['mime_type']];
            }
        }

        $mediaId = $payload['audio']['id'] ?? $payload['media_id'] ?? $message->media_id;
        $client = CloudApiClient::forWorkspace((int) $conversation->workspace_id);
        if ($client && $mediaId) {
            $media = $client->getMediaUrl((string) $mediaId);

            return [$client->downloadMedia($media['url']), (string) ($media['mime_type'] ?? $mimeType)];
        }

        throw new \RuntimeException('Não foi possível localizar o arquivo de áudio desta mensagem.');
    }
}
