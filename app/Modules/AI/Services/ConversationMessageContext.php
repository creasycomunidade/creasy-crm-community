<?php

namespace App\Modules\AI\Services;

use App\Modules\Shared\Models\Message;

class ConversationMessageContext
{
    public function __construct(private readonly AudioMessageTranscriber $audio) {}

    public function text(Message $message): string
    {
        $payload = $message->payload ?? [];
        $body = trim((string) $message->body);

        if ($message->type === 'audio') {
            $transcription = trim((string) ($payload['transcription'] ?? ''));
            if ($transcription === '') {
                $transcription = trim((string) ($this->audio->transcribe($message) ?? ''));
            }
            $body = $transcription !== '' ? '[Áudio transcrito] '.$transcription : '[Áudio sem transcrição disponível]';
        } elseif ($message->type === 'reaction') {
            $emoji = (string) data_get($payload, 'reaction.emoji', $body);
            $body = $emoji !== '' ? 'Reagiu com '.$emoji : 'Removeu uma reação';
        } elseif ($message->type === 'image') {
            $body = '[Imagem] '.($body !== '' && $body !== 'Imagem' ? $body : 'sem legenda');
        } elseif ($message->type === 'video') {
            $body = '[Vídeo] '.($body !== '' && $body !== 'Vídeo' ? $body : 'sem legenda');
        } elseif ($body === '') {
            $body = '['.($message->type ?: 'mensagem').']';
        }

        $quoted = is_array($payload['quoted'] ?? null) ? $payload['quoted'] : null;
        if ($quoted) {
            $quotedType = (string) ($quoted['type'] ?? 'mensagem');
            $quotedBody = trim((string) ($quoted['body'] ?? '')) ?: $quotedType;
            $body = "Em resposta a [{$quotedType}]: {$quotedBody}\n{$body}";
        }

        return $body;
    }
}
