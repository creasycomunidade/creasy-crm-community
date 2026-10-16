<?php

namespace App\Modules\Whatsapp\Services;

use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Integrations\Services\ZernioOutboundMessageService;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ContactService;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappDriver implements ChannelDriverInterface
{
    public function __construct(
        private readonly ContactService $contactService,
    ) {}

    public function send(Message $message): string
    {
        $message->loadMissing('conversation.channelAccount');
        if ($message->conversation?->channelAccount?->provider === 'zernio') {
            return app(ZernioOutboundMessageService::class)->send($message);
        }
        $conversation = $message->conversation;
        $contact = $conversation->contact;
        $phone = $contact->phone_e164;
        $channelAccount = $conversation->channelAccount;

        if (in_array($channelAccount?->provider, WhatsappGatewayClient::PROVIDERS, true)) {
            if ($channelAccount->provider === 'waha' && $message->type === 'image') {
                if ($channelAccount->status !== 'active') {
                    throw new \RuntimeException('A conta de WhatsApp não está conectada.');
                }
                $payload = $message->payload ?? [];
                $id = WhatsappGatewayClient::forAccount($channelAccount)->sendImage(
                    (string) ($phone ?: $conversation->external_thread_id),
                    (string) ($payload['preview_url'] ?? $payload['link'] ?? ''),
                    (string) ($payload['caption'] ?? $payload['provider_body'] ?? $message->body ?? ''),
                    (string) ($payload['filename'] ?? 'imagem'),
                    (string) ($payload['mime_type'] ?? 'image/jpeg'),
                );
                return 'waha:'.$channelAccount->id.':'.$id;
            }

            if ($message->type !== 'text') {
                throw new \RuntimeException('Esta integração suporta mensagens de texto no atendimento.');
            }

            return WhatsappGatewayClient::send($channelAccount, (string) ($phone ?: $conversation->external_thread_id), (string) ($message->payload['provider_body'] ?? $message->body ?? ''));
        }

        if ($channelAccount?->provider === 'evolution_go') {
            return $this->sendViaEvolutionGo($message, $channelAccount, (string) $phone);
        }

        // Prefer the phone number tied to this conversation's channel account so
        // outbound replies go from the same number the customer wrote to.
        $phoneNumberId = $conversation->channelAccount?->phone_number_id;
        $client = $phoneNumberId
            ? CloudApiClient::forPhoneNumber($phoneNumberId, $conversation->workspace_id)
            : null;
        $client ??= CloudApiClient::forWorkspace($conversation->workspace_id);

        if (! $client) {
            throw new \RuntimeException('No active WhatsApp account for workspace.');
        }

        $payload = $message->payload ?? [];
        $providerBody = $payload['provider_body'] ?? $message->body ?? '';

        $resp = match ($message->type) {
            'template' => $client->sendTemplate($phone, $payload['template']['name'] ?? '', $payload['template']['language'] ?? 'en', $payload['template']['components'] ?? []),
            'interactive' => $client->sendInteractive($phone, $payload['interactive'] ?? []),
            'image' => $client->sendMedia($phone, 'image', $payload['media_id'] ?? '', $payload['caption'] ?? null, null, $payload['link'] ?? null),
            'video' => $client->sendMedia($phone, 'video', $payload['media_id'] ?? '', $payload['caption'] ?? null, null, $payload['link'] ?? null),
            'document' => $client->sendMedia($phone, 'document', $payload['media_id'] ?? '', $payload['caption'] ?? null, $payload['filename'] ?? null, $payload['link'] ?? null),
            'audio' => $client->sendMedia($phone, 'audio', $payload['media_id'] ?? ''),
            'location' => $client->sendLocation(
                $phone,
                (float) ($payload['location']['latitude'] ?? 0),
                (float) ($payload['location']['longitude'] ?? 0),
                $payload['location']['name'] ?? null,
                $payload['location']['address'] ?? null,
            ),
            default => $client->sendText($phone, $providerBody),
        };

        if (! $resp->successful()) {
            throw new \RuntimeException('WhatsApp send failed: '.$resp->body());
        }

        return $resp->json('messages.0.id', '');
    }

    public function receiveWebhook(Request $request): array
    {
        return $this->processWebhookPayload($request->all());
    }

    public function processWebhookPayload(array $payload, string $verifyToken = ''): array
    {
        $processed = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            $wabaId = (string) ($entry['id'] ?? '');

            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? '';
                $value = $change['value'] ?? [];

                if ($field === 'message_template_status_update') {
                    $this->processTemplateStatusUpdate($wabaId, $value);

                    continue;
                }

                if (in_array($field, ['phone_number_quality_update', 'phone_number_name_update', 'account_update'], true)) {
                    $this->processPhoneNumberUpdate($value);

                    continue;
                }

                foreach ($value['messages'] ?? [] as $msg) {
                    try {
                        $processed[] = $this->processInboundMessage($value, $msg);
                    } catch (\Throwable $e) {
                        // Never copy message bodies, media metadata, names or phone
                        // numbers into application logs. Keep only non-PII tracing data.
                        Log::error('WhatsApp webhook processing failed', [
                            'error_type' => $e::class,
                            'message_type' => $msg['type'] ?? null,
                            'provider_message_id_hash' => isset($msg['id'])
                                ? hash('sha256', (string) $msg['id'])
                                : null,
                        ]);
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->processStatusUpdate($status);
                }
            }
        }

        return $processed;
    }

    private function processTemplateStatusUpdate(string $wabaId, array $value): void
    {
        $event = strtoupper((string) ($value['event'] ?? ''));
        $name = $value['message_template_name'] ?? null;
        $language = $value['message_template_language'] ?? 'en';

        if (! $wabaId || ! $name || ! $event) {
            return;
        }

        $statusMap = [
            'APPROVED' => 'APPROVED',
            'REJECTED' => 'REJECTED',
            'PENDING' => 'PENDING',
            'PAUSED' => 'PAUSED',
            'DISABLED' => 'PAUSED',
        ];
        $status = $statusMap[$event] ?? null;
        if (! $status) {
            return;
        }

        $reason = $value['reason'] ?? $value['rejection_reason'] ?? null;

        WhatsappTemplate::where('waba_id', $wabaId)
            ->where('name', $name)
            ->where('language', $language)
            ->update(array_filter([
                'status' => $status,
                'rejection_reason' => $status === 'REJECTED' ? (is_string($reason) ? $reason : json_encode($reason)) : null,
                'meta_template_id' => isset($value['message_template_id'])
                    ? (string) $value['message_template_id']
                    : null,
            ]));
    }

    private function processPhoneNumberUpdate(array $value): void
    {
        $phoneNumberId = $value['phone_number_id'] ?? null;
        if (! $phoneNumberId) {
            return;
        }

        // Map Meta's name decision to our name_status values
        $decision = strtoupper((string) ($value['decision'] ?? ''));
        $nameStatus = match ($decision) {
            'APPROVED' => 'APPROVED',
            'REJECTED' => 'DECLINED',
            default => null,
        };

        $patch = array_filter([
            'quality_rating' => $value['current_quality_rating'] ?? $value['quality_rating'] ?? null,
            'messaging_limit_tier' => $value['current_limit'] ?? $value['messaging_limit_tier'] ?? null,
            'display_phone' => $value['display_phone_number'] ?? null,
            // When a name is approved, verified_name updates to the new name
            'verified_name' => $nameStatus === 'APPROVED'
                                          ? ($value['requested_verified_name'] ?? $value['verified_name'] ?? null)
                                          : ($value['verified_name'] ?? null),
            'name_status' => $nameStatus,
            // Clear requested_verified_name once the decision is made
            'requested_verified_name' => in_array($nameStatus, ['APPROVED', 'DECLINED'], true) ? null : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($patch === []) {
            return;
        }

        WhatsappPhoneNumber::where('phone_number_id', (string) $phoneNumberId)->update($patch);

        Log::info('whatsapp.phone_number.updated', [
            'phone_number_id' => $phoneNumberId,
            'patch' => $patch,
        ]);
    }

    public function verifyCreds(): bool
    {
        return true;
    }

    private function sendViaEvolutionGo(Message $message, ChannelAccount $account, string $phone): string
    {
        $client = EvolutionGoClient::forAccount($account);
        $payload = $message->payload ?? [];
        $providerBody = $payload['provider_body'] ?? $message->body ?? '';
        $mediaUrl = $payload['preview_url'] ?? $payload['link'] ?? null;
        [$recipient, $formatJid] = $this->evolutionGoRecipient($message, $phone);

        try {
            if (in_array($message->type, ['image', 'video', 'document', 'audio'], true)) {
                if (! is_string($mediaUrl) || $mediaUrl === '') {
                    throw new \RuntimeException('A mídia precisa ter uma URL pública para ser enviada pela Evolution Go.');
                }

                $providerId = $client->sendMedia(
                    $recipient,
                    $message->type,
                    $mediaUrl,
                    $payload['caption'] ?? $providerBody,
                    $payload['filename'] ?? null,
                    $formatJid,
                );
            } else {
                $providerId = $client->sendText($recipient, (string) $providerBody, $formatJid);
            }
        } catch (EvolutionGoConnectionException $exception) {
            $account->update([
                'status' => 'error',
                'meta_json' => array_merge($account->meta_json ?? [], [
                    'connection_status' => 'disconnected',
                    'last_error' => $exception->getMessage(),
                    'last_checked_at' => now()->toIso8601String(),
                ]),
            ]);

            throw $exception;
        }

        return 'evogo:'.$account->id.':'.$providerId;
    }

    /** @return array{0:string,1:bool} */
    private function evolutionGoRecipient(Message $message, string $phone): array
    {
        $conversation = $message->conversation;
        $candidates = [$conversation->external_thread_id];

        $lastInbound = $conversation->messages()
            ->where('direction', 'in')
            ->whereNotNull('payload')
            ->latest('sent_at')
            ->first(['payload']);
        $gatewayPayload = $lastInbound?->payload['evolution_go'] ?? [];
        if (is_array($gatewayPayload)) {
            foreach ([
                data_get($gatewayPayload, 'key.remoteJid'),
                data_get($gatewayPayload, 'Info.Chat'),
                data_get($gatewayPayload, 'info.chat'),
                data_get($gatewayPayload, 'Info.Sender'),
                data_get($gatewayPayload, 'info.sender'),
            ] as $candidate) {
                $candidates[] = $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            $jid = $this->normaliseDirectJid($candidate);
            if ($jid !== null) {
                return [$jid, false];
            }
        }

        $digits = preg_replace('/\D+/', '', explode('@', $phone, 2)[0]) ?: '';
        if ($digits === '') {
            throw new \RuntimeException('O contato não possui um telefone válido para responder.');
        }

        return [$digits, true];
    }

    private function normaliseDirectJid(string $value): ?string
    {
        $value = trim($value);
        if (! str_contains($value, '@')) {
            return null;
        }

        [$user, $server] = array_pad(explode('@', $value, 2), 2, '');
        $server = strtolower($server);
        if (! in_array($server, ['s.whatsapp.net', 'lid', 'g.us'], true)) {
            return null;
        }

        // A JID de remetente pode trazer o identificador do dispositivo
        // (numero:dispositivo@s.whatsapp.net). O envio deve usar o JID da conta.
        $user = preg_replace('/:\d+$/', '', ltrim($user, '+')) ?: '';
        if (! ctype_digit($user)) {
            return null;
        }

        return $user.'@'.$server;
    }

    private function processInboundMessage(array $value, array $msg): Message
    {
        $msgId = $msg['id'] ?? null;

        // Idempotency guard — skip if already processed or being processed concurrently.
        // insertOrIgnore is atomic, so only one concurrent request gets affected=1.
        // If affected=0 (already seen), never fall through — throw so the outer
        // try-catch skips this duplicate without creating a second message or auto-reply.
        if ($msgId && ! app(WebhookIdempotencyService::class)->isNewEvent('whatsapp_msg', $msgId)) {
            $existing = Message::where('provider_message_id', $msgId)->first();
            if ($existing) {
                return $existing;
            }
            // Race condition: the first request hasn't committed the message yet.
            // Skip rather than fall through and create a duplicate.
            throw new \RuntimeException("Duplicate webhook skipped (concurrent): {$msgId}");
        }

        // TEMP DIAGNOSTIC (remove once incoming poll shape is confirmed):
        // log the raw payload of unsupported / error-bearing messages so we can
        // see exactly how WhatsApp delivers polls and other unsupported types.
        if (($msg['type'] ?? '') === 'unsupported' || ! empty($msg['errors'])) {
            Log::info('whatsapp.inbound.unsupported_payload', [
                'type' => $msg['type'] ?? null,
                'msg' => $msg,
            ]);
        }

        $phoneId = $value['metadata']['phone_number_id'] ?? '';
        $fromPhone = $msg['from'] ?? '';

        $channelAccount = ChannelAccount::where('phone_number_id', $phoneId)
            ->where('channel', 'whatsapp')
            ->first();

        if (! $channelAccount) {
            Log::warning('WhatsApp inbound dropped — no channel_account match', [
                'phone_number_id' => $phoneId,
                'from' => $fromPhone,
                'msg_id' => $msg['id'] ?? null,
                'hint' => 'The phone_number_id received from Meta does not exist in channel_accounts. Re-run the WhatsApp setup or verify the configured number id.',
            ]);

            // Skip persisting — without a workspace the message would be invisible
            // and would corrupt the inbox queries that filter by workspace_id.
            throw new \RuntimeException("No channel_account found for phone_number_id={$phoneId}");
        }

        $workspaceId = (int) $channelAccount->workspace_id;

        $contact = $this->contactService->upsert($workspaceId, [
            'phone_e164' => '+'.$fromPhone,
            'opt_in_whatsapp' => true,
            'source' => 'whatsapp_inbound',
        ]);

        $conversation = Conversation::firstOrCreate(
            ['workspace_id' => $workspaceId, 'contact_id' => $contact->id, 'channel_account_id' => $channelAccount?->id],
            ['status' => 'open', 'external_thread_id' => $fromPhone]
        );

        $type = $msg['type'] ?? 'text';
        $interactive = is_array($msg['interactive'] ?? null) ? $msg['interactive'] : [];
        $textBlock = is_array($msg['text'] ?? null) ? $msg['text'] : [];

        // Extract a human-readable body for every message type
        $body = ($textBlock['body'] ?? null)
            ?? (($msg['button'] ?? [])['text'] ?? null)
            ?? (($interactive['button_reply'] ?? [])['title'] ?? null)
            ?? (($interactive['list_reply'] ?? [])['title'] ?? null)
            ?? (is_array($msg[$type] ?? null) && ! isset($msg[$type][0]) ? ($msg[$type]['caption'] ?? null) : null)
            ?? ($msg['caption'] ?? null)
            ?? ($msg['errors'][0]['title'] ?? null);

        // Type-specific body fallbacks so conversation preview is meaningful
        if ($body === null || $body === '') {
            $body = match ($type) {
                'location' => implode(', ', array_filter([
                    $msg['location']['name'] ?? null,
                    $msg['location']['address'] ?? null,
                    isset($msg['location']['latitude'], $msg['location']['longitude'])
                        ? ($msg['location']['latitude'].','.$msg['location']['longitude'])
                        : null,
                ])) ?: '📍 Location',
                'contacts' => isset($msg['contacts'][0]['name']['formatted_name'])
                    ? ('👤 '.$msg['contacts'][0]['name']['formatted_name'])
                    : '👤 Contact',
                'poll' => '📊 '.($msg['poll']['question'] ?? ($msg['interactive']['poll_creation']['name'] ?? 'Poll')),
                'event' => '📅 '.($msg['event']['title'] ?? ($msg['event']['name'] ?? 'Event')),
                'image' => '🖼 Image',
                'video' => '🎬 Video',
                'audio' => '🎤 Audio',
                'document' => '📄 '.($msg['document']['filename'] ?? 'Document'),
                'sticker' => '😊 Sticker',
                'reaction' => $msg['reaction']['emoji'] ?? '👍',
                default => '',
            };
        }

        $allowedTypes = ['text', 'template', 'media', 'interactive', 'reaction', 'image', 'video',
            'document', 'audio', 'location', 'contacts', 'sticker', 'order', 'poll', 'event', 'unsupported'];

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => in_array($type, $allowedTypes, true) ? $type : 'unsupported',
            'payload' => $msg,
            'body' => $body,
            'status' => 'delivered',
            'provider_message_id' => $msg['id'] ?? null,
            'sent_by' => 'human',
            'sent_at' => now()->createFromTimestamp($msg['timestamp'] ?? time()),
        ]);

        $conversation->update([
            'last_message_at' => $message->sent_at,
            'status' => 'open',
            'unread_count' => $conversation->unread_count + 1,
            'last_inbound_at' => $message->sent_at,
            // If contact replies after we responded, reset first_response_at for next cycle
            'first_response_at' => $conversation->first_response_at && $conversation->last_inbound_at
                ? ($message->sent_at > $conversation->first_response_at ? null : $conversation->first_response_at)
                : $conversation->first_response_at,
        ]);

        // Fire typed event for automations / AI
        MessageReceived::dispatch($message);

        return $message;
    }

    private function processStatusUpdate(array $status): void
    {
        $providerId = $status['id'] ?? null;
        $newStatus = $status['status'] ?? null;

        if (! $providerId || ! $newStatus) {
            return;
        }

        $statusMap = ['sent' => 'sent', 'delivered' => 'delivered', 'read' => 'read', 'failed' => 'failed'];
        $mapped = $statusMap[$newStatus] ?? null;
        if (! $mapped) {
            return;
        }

        // Status priority — never downgrade (e.g. delivered -> sent).
        $priority = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];
        $newPriority = $priority[$mapped] ?? 0;

        // 1. Update inbox `messages` row for this wamid.
        $message = Message::where('provider_message_id', $providerId)->first();
        if ($message) {
            $current = $priority[$message->status] ?? 0;
            if ($newPriority >= $current) {
                $message->update(['status' => $mapped]);
                $message->load('conversation');
                MessageStatusUpdated::dispatch($message);
            }
        }

        // 2. Update campaign_recipients row for this wamid (separate table).
        $recipient = CampaignRecipient::where('provider_message_id', $providerId)->first();
        if ($recipient) {
            $current = $priority[$recipient->status] ?? 0;
            if ($newPriority < $current) {
                return;
            }

            $now = now();
            $patch = ['status' => $mapped];

            if ($mapped === 'sent' && ! $recipient->sent_at) {
                $patch['sent_at'] = $now;
            }
            if ($mapped === 'delivered') {
                if (! $recipient->sent_at) {
                    $patch['sent_at'] = $now;
                }
                if (! $recipient->delivered_at) {
                    $patch['delivered_at'] = $now;
                }
            }
            if ($mapped === 'read') {
                if (! $recipient->sent_at) {
                    $patch['sent_at'] = $now;
                }
                if (! $recipient->delivered_at) {
                    $patch['delivered_at'] = $now;
                }
                if (! $recipient->read_at) {
                    $patch['read_at'] = $now;
                }
            }
            if ($mapped === 'failed') {
                $patch['failed_reason'] = substr(
                    $status['errors'][0]['title']
                        ?? $status['errors'][0]['message']
                        ?? 'unknown',
                    0,
                    512,
                );
            }

            $recipient->update($patch);
        }
    }
}
