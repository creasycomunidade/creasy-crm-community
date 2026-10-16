<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Events\MessageReceived;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ContactService;
use App\Services\StorageManager;
use App\Services\WebhookIdempotencyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EvolutionGoWebhookController extends Controller
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly StorageManager $storage,
    ) {}

    public function receive(Request $request, string $token): JsonResponse
    {
        $account = ChannelAccount::query()
            ->where('provider', 'evolution_go')
            ->where('channel', 'whatsapp')
            ->where('webhook_token_hash', hash('sha256', $token))
            ->first();

        if (! $account) {
            return response()->json(['received' => false], 404);
        }

        $payload = $request->all();
        $instance = (string) ($payload['instanceName'] ?? $payload['instance'] ?? '');
        $expectedInstance = (string) (($account->meta_json ?? [])['instance_name'] ?? '');
        if ($instance !== '' && $expectedInstance !== '' && ! hash_equals($expectedInstance, $instance)) {
            Log::warning('Evolution Go webhook instance mismatch', [
                'channel_account_id' => $account->id,
            ]);

            return response()->json(['received' => false], 403);
        }

        $event = strtoupper((string) ($payload['event'] ?? ''));
        if (in_array($event, ['CONNECTED', 'PAIRSUCCESS', 'CONNECTION_UPDATE'], true)) {
            $account->update([
                'status' => 'active',
                'meta_json' => array_merge($account->meta_json ?? [], [
                    'connection_status' => 'open',
                    'connected_at' => now()->toIso8601String(),
                ]),
            ]);

            return response()->json(['received' => true]);
        }

        if (in_array($event, ['DISCONNECTED', 'LOGGEDOUT', 'TEMPORARYBAN', 'CONNECTFAILURE'], true)) {
            $account->update([
                'status' => $event === 'TEMPORARYBAN' ? 'error' : 'inactive',
                'meta_json' => array_merge($account->meta_json ?? [], [
                    'connection_status' => strtolower($event),
                    'last_connection_event_at' => now()->toIso8601String(),
                ]),
            ]);

            return response()->json(['received' => true]);
        }

        if (! in_array($event, ['MESSAGE', 'SENDMESSAGE'], true)) {
            return response()->json(['received' => true]);
        }

        try {
            $message = $this->processMessage($account, is_array($payload['data'] ?? null) ? $payload['data'] : []);

            return response()->json(['received' => true, 'message_id' => $message?->id]);
        } catch (\Throwable $e) {
            Log::error('Evolution Go webhook processing failed', [
                'channel_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['received' => false], 422);
        }
    }

    private function processMessage(ChannelAccount $account, array $data): ?Message
    {
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];
        $info = is_array($data['Info'] ?? null)
            ? $data['Info']
            : (is_array($data['info'] ?? null) ? $data['info'] : []);
        $providerId = (string) ($key['id'] ?? $info['ID'] ?? $info['id'] ?? '');
        $fromMe = filter_var(
            $key['fromMe'] ?? $info['IsFromMe'] ?? $info['isFromMe'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        $isGroup = $this->isGroupMessage($data, $key, $info);
        if ($isGroup) {
            Log::info('Evolution Go group message ignored before contact resolution', [
                'channel_account_id' => $account->id,
                'provider_message_id' => $providerId ?: null,
            ]);

            return null;
        }

        $jids = $this->messageJids($data, $key, $info, $fromMe);
        $remoteJid = $this->canonicalJid($jids);

        if (
            $providerId === ''
            || $remoteJid === ''
            || str_ends_with($remoteJid, '@broadcast')
            || str_ends_with($remoteJid, '@newsletter')
        ) {
            return null;
        }

        $dedupeKey = $account->id.':'.$providerId;
        if (! app(WebhookIdempotencyService::class)->isNewEvent('evolution_go_message', $dedupeKey)) {
            return Message::where('provider_message_id', 'evogo:'.$dedupeKey)->first();
        }

        $phone = ! $isGroup && str_ends_with($remoteJid, '@s.whatsapp.net')
            ? $this->jidUser($remoteJid)
            : null;

        $participantName = trim((string) (
            $data['pushName']
            ?? $data['notifyName']
            ?? $info['PushName']
            ?? $info['pushName']
            ?? ''
        ));
        $name = $isGroup ? $this->groupName($data, $info, $remoteJid) : $participantName;
        [$firstName, $lastName] = $this->splitName($name);
        $identityJids = $isGroup ? [$remoteJid] : $jids;
        $existing = $this->findContact((int) $account->workspace_id, $account, $phone, $identityJids);
        // A webhook emitted for one of our own outbound messages carries the
        // connected WhatsApp account's push name, not the recipient's name.
        // Also keep an existing lead name stable: profile-name changes on
        // WhatsApp must not overwrite a name curated by the workspace.
        $mayAdoptWhatsappName = ! $fromMe
            && ($isGroup || ! $existing || (blank($existing->first_name) && blank($existing->last_name)));
        $customFields = array_merge($existing?->custom_fields ?? [], [
            'evolution_go_jids' => array_values(array_unique(array_merge(
                $existing?->custom_fields['evolution_go_jids'] ?? [],
                $identityJids,
            ))),
        ]);
        if ($isGroup) {
            $customFields = array_merge($customFields, [
                'is_whatsapp_group' => true,
                'whatsapp_group_jid' => $remoteJid,
                'whatsapp_group_name' => $name,
            ]);
        }
        $contactData = array_filter([
            'phone_e164' => $phone ? '+'.$phone : null,
            'first_name' => $mayAdoptWhatsappName ? ($firstName ?: $existing?->first_name) : $existing?->first_name,
            'last_name' => $mayAdoptWhatsappName ? ($lastName ?: $existing?->last_name) : $existing?->last_name,
            'opt_in_whatsapp' => $fromMe ? ($existing?->opt_in_whatsapp ?? false) : true,
            'source' => $existing?->source ?: 'evolution_go_inbound',
            'last_seen_at' => now(),
            'custom_fields' => $customFields,
        ], fn ($value) => $value !== null);

        if ($existing) {
            $existing->update($contactData);
            $contact = $existing->refresh();
        } elseif ($phone) {
            $contact = $this->contacts->upsert((int) $account->workspace_id, $contactData);
        } else {
            // LIDs are stable WhatsApp identities, but they are not phone numbers.
            // Persist the identity in custom_fields so subsequent messages reuse
            // this contact without inventing an invalid E.164 number.
            $contact = Contact::create(array_merge($contactData, [
                'workspace_id' => $account->workspace_id,
            ]));
        }

        $conversation = Conversation::firstOrCreate(
            [
                'workspace_id' => $account->workspace_id,
                'contact_id' => $contact->id,
                'channel_account_id' => $account->id,
            ],
            ['status' => 'open', 'external_thread_id' => $remoteJid]
        );

        $content = is_array($data['Message'] ?? null)
            ? $data['Message']
            : (is_array($data['message'] ?? null) ? $data['message'] : []);
        [$type, $body, $contentPayload] = $this->content($content, $account, $providerId);
        $timestamp = $data['messageTimestamp']
            ?? $info['Timestamp']
            ?? $info['timestamp']
            ?? null;
        $sentAt = is_numeric($timestamp)
            ? Carbon::createFromTimestamp((int) $timestamp)
            : ($timestamp ? Carbon::parse((string) $timestamp) : now());
        $fullProviderId = 'evogo:'.$dedupeKey;

        if ($fromMe) {
            $existingOutbound = Message::where('provider_message_id', $fullProviderId)->first();
            if ($existingOutbound) {
                return $existingOutbound;
            }
        }

        $participantJid = $isGroup ? $this->participantJid($data, $key, $info) : null;
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => $fromMe ? 'out' : 'in',
            'channel' => 'whatsapp',
            'type' => $type,
            'payload' => array_merge([
                'evolution_go' => $this->sanitisePayload($data),
                'whatsapp_group' => $isGroup ? array_filter([
                    'jid' => $remoteJid,
                    'name' => $name,
                    'participant_jid' => $participantJid,
                    'participant_name' => $fromMe ? 'Você' : ($participantName ?: $participantJid),
                ]) : null,
            ], $contentPayload),
            'body' => $body,
            'status' => $fromMe ? 'sent' : 'delivered',
            'provider_message_id' => $fullProviderId,
            'sent_by' => 'human',
            'sent_at' => $sentAt,
        ]);

        $conversation->update(array_filter([
            'external_thread_id' => $remoteJid,
            'last_message_at' => $sentAt,
            'last_inbound_at' => $fromMe ? null : $sentAt,
            'status' => 'open',
            'unread_count' => $fromMe ? $conversation->unread_count : $conversation->unread_count + 1,
        ], fn ($value) => $value !== null));

        $message->load('conversation');
        $fromMe ? MessageSent::dispatch($message) : MessageReceived::dispatch($message);

        return $message;
    }

    /** @return array{0:string,1:string,2:array<string, mixed>} */
    private function content(array $message, ChannelAccount $account, string $providerId): array
    {
        $message = $this->unwrapMessage($message);
        $quoted = $this->quotedPayload($message, $account);

        if (isset($message['reactionMessage'])) {
            $emoji = trim((string) ($message['reactionMessage']['text'] ?? ''));
            $reactionQuoted = $this->reactionTargetPayload($message['reactionMessage'], $account);

            return ['reaction', $emoji ?: 'Reação removida', array_filter([
                'reaction' => [
                    'emoji' => $emoji,
                    'action' => $emoji !== '' ? 'added' : 'removed',
                    'target_provider_id' => $message['reactionMessage']['key']['ID']
                        ?? $message['reactionMessage']['key']['id']
                        ?? null,
                ],
                'quoted' => $reactionQuoted,
            ])];
        }
        if (isset($message['conversation'])) {
            return $this->contentResult('text', (string) $message['conversation'], [], $quoted);
        }
        if (isset($message['extendedTextMessage']['text'])) {
            return $this->contentResult('text', (string) $message['extendedTextMessage']['text'], [], $quoted);
        }
        if (isset($message['imageMessage'])) {
            return $this->contentResult(
                'image',
                (string) ($message['imageMessage']['caption'] ?? 'Imagem'),
                $this->mediaPayload($message, $message['imageMessage'], $account, $providerId),
                $quoted,
            );
        }
        if (isset($message['videoMessage'])) {
            return $this->contentResult(
                'video',
                (string) ($message['videoMessage']['caption'] ?? 'Vídeo'),
                $this->mediaPayload($message, $message['videoMessage'], $account, $providerId),
                $quoted,
            );
        }
        if (isset($message['documentMessage'])) {
            return $this->contentResult(
                'document',
                (string) ($message['documentMessage']['fileName'] ?? 'Documento'),
                $this->mediaPayload($message, $message['documentMessage'], $account, $providerId),
                $quoted,
            );
        }
        if (isset($message['audioMessage'])) {
            return $this->contentResult('audio', 'Áudio', $this->mediaPayload($message, $message['audioMessage'], $account, $providerId), $quoted);
        }
        if (isset($message['stickerMessage'])) {
            return $this->contentResult('sticker', 'Figurinha', $this->mediaPayload($message, $message['stickerMessage'], $account, $providerId), $quoted);
        }
        if (isset($message['locationMessage'])) {
            return $this->contentResult('location', 'Localização', [
                'location' => [
                    'latitude' => $message['locationMessage']['degreesLatitude'] ?? null,
                    'longitude' => $message['locationMessage']['degreesLongitude'] ?? null,
                    'name' => $message['locationMessage']['name'] ?? null,
                    'address' => $message['locationMessage']['address'] ?? null,
                ],
            ], $quoted);
        }
        if (isset($message['contactMessage'])) {
            return $this->contentResult('contacts', (string) ($message['contactMessage']['displayName'] ?? 'Contato'), [], $quoted);
        }

        if (isset($message['protocolMessage'])) {
            $protocolType = (int) ($message['protocolMessage']['type'] ?? -1);
            if ($protocolType === 0) {
                return $this->contentResult('event', 'Mensagem apagada', [
                    'event' => [
                        'type' => 'message_revoked',
                        'target_provider_id' => $message['protocolMessage']['key']['ID']
                            ?? $message['protocolMessage']['key']['id']
                            ?? null,
                    ],
                ], $quoted);
            }

            return $this->contentResult('event', 'Atualização interna do WhatsApp', [
                'event' => ['type' => 'protocol_update', 'protocol_type' => $protocolType],
            ], $quoted);
        }

        if (isset($message['albumMessage'])) {
            return ['unsupported', 'Álbum de mídias', ['content_kind' => 'album_stub']];
        }

        return $this->contentResult('unsupported', 'Mensagem não suportada', [], $quoted);
    }

    /** @return array{0:string,1:string,2:array<string,mixed>} */
    private function contentResult(string $type, string $body, array $payload, ?array $quoted): array
    {
        if ($quoted) {
            $payload['quoted'] = $quoted;
        }

        return [$type, $body, $payload];
    }

    /** Unwrap WhatsApp containers without losing the actual message type. */
    private function unwrapMessage(array $message): array
    {
        foreach (['ephemeralMessage', 'viewOnceMessage', 'viewOnceMessageV2', 'viewOnceMessageV2Extension', 'documentWithCaptionMessage'] as $wrapper) {
            $nested = data_get($message, $wrapper.'.message');
            if (is_array($nested)) {
                return $this->unwrapMessage($nested);
            }
        }

        $edited = data_get($message, 'protocolMessage.editedMessage');
        if (is_array($edited)) {
            return $this->unwrapMessage($edited);
        }

        return $message;
    }

    /** @return array<string,mixed>|null */
    private function quotedPayload(array $message, ChannelAccount $account): ?array
    {
        foreach (['extendedTextMessage', 'imageMessage', 'videoMessage', 'documentMessage', 'audioMessage', 'stickerMessage'] as $key) {
            $context = $message[$key]['contextInfo'] ?? null;
            if (is_array($context) && is_array($context['quotedMessage'] ?? null)) {
                return $this->buildQuotedPayload(
                    $context['quotedMessage'],
                    (string) ($context['stanzaID'] ?? $context['stanzaId'] ?? ''),
                    $account,
                );
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function reactionTargetPayload(array $reaction, ChannelAccount $account): ?array
    {
        $providerId = (string) ($reaction['key']['ID'] ?? $reaction['key']['id'] ?? '');

        return $providerId !== '' ? $this->buildQuotedPayload([], $providerId, $account) : null;
    }

    /** @return array<string,mixed>|null */
    private function buildQuotedPayload(array $quotedMessage, string $providerId, ChannelAccount $account): ?array
    {
        $original = $providerId !== ''
            ? Message::where('provider_message_id', 'evogo:'.$account->id.':'.$providerId)->first()
            : null;
        if ($original) {
            return array_filter([
                'message_id' => $original->id,
                'provider_id' => $providerId,
                'direction' => $original->direction,
                'type' => $original->type,
                'body' => $original->body,
                'preview_url' => data_get($original->payload, 'preview_url'),
            ], fn ($value) => $value !== null && $value !== '');
        }

        if ($quotedMessage === []) {
            return $providerId !== '' ? ['provider_id' => $providerId, 'type' => 'mensagem', 'body' => 'Mensagem original'] : null;
        }

        $quotedMessage = $this->unwrapMessage($quotedMessage);
        [$type, $body, $media] = match (true) {
            isset($quotedMessage['conversation']) => ['text', (string) $quotedMessage['conversation'], null],
            isset($quotedMessage['extendedTextMessage']) => ['text', (string) ($quotedMessage['extendedTextMessage']['text'] ?? 'Mensagem'), null],
            isset($quotedMessage['imageMessage']) => ['image', (string) ($quotedMessage['imageMessage']['caption'] ?? 'Imagem'), $quotedMessage['imageMessage']],
            isset($quotedMessage['videoMessage']) => ['video', (string) ($quotedMessage['videoMessage']['caption'] ?? 'Vídeo'), $quotedMessage['videoMessage']],
            isset($quotedMessage['audioMessage']) => ['audio', 'Áudio', $quotedMessage['audioMessage']],
            isset($quotedMessage['documentMessage']) => ['document', (string) ($quotedMessage['documentMessage']['fileName'] ?? 'Documento'), $quotedMessage['documentMessage']],
            isset($quotedMessage['stickerMessage']) => ['sticker', 'Figurinha', $quotedMessage['stickerMessage']],
            default => ['mensagem', 'Mensagem original', null],
        };
        $thumbnail = is_array($media) ? ($media['JPEGThumbnail'] ?? $media['jpegThumbnail'] ?? null) : null;

        return array_filter([
            'provider_id' => $providerId ?: null,
            'type' => $type,
            'body' => $body,
            'thumbnail' => is_string($thumbnail) && strlen($thumbnail) <= 512 * 1024
                ? 'data:image/jpeg;base64,'.$thumbnail
                : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** @return array<string, mixed> */
    private function mediaPayload(
        array $message,
        array $media,
        ChannelAccount $account,
        string $providerId,
    ): array {
        $url = $message['mediaUrl'] ?? $media['mediaUrl'] ?? null;
        $mimeType = (string) ($message['mimetype'] ?? $media['mimetype'] ?? '');
        $base64 = $message['base64'] ?? $media['base64'] ?? null;

        if ((! is_string($url) || ! str_starts_with($url, 'http')) && is_string($base64) && $base64 !== '') {
            $url = $this->storeBase64Media($base64, $mimeType, $account, $providerId);
        }

        return array_filter([
            'preview_url' => is_string($url)
                && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/'))
                    ? $url
                    : null,
            'mime_type' => $mimeType ?: null,
            'caption' => $media['caption'] ?? null,
            'filename' => $media['fileName'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function storeBase64Media(
        string $encoded,
        string $mimeType,
        ChannelAccount $account,
        string $providerId,
    ): ?string {
        if (str_starts_with($encoded, 'data:')) {
            if (! preg_match('/^data:([^;,]+);base64,(.+)$/s', $encoded, $matches)) {
                return null;
            }
            $mimeType = $mimeType ?: $matches[1];
            $encoded = $matches[2];
        }

        // Protect the application from oversized webhook bodies being duplicated
        // in memory. 40 MiB encoded is roughly 30 MiB of binary media.
        if (strlen($encoded) > 40 * 1024 * 1024) {
            Log::warning('Evolution Go media exceeds local storage limit', [
                'channel_account_id' => $account->id,
                'provider_message_id' => $providerId,
            ]);

            return null;
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $mimeType = strtolower(trim(explode(';', $mimeType, 2)[0]));
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/aac' => 'm4a',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        try {
            $path = $this->storage->prefixedPath(sprintf(
                'message-media/evolution-go/%d/%s.%s',
                $account->workspace_id,
                hash('sha256', $account->id.':'.$providerId),
                $extension,
            ));
            $disk = $this->storage->disk();
            if (! $disk->put($path, $binary, ['visibility' => 'public'])) {
                return null;
            }

            return $disk->url($path);
        } catch (\Throwable $e) {
            Log::warning('Evolution Go media could not be stored', [
                'channel_account_id' => $account->id,
                'provider_message_id' => $providerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function sanitisePayload(array $data): array
    {
        foreach (['Message', 'message'] as $messageKey) {
            if (! is_array($data[$messageKey] ?? null)) {
                continue;
            }

            unset($data[$messageKey]['base64']);
            foreach (['imageMessage', 'videoMessage', 'documentMessage', 'audioMessage', 'stickerMessage'] as $mediaKey) {
                if (is_array($data[$messageKey][$mediaKey] ?? null)) {
                    unset($data[$messageKey][$mediaKey]['base64']);
                }
            }
        }

        return $data;
    }

    /** @return array{0:?string,1:?string} */
    private function splitName(string $name): array
    {
        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/u', $name, 2) ?: [];

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    private function isGroupMessage(array $data, array $key, array $info): bool
    {
        $values = [
            $key['remoteJid'] ?? $key['remoteJID'] ?? null,
            $key['participant'] ?? null,
            $info['Chat'] ?? $info['chat'] ?? null,
            $data['Chat'] ?? $data['chat'] ?? null,
        ];

        foreach ($values as $value) {
            if (is_array($value)) {
                $server = strtolower((string) ($value['Server'] ?? $value['server'] ?? ''));
                if ($server === 'g.us') {
                    return true;
                }

                $user = $value['User'] ?? $value['user'] ?? null;
                $value = $user && $server ? $user.'@'.$server : null;
            }

            if (is_string($value) && str_ends_with(strtolower(trim($value)), '@g.us')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function messageJids(array $data, array $key, array $info, bool $fromMe): array
    {
        $values = [
            $key['remoteJid'] ?? null,
            $key['remoteJidAlt'] ?? null,
            $info['Chat'] ?? $info['chat'] ?? null,
            $data['Chat'] ?? $data['chat'] ?? null,
        ];

        if ($fromMe) {
            $values[] = $info['RecipientAlt'] ?? $info['recipientAlt'] ?? null;
        } else {
            $values[] = $info['Sender'] ?? $info['sender'] ?? null;
            $values[] = $info['SenderAlt'] ?? $info['senderAlt'] ?? null;
            $values[] = $data['Sender'] ?? $data['sender'] ?? null;
            $values[] = $data['SenderAlt'] ?? $data['senderAlt'] ?? null;
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($value) => $this->normaliseJidValue($value),
            $values,
        ))));
    }

    private function normaliseJidValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $user = $value['User'] ?? $value['user'] ?? null;
            $server = $value['Server'] ?? $value['server'] ?? null;
            $value = $user && $server ? $user.'@'.$server : null;
        }
        if (! is_string($value) || ! str_contains($value, '@')) {
            return null;
        }

        [$user, $server] = array_pad(explode('@', trim($value), 2), 2, '');
        $user = preg_replace('/:\d+$/', '', ltrim($user, '+')) ?: '';
        $server = strtolower($server);

        return ctype_digit($user) && in_array($server, ['s.whatsapp.net', 'lid', 'g.us'], true)
            ? $user.'@'.$server
            : null;
    }

    /** @param array<int, string> $jids */
    private function canonicalJid(array $jids): string
    {
        foreach ($jids as $jid) {
            if (str_ends_with($jid, '@g.us')) {
                return $jid;
            }
        }

        foreach ($jids as $jid) {
            if (str_ends_with($jid, '@s.whatsapp.net')) {
                return $jid;
            }
        }

        return $jids[0] ?? '';
    }

    private function groupName(array $data, array $info, string $groupJid): string
    {
        foreach ([
            $data['GroupName'] ?? $data['groupName'] ?? null,
            $data['ChatName'] ?? $data['chatName'] ?? null,
            $info['GroupName'] ?? $info['groupName'] ?? null,
            $info['ChatName'] ?? $info['chatName'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        $identifier = substr($this->jidUser($groupJid), -6);

        return $identifier !== '' ? 'Grupo do WhatsApp · '.$identifier : 'Grupo do WhatsApp';
    }

    private function participantJid(array $data, array $key, array $info): ?string
    {
        foreach ([
            $key['participant'] ?? null,
            $info['Sender'] ?? $info['sender'] ?? null,
            $info['SenderAlt'] ?? $info['senderAlt'] ?? null,
            $data['Sender'] ?? $data['sender'] ?? null,
            $data['SenderAlt'] ?? $data['senderAlt'] ?? null,
        ] as $candidate) {
            $jid = $this->normaliseJidValue($candidate);
            if ($jid && ! str_ends_with($jid, '@g.us')) {
                return $jid;
            }
        }

        return null;
    }

    private function jidUser(string $jid): string
    {
        return explode('@', $jid, 2)[0];
    }

    /** @param array<int, string> $jids */
    private function findContact(
        int $workspaceId,
        ChannelAccount $account,
        ?string $phone,
        array $jids,
    ): ?Contact {
        if ($phone) {
            $contact = Contact::withTrashed()
                ->where('workspace_id', $workspaceId)
                ->where('phone_e164', '+'.$phone)
                ->first();
            if ($contact) {
                if ($contact->trashed()) {
                    $contact->restore();
                }

                return $contact;
            }
        }

        foreach ($jids as $jid) {
            $contact = Contact::withTrashed()
                ->where('workspace_id', $workspaceId)
                ->whereJsonContains('custom_fields->evolution_go_jids', $jid)
                ->first();
            if ($contact) {
                if ($contact->trashed()) {
                    $contact->restore();
                }

                return $contact;
            }

            $contactId = Conversation::query()
                ->where('workspace_id', $workspaceId)
                ->where('channel_account_id', $account->id)
                ->where('external_thread_id', $jid)
                ->value('contact_id');
            if ($contactId && ($contact = Contact::withTrashed()->find($contactId))) {
                if ($contact->trashed()) {
                    $contact->restore();
                }

                return $contact;
            }
        }

        return null;
    }
}
