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

class InstagramDriver implements ChannelDriverInterface
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
        $accessToken = $creds['access_token'] ?? '';
        $igAccountId = $creds['instagram_account_id'] ?? '';
        $recipientId = $conv->external_thread_id;

        if (! $accessToken || ! $igAccountId) {
            throw new \RuntimeException('Instagram credentials not configured.');
        }

        $payload = $message->payload ?? [];
        $providerBody = $payload['provider_body'] ?? $message->body;
        $imageUrl = $payload['link'] ?? $payload['preview_url'] ?? null;

        // Image messages (e.g. shared products): send the photo as an attachment,
        // then the caption as a follow-up — an IG attachment carries no text.
        if ($message->type === 'image' && $imageUrl) {
            $messageId = $this->postMessage($accessToken, $igAccountId, $recipientId, [
                'attachment' => ['type' => 'image', 'payload' => ['url' => $imageUrl, 'is_reusable' => true]],
            ]);
            if (! empty($providerBody)) {
                $this->postMessage($accessToken, $igAccountId, $recipientId, ['text' => $providerBody]);
            }

            return $messageId;
        }

        return $this->postMessage($accessToken, $igAccountId, $recipientId, ['text' => $providerBody]);
    }

    /**
     * POST a single message object to Instagram, with the Page /me/messages
     * fallback used for Facebook-Login (Meta TEST) connections.
     *
     * @param  array<string, mixed>  $messageObj
     */
    private function postMessage(string $accessToken, string $igAccountId, string $recipientId, array $messageObj): string
    {
        // Primary (existing behaviour): send via the IG account messages endpoint.
        $resp = Http::withToken($accessToken)
            ->timeout(15)
            ->post(self::BASE."/{$igAccountId}/messages", [
                'recipient' => ['id' => $recipientId],
                'message' => $messageObj,
                'messaging_type' => 'RESPONSE',
            ]);

        if ($resp->successful()) {
            return $resp->json('message_id', '');
        }

        $errCode = $resp->json('error.code');
        $errMsg = $resp->json('error.message') ?? $resp->body();

        Log::warning('Instagram send failed', [
            'ig_account_id' => $igAccountId,
            'recipient' => $recipientId,
            'error_code' => $errCode,
            'error' => $errMsg,
        ]);

        // Fallback (per instagram.md §6): on a Facebook-Login based connection —
        // which is how Meta TEST accounts are reached — Instagram DMs are sent
        // through the linked Page's /me/messages endpoint with the Page token,
        // NOT /{ig_account_id}/messages. Retry that way before giving up; this is
        // what makes sends to a test Instagram account go through. Normal accounts
        // already succeed above, so their behaviour is unchanged.
        $fallback = Http::withToken($accessToken)
            ->timeout(15)
            ->post(self::BASE.'/me/messages', [
                'recipient' => ['id' => $recipientId],
                'message' => $messageObj,
                'messaging_type' => 'RESPONSE',
            ]);

        if ($fallback->successful()) {
            Log::info('Instagram send: succeeded via /me/messages fallback', [
                'recipient' => $recipientId,
            ]);

            return $fallback->json('message_id', '');
        }

        $fbCode = $fallback->json('error.code');
        $fbMsg = $fallback->json('error.message') ?? $fallback->body();

        Log::warning('Instagram send fallback (/me/messages) also failed', [
            'recipient' => $recipientId,
            'error_code' => $fbCode,
            'error' => $fbMsg,
        ]);

        // (#3) = the app / page token lacks the capability for this call. For
        // Instagram sending that means the `instagram_manage_messages` permission
        // is missing (Advanced Access not granted, or not requested during
        // connect). Receiving works without it, which is why inbound already does.
        if ((int) $errCode === 3 || (int) $fbCode === 3) {
            throw new \RuntimeException(
                'Instagram send failed: your Meta app is missing the "instagram_manage_messages" permission. '
                .'Add it to your Instagram embedded-signup (Social) configuration, get Advanced Access approved, '
                .'set the app to Live, then reconnect the Instagram account.'
            );
        }

        throw new \RuntimeException('Instagram send failed: '.$fbMsg);
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
        Log::info('Instagram webhook: processing payload', [
            'entry_count' => count($entries),
            'entry_ids' => collect($entries)->pluck('id')->filter()->values()->all(),
        ]);

        foreach ($entries as $entry) {
            $entryId = $entry['id'] ?? '';
            $events = $entry['messaging'] ?? [];

            Log::info('Instagram webhook: entry', [
                'entry_id' => $entryId,
                'messaging_count' => count($events),
            ]);

            foreach ($events as $event) {
                try {
                    if (! isset($event['message'])) {
                        Log::info('Instagram webhook: non-message event skipped', [
                            'entry_id' => $entryId,
                            'keys' => array_keys($event),
                        ]);

                        continue;
                    }

                    $isEcho = (bool) ($event['message']['is_echo'] ?? false);

                    $mid = $event['message']['mid'] ?? null;
                    if ($mid && ! $idempotency->isNewEvent('instagram', $mid)) {
                        Log::info('Instagram webhook: duplicate message skipped', ['mid' => $mid]);

                        continue;
                    }

                    // Echo = a message the business sent from the Instagram app (or
                    // another tool) — record it as an outbound message so the thread
                    // stays complete. Otherwise it's an inbound customer message.
                    $message = $isEcho
                        ? $this->processEchoMessage($entryId, $event)
                        : $this->processInboundMessage($entryId, $event);

                    if ($message !== null) {
                        $processed[] = $message;
                    }
                } catch (\Throwable $e) {
                    Log::error('Instagram webhook failed', [
                        'entry_id' => $entryId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Instagram webhook: done', ['processed_count' => count($processed)]);

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

        // The webhook entry.id is the Instagram account id. Match it against either
        // key we persist (instagram_page_id holds the IG account id for embedded-signup
        // connections; instagram_account_id is the explicit copy) so the account is
        // found regardless of which shape it was stored in.
        $channelAccount = ChannelAccount::where('channel', 'instagram')
            ->where(function ($q) use ($pageId) {
                $q->whereJsonContains('meta_json->instagram_page_id', $pageId)
                    ->orWhereJsonContains('meta_json->instagram_account_id', $pageId);
            })
            ->first();

        if (! $channelAccount) {
            Log::warning('Instagram webhook: no channel account matched — message dropped', [
                'entry_id' => $pageId,
                'sender_id' => $senderId,
                'known_ids' => ChannelAccount::where('channel', 'instagram')
                    ->pluck('meta_json')
                    ->map(fn ($m) => $m['instagram_account_id'] ?? $m['instagram_page_id'] ?? null)
                    ->filter()->values()->all(),
            ]);

            return null;
        }

        $workspaceId = $channelAccount->workspace_id;

        Log::info('Instagram webhook: inbound message matched', [
            'entry_id' => $pageId,
            'channel_account_id' => $channelAccount->id,
            'workspace_id' => $workspaceId,
            'sender_id' => $senderId,
            'mid' => $event['message']['mid'] ?? null,
        ]);

        $contact = $this->resolveInstagramContact($workspaceId, $senderId, $channelAccount);

        $conversation = Conversation::firstOrCreate(
            ['workspace_id' => $workspaceId, 'contact_id' => $contact->id, 'channel_account_id' => $channelAccount->id],
            ['status' => 'open', 'external_thread_id' => $senderId]
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'instagram',
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

        Log::info('Instagram webhook: message stored', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspaceId,
        ]);

        return $message;
    }

    /**
     * Handle an echo event — a message the business sent from the Instagram app (or
     * another tool). Recorded as an OUTBOUND message on the same thread so the
     * conversation stays complete. On an echo the sender is the business account, so
     * the thread is keyed on the recipient (the customer).
     */
    private function processEchoMessage(string $pageId, array $event): ?Message
    {
        $recipientId = $event['recipient']['id'] ?? '';
        $msgBody = $event['message']['text'] ?? '';
        $mid = $event['message']['mid'] ?? null;

        if ($recipientId === '') {
            Log::info('Instagram webhook: echo without recipient — skipped', ['entry_id' => $pageId]);

            return null;
        }

        $channelAccount = ChannelAccount::where('channel', 'instagram')
            ->where(function ($q) use ($pageId) {
                $q->whereJsonContains('meta_json->instagram_page_id', $pageId)
                    ->orWhereJsonContains('meta_json->instagram_account_id', $pageId);
            })
            ->first();

        if (! $channelAccount) {
            Log::warning('Instagram webhook: echo — no channel account matched, dropped', [
                'entry_id' => $pageId,
                'recipient_id' => $recipientId,
            ]);

            return null;
        }

        // A message already recorded with this mid (e.g. sent through the platform,
        // which then echoes back) must not be duplicated.
        if ($mid && Message::where('provider_message_id', $mid)->exists()) {
            Log::info('Instagram webhook: echo already recorded — skipped', ['mid' => $mid]);

            return null;
        }

        $workspaceId = $channelAccount->workspace_id;

        Log::info('Instagram webhook: echo (outbound) matched', [
            'entry_id' => $pageId,
            'channel_account_id' => $channelAccount->id,
            'workspace_id' => $workspaceId,
            'recipient_id' => $recipientId,
            'mid' => $mid,
        ]);

        $contact = $this->resolveInstagramContact($workspaceId, $recipientId, $channelAccount);

        $conversation = Conversation::firstOrCreate(
            ['workspace_id' => $workspaceId, 'contact_id' => $contact->id, 'channel_account_id' => $channelAccount->id],
            ['status' => 'open', 'external_thread_id' => $recipientId]
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => 'instagram',
            'type' => 'text',
            'payload' => $event,
            'body' => $msgBody,
            'status' => 'sent',
            'provider_message_id' => $mid,
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        // Outbound: refresh the thread timestamp but do not bump unread_count.
        $conversation->update(['last_message_at' => now(), 'status' => 'open']);

        MessageReceived::dispatch($message);

        Log::info('Instagram webhook: echo message stored', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspaceId,
        ]);

        return $message;
    }

    /**
     * Find-or-create the contact for an Instagram sender, keyed on the stable IGSID
     * (stored in custom_fields.instagram_psid) so repeat messages map to ONE contact
     * instead of creating a new "Unknown" contact per message. Backfills the display
     * name and avatar from the Instagram user profile when available.
     */
    private function resolveInstagramContact(int $workspaceId, string $igsid, ChannelAccount $channelAccount): Contact
    {
        $profile = $this->fetchSenderProfile($igsid, $channelAccount->credentials['access_token'] ?? '');

        $name = $profile['name'] ?? $profile['username'] ?? null;
        $firstName = $lastName = null;
        if ($name !== null && trim((string) $name) !== '') {
            $parts = preg_split('/\s+/u', trim((string) $name), 2) ?: [];
            $firstName = $parts[0] ?? null;
            $lastName = $parts[1] ?? null;
        }

        $contact = Contact::where('workspace_id', $workspaceId)
            ->whereJsonContains('custom_fields->instagram_psid', $igsid)
            ->first();

        if (! $contact) {
            $contact = Contact::create([
                'workspace_id' => $workspaceId,
                'source' => 'instagram',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'custom_fields' => array_filter([
                    'instagram_psid' => $igsid,
                    'instagram_username' => $profile['username'] ?? null,
                ]),
            ]);

            ContactCreated::dispatch($contact);
        } else {
            // Backfill name/username only when we learned something and the contact
            // doesn't already have a name (never clobber a manually edited contact).
            $updates = [];
            if ($firstName && ! $contact->first_name) {
                $updates['first_name'] = $firstName;
                $updates['last_name'] = $lastName;
            }
            if (! empty($profile['username'])) {
                $cf = $contact->custom_fields ?? [];
                if (($cf['instagram_username'] ?? null) !== $profile['username']) {
                    $cf['instagram_username'] = $profile['username'];
                    $updates['custom_fields'] = $cf;
                }
            }
            if ($updates !== []) {
                $contact->update($updates);
            }
        }

        if (! empty($profile['profile_pic'])) {
            $this->contactService->syncAvatarFromUrl($contact, $profile['profile_pic']);
        }

        return $contact;
    }

    /**
     * Fetch an Instagram sender's public profile (name, username, profile picture)
     * via the page access token. Requires instagram_manage_messages; on failure we
     * log and return an empty array so messaging still works without the profile.
     *
     * @return array<string, mixed>
     */
    private function fetchSenderProfile(string $igsid, string $pageToken): array
    {
        if ($igsid === '' || $pageToken === '') {
            return [];
        }

        try {
            $resp = Http::withToken($pageToken)
                ->timeout(10)
                ->get(self::BASE."/{$igsid}", [
                    'fields' => 'name,username,profile_pic',
                ]);

            if (! $resp->successful()) {
                Log::info('Instagram webhook: profile fetch failed', [
                    'igsid' => $igsid,
                    'status' => $resp->status(),
                    'error_code' => $resp->json('error.code'),
                    'error' => $resp->json('error.message'),
                ]);

                return [];
            }

            return $resp->json() ?? [];
        } catch (\Throwable $e) {
            Log::info('Instagram webhook: profile fetch exception', [
                'igsid' => $igsid,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
