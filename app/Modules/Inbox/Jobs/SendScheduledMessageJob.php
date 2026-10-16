<?php

namespace App\Modules\Inbox\Jobs;

use App\Events\MessageSent;
use App\Modules\Inbox\Models\ScheduledMessage;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use App\Modules\Shared\Services\OutboundMessageSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $scheduledMessageId) {}

    public function handle(ChannelManager $channels, OutboundMessageSignature $signature): void
    {
        $claimed = ScheduledMessage::query()
            ->whereKey($this->scheduledMessageId)
            ->where(function ($query) {
                $query->where('status', 'scheduled')
                    ->orWhere(fn ($stale) => $stale->where('status', 'processing')
                        ->where('processing_at', '<=', now()->subMinutes(10)));
            })
            ->where('next_send_at', '<=', now())
            ->update(['status' => 'processing', 'processing_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $schedule = ScheduledMessage::with(['conversation.channelAccount', 'creator'])->find($this->scheduledMessageId);
        if (! $schedule || ! $schedule->conversation || ! $schedule->creator) {
            $schedule?->update(['status' => 'failed', 'last_error' => 'Conversa ou usuário não encontrado.']);

            return;
        }
        if ($schedule->status !== 'processing') {
            return;
        }

        $conversation = $schedule->conversation;
        $channel = $conversation->channelAccount?->channel ?? 'whatsapp';

        try {
            if ($channel === 'whatsapp'
                && ! in_array($conversation->channelAccount?->provider, ['evolution_go', 'waha', 'evolution_api'], true)
                && ! $conversation->isWhatsappWindowOpen()) {
                throw new \RuntimeException('A janela de 24 horas do WhatsApp está fechada. Use um modelo aprovado.');
            }

            $messageType = $schedule->media_url ? 'image' : 'text';
            $mediaPayload = $schedule->media_url ? [
                'preview_url' => $schedule->media_url,
                'link' => $schedule->media_url,
                'filename' => $schedule->media_name,
                'mime_type' => $schedule->media_mime,
            ] : null;
            $signed = $signature->apply($schedule->creator, $channel, $schedule->body, $mediaPayload, $messageType);
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'out',
                'channel' => $channel,
                'type' => $messageType,
                'body' => $signed['body'],
                'payload' => array_merge($signed['payload'], ['scheduled_message_uuid' => $schedule->uuid]),
                'status' => 'queued',
                'sent_by' => 'automation',
                'user_id' => $schedule->created_by,
                'sent_at' => now(),
            ]);

            try {
                $providerId = $channels->driver($channel)->send($message);
                $message->update(['status' => 'sent', 'provider_message_id' => $providerId]);
            } catch (\Throwable $error) {
                $message->update(['status' => 'failed', 'error_json' => ['message' => $error->getMessage()]]);
                throw $error;
            }

            $conversation->update(['last_message_at' => now()]);
            $message->load('conversation');
            MessageSent::dispatch($message);

            $schedule->occurrences_sent++;
            $next = $schedule->nextOccurrence();
            $schedule->update([
                'occurrences_sent' => $schedule->occurrences_sent,
                'last_sent_at' => now(),
                'next_send_at' => $next ?? $schedule->next_send_at,
                'status' => $next ? 'scheduled' : 'completed',
                'failure_count' => 0,
                'last_error' => null,
                'processing_at' => null,
            ]);
        } catch (\Throwable $error) {
            $failures = $schedule->failure_count + 1;
            $schedule->update([
                'failure_count' => $failures,
                'last_error' => mb_substr($error->getMessage(), 0, 2000),
                'status' => $failures >= 3 ? 'failed' : 'scheduled',
                'next_send_at' => $failures >= 3 ? $schedule->next_send_at : now()->addMinutes(5),
                'processing_at' => null,
            ]);

            Log::warning('scheduled_message.send_failed', [
                'scheduled_message_id' => $schedule->id,
                'workspace_id' => $schedule->workspace_id,
                'error' => $error->getMessage(),
            ]);
        }
    }
}
