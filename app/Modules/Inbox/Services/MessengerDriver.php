<?php

namespace App\Modules\Inbox\Services;

use App\Events\ContactCreated;
use App\Events\MessageReceived;
use App\Modules\Integrations\Services\ZernioOutboundMessageService;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ContactService;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessengerDriver implements ChannelDriverInterface
{
    private const BASE = 'https://graph.facebook.com/v20.0';

    public function __construct(private ContactService $contactService) {}

    public function send(Message $message): string
    {
        $message->loadMissing('conversation.channelAccount');
        if ($message->conversation?->channelAccount?->provider === 'zernio') {
            return app(ZernioOutboundMessageService::class)->send($message);
        }
        $conv = $message->conversation;
        $channelAcct = $conv->channelAccount;
        $creds = $channelAcct?->credentials ?? [];
        $accessToken = $creds['page_access_token'] ?? '';

        if (! $accessToken) {
            throw new \RuntimeException('Messenger page access token not configured.');
        }

        $recipient = ['id' => $conv->external_thread_id];
        $payload = $message->payload ?? [];
        $providerBody = $payload['provider_body'] ?? $message->body;
        $imageUrl = $payload['link'] ?? $payload['preview_url'] ?? null;

        // Image messages (e.g. shared products): send the photo as an attachment,
        // then the caption as a follow-up — a Messenger attachment carries no text.
        if ($message->type === 'image' && $imageUrl) {
            $messageId = $this->postMessage($accessToken, $recipient, [
                'attachment' => ['type' => 'image', 'payload' => ['url' => $imageUrl, 'is_reusable' => true]],
            ]);
            if (! empty($providerBody)) {
                $this->postMessage($accessToken, $recipient, ['text' => $providerBody]);
            }

            return $messageId;
        }

        return $this->postMessage($accessToken, $recipient, ['text' => $providerBody]);
    }

    /**
     * POST a single message object to the Send API and return the message id.
     *
     * @param  array<string, mixed>  $recipient
     * @param  array<string, mixed>  $message
     */
    private function postMessage(string $accessToken, array $recipient, array $message): string
    {
        $resp = Http::withToken($accessToken)
            ->timeout(15)
            ->post(self::BASE.'/me/messages', [
                'recipient' => $recipient,
                'message' => $message,
                'messaging_type' => 'RESPONSE',
            ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('Messenger send failed: '.$resp->body());
        }

        return $resp->json('message_id', '');
    }

    public function receiveWebhook(Request $request): array
    {
        return $this->processWebhookPayload($request->all());
    }

    public function processWebhookPayload(array $payload): array
    {
        $idempotency = app(WebhookIdempotencyService::class);
        $processed = [];

        $entries = $payload['entry'] ?? [];
        Log::info('Messenger webhook: processing payload', [
            'entry_count' => count($entries),
            'entry_ids' => collect($entries)->pluck('id')->filter()->values()->all(),
        ]);

        foreach ($entries as $entry) {
            $entryId = $entry['id'] ?? '';
            $events = $entry['messaging'] ?? [];

            Log::info('Messenger webhook: entry', [
                'entry_id' => $entryId,
                'messaging_count' => count($events),
            ]);

            foreach ($events as $event) {
                try {
                    if (! isset($event['message'])) {
                        Log::info('Messenger webhook: non-message event skipped', [
                            'entry_id' => $entryId,
                            'keys' => array_keys($event),
                        ]);

                        continue;
                    }

                    if ($event['message']['is_echo'] ?? false) {
                        Log::info('Messenger webhook: echo (outbound) event skipped', [
                            'entry_id' => $entryId,
                            'mid' => $event['message']['mid'] ?? null,
                        ]);

                        continue;
                    }

                    $mid = $event['message']['mid'] ?? null;
                    if ($mid && ! $idempotency->isNewEvent('messenger', $mid)) {
                        Log::info('Messenger webhook: duplicate message skipped', ['mid' => $mid]);

                        continue;
                    }

                    $message = $this->processInboundMessage($entryId, $event);

                    if ($message !== null) {
                        $processed[] = $message;
                    }
                } catch (\Throwable $e) {
                    Log::error('Messenger webhook failed', [
                        'entry_id' => $entryId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Messenger webhook: done', ['processed_count' => count($processed)]);

        return $processed;
    }

    public function verifyCreds(): bool
    {
        return true;
    }

    private function processInboundMessage(string $pageId, array $event): ?Message
    {
        $senderId = $event['sender']['id'] ?? '';
        $msgBody = $event['message']['text'] ?? '';

        // The webhook entry.id is the Facebook Page id. Match it against the page_id
        // we persist when the page was connected (InboxSetupController).
        $channelAccount = ChannelAccount::where('channel', 'messenger')
            ->whereJsonContains('meta_json->page_id', $pageId)
            ->first();

        // No matching page → the message belonged to a page that isn't connected to
        // any workspace. Drop it (and log) instead of silently writing it to
        // workspace 0, where it would never appear in any real inbox.
        if (! $channelAccount) {
            Log::warning('Messenger webhook: no channel account matched — message dropped', [
                'entry_id' => $pageId,
                'sender_id' => $senderId,
                'known_ids' => ChannelAccount::where('channel', 'messenger')
                    ->pluck('meta_json')
                    ->map(fn ($m) => $m['page_id'] ?? null)
                    ->filter()->values()->all(),
            ]);

            return null;
        }

        $workspaceId = $channelAccount->workspace_id;

        Log::info('Messenger webhook: inbound message matched', [
            'entry_id' => $pageId,
            'channel_account_id' => $channelAccount->id,
            'workspace_id' => $workspaceId,
            'sender_id' => $senderId,
            'mid' => $event['message']['mid'] ?? null,
        ]);

        $contact = $this->resolveMessengerContact($workspaceId, $senderId, $channelAccount);

        $conversation = Conversation::firstOrCreate(
            ['workspace_id' => $workspaceId, 'contact_id' => $contact->id, 'channel_account_id' => $channelAccount->id],
            ['status' => 'open', 'external_thread_id' => $senderId]
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'messenger',
            'type' => 'text',
            'payload' => $event,
            'body' => $msgBody,
            'status' => 'delivered',
            'provider_message_id' => $event['message']['mid'] ?? null,
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now(), 'status' => 'open', 'unread_count' => $conversation->unread_count + 1]);

        MessageReceived::dispatch($message);

        Log::info('Messenger webhook: message stored', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspaceId,
        ]);

        return $message;
    }

    /**
     * Find-or-create the contact for a Messenger sender, keyed on the stable PSID
     * (stored in custom_fields.messenger_psid) so repeat messages map to ONE contact
     * instead of creating a new "Unknown" contact per message. Backfills the display
     * name and avatar from the page-scoped user profile when available.
     */
    private function resolveMessengerContact(int $workspaceId, string $psid, ChannelAccount $channelAccount): Contact
    {
        $profile = $this->fetchSenderProfile(
            $psid,
            $channelAccount->credentials['page_access_token'] ?? '',
            (string) ($channelAccount->meta_json['page_id'] ?? '')
        );

        $firstName = $profile['first_name'] ?? null;
        $lastName = $profile['last_name'] ?? null;

        $contact = Contact::where('workspace_id', $workspaceId)
            ->whereJsonContains('custom_fields->messenger_psid', $psid)
            ->first();

        if (! $contact) {
            $contact = Contact::create([
                'workspace_id' => $workspaceId,
                'source' => 'messenger',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'custom_fields' => array_filter(['messenger_psid' => $psid]),
            ]);

            ContactCreated::dispatch($contact);
        } elseif ($firstName && ! $contact->first_name) {
            // Backfill name only when we learned one and the contact doesn't already
            // have one (never clobber a manually edited contact).
            $contact->update(['first_name' => $firstName, 'last_name' => $lastName]);
        }

        // The profile_pic URL from Graph is signed and expires, so download and
        // re-host it locally (per messenger.md) rather than storing the volatile URL.
        // Only fetched on first creation / when the contact has no stored avatar yet.
        if (! empty($profile['profile_pic']) && ! $contact->avatar) {
            $this->contactService->downloadAndStoreAvatar($contact, $profile['profile_pic']);
        }

        return $contact;
    }

    /**
     * Fetch a Messenger sender's profile (first/last name, profile picture).
     *
     * Tries two sources, in order:
     *   1. User Profile API  — GET /{PSID}?fields=first_name,last_name,profile_pic.
     *      Reads the *user's* profile; gated behind pages_messaging Advanced Access /
     *      Live mode, so for non-tester users on a dev/standard app it returns Graph
     *      error 100 ("missing permissions").
     *   2. Page Conversations API — GET /{page-id}/conversations?user_id={PSID}.
     *      Reads the *page's own* conversation, which the page owns, so it typically
     *      works under standard access where (1) fails. Yields the participant name
     *      (no picture). Used only as a fallback when (1) gives no name.
     *
     * On total failure we log and return an empty array so messaging still works.
     *
     * @return array<string, mixed>
     */
    private function fetchSenderProfile(string $psid, string $pageToken, string $pageId = ''): array
    {
        if ($psid === '' || $pageToken === '') {
            return [];
        }

        // --- 1. User Profile API ------------------------------------------------
        try {
            $resp = Http::withToken($pageToken)
                ->timeout(10)
                ->get(self::BASE."/{$psid}", [
                    'fields' => 'first_name,last_name,profile_pic',
                ]);

            if ($resp->successful()) {
                $data = $resp->json() ?? [];
                if (! empty($data['first_name']) || ! empty($data['last_name'])) {
                    Log::info('Messenger webhook: profile fetched (user profile api)', [
                        'psid' => $psid,
                        'first_name' => $data['first_name'] ?? null,
                        'last_name' => $data['last_name'] ?? null,
                        'has_picture' => ! empty($data['profile_pic']),
                    ]);

                    return $data;
                }

                Log::info('Messenger webhook: user profile api returned no name', [
                    'psid' => $psid,
                    'response' => $data,
                ]);
            } else {
                Log::info('Messenger webhook: user profile api failed', [
                    'psid' => $psid,
                    'status' => $resp->status(),
                    'error_code' => $resp->json('error.code'),
                    'error' => $resp->json('error.message'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::info('Messenger webhook: user profile api exception', [
                'psid' => $psid,
                'error' => $e->getMessage(),
            ]);
        }

        // --- 2. Page Conversations API (fallback) -------------------------------
        if ($pageId !== '') {
            try {
                $conv = Http::withToken($pageToken)
                    ->timeout(10)
                    ->get(self::BASE."/{$pageId}/conversations", [
                        'platform' => 'messenger',
                        'user_id' => $psid,
                        'fields' => 'participants',
                    ]);

                if ($conv->successful()) {
                    $participants = $conv->json('data.0.participants.data') ?? [];
                    foreach ($participants as $p) {
                        // The participant whose id equals the PSID is the customer;
                        // the other is the page itself.
                        if (($p['id'] ?? '') === $psid && ! empty($p['name'])) {
                            $parts = preg_split('/\s+/u', trim((string) $p['name']), 2) ?: [];

                            Log::info('Messenger webhook: name from conversations api', [
                                'psid' => $psid,
                                'name' => $p['name'],
                            ]);

                            return [
                                'first_name' => $parts[0] ?? null,
                                'last_name' => $parts[1] ?? null,
                            ];
                        }
                    }

                    Log::info('Messenger webhook: conversations api returned no matching participant', [
                        'psid' => $psid,
                        'response' => $conv->json(),
                    ]);
                } else {
                    Log::info('Messenger webhook: conversations api failed', [
                        'psid' => $psid,
                        'status' => $conv->status(),
                        'error_code' => $conv->json('error.code'),
                        'error' => $conv->json('error.message'),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::info('Messenger webhook: conversations api exception', [
                    'psid' => $psid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }
}
