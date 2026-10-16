<?php

namespace App\Modules\Integrations\Jobs;

use App\Events\MessageReceived;
use App\Modules\Integrations\Models\ZernioAccountMapping;
use App\Modules\Integrations\Models\ZernioWorkspaceConnection;
use App\Modules\Integrations\Services\ZernioWorkspaceManager;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessZernioWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [15, 30, 60, 120, 300];
    }

    public function __construct(private readonly int $connectionId, private readonly array $payload) {}

    public function handle(ZernioWorkspaceManager $manager): void
    {
        $connection = ZernioWorkspaceConnection::findOrFail($this->connectionId);
        $event = (string) ($this->payload['event'] ?? 'unknown');

        if (in_array($event, ['account.connected', 'account.disconnected', 'account.ads.initial_sync_completed'], true)) {
            $manager->syncAccounts($connection);
        } elseif ($event === 'message.received') {
            $this->storeInboundMessage($connection);
        }

        $connection->update([
            'settings' => array_merge($connection->settings ?? [], [
                'last_webhook_event' => $event,
                'last_webhook_at' => now()->toISOString(),
            ]),
        ]);
    }

    private function storeInboundMessage(ZernioWorkspaceConnection $connection): void
    {
        $accountData = (array) ($this->payload['account'] ?? []);
        $messageData = (array) ($this->payload['message'] ?? []);
        $conversationData = (array) ($this->payload['conversation'] ?? []);
        $accountId = (string) ($accountData['accountId'] ?? $accountData['id'] ?? '');
        $mapping = ZernioAccountMapping::where('workspace_id', $connection->workspace_id)
            ->where('account_id', $accountId)->firstOrFail();

        $channel = match ((string) ($accountData['platform'] ?? $mapping->platform)) {
            'facebook' => 'messenger',
            'instagram' => 'instagram',
            'telegram' => 'telegram',
            'whatsapp' => 'whatsapp',
            'sms' => 'sms',
            default => null,
        };
        if (! $channel) {
            return;
        }

        $channelAccount = ChannelAccount::updateOrCreate(
            ['channel' => $channel, 'external_account_id' => $accountId],
            [
                'workspace_id' => $connection->workspace_id,
                'provider' => 'zernio',
                'display_name' => (string) ($accountData['displayName'] ?? $accountData['username'] ?? ucfirst($channel)),
                'status' => 'active',
                'credentials' => [],
                'meta_json' => ['zernio_profile_id' => $connection->profile_id],
            ],
        );

        $sender = (array) ($messageData['sender'] ?? []);
        $senderId = (string) ($sender['businessScopedUserId'] ?? $sender['id'] ?? $conversationData['participantId'] ?? '');
        if ($senderId === '') {
            return;
        }
        $contact = Contact::where('workspace_id', $connection->workspace_id)
            ->where('custom_fields->zernio_sender_id', $senderId)
            ->first();
        $name = trim((string) ($sender['name'] ?? $conversationData['participantName'] ?? $sender['username'] ?? 'Contato'));
        $phone = $this->phone((string) ($sender['phoneNumber'] ?? ''));
        $contactData = [
            'first_name' => $name,
            'phone_e164' => $phone,
            'avatar' => $sender['picture'] ?? $conversationData['participantPicture'] ?? null,
            'source' => 'zernio_'.$channel,
            'last_seen_at' => now(),
            'custom_fields' => array_merge($contact?->custom_fields ?? [], [
                'zernio_sender_id' => $senderId,
                'zernio_username' => $sender['username'] ?? null,
            ]),
        ];
        if ($contact) {
            $contact->update($contactData);
        } else {
            $contact = Contact::create(array_merge($contactData, ['workspace_id' => $connection->workspace_id]));
        }

        $threadId = (string) ($conversationData['id'] ?? $conversationData['platformConversationId'] ?? $messageData['conversationId'] ?? '');
        $conversation = Conversation::firstOrCreate([
            'workspace_id' => $connection->workspace_id,
            'channel_account_id' => $channelAccount->id,
            'contact_id' => $contact->id,
            'external_thread_id' => $threadId,
        ], ['status' => 'open']);

        $attachments = (array) ($messageData['attachments'] ?? []);
        $type = $attachments !== [] ? $this->messageType((string) ($attachments[0]['type'] ?? 'file')) : 'text';
        $providerId = (string) ($messageData['platformMessageId'] ?? $messageData['id'] ?? '');
        $providerId = $providerId !== '' ? $providerId : 'zernio:'.hash('sha256', json_encode($messageData));
        $message = Message::firstOrCreate(
            ['provider_message_id' => $providerId],
            [
                'conversation_id' => $conversation->id,
                'direction' => 'in',
                'channel' => $channel,
                'type' => $type,
                'payload' => ['zernio' => $messageData, 'attachments' => $attachments],
                'body' => $messageData['text'] ?? ($attachments !== [] ? ucfirst($type) : ''),
                'status' => ($messageData['isRead'] ?? false) ? 'read' : 'delivered',
                'sent_by' => 'human',
                'sent_at' => $messageData['sentAt'] ?? now(),
            ],
        );

        if ($message->wasRecentlyCreated) {
            $conversation->update([
                'last_message_at' => $message->sent_at,
                'last_inbound_at' => $message->sent_at,
                'status' => 'open',
                'unread_count' => $conversation->unread_count + 1,
            ]);
            MessageReceived::dispatch($message);
        }
    }

    private function phone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits === '' ? null : '+'.$digits;
    }

    private function messageType(string $type): string
    {
        return match ($type) {
            'image', 'video', 'audio', 'sticker' => $type,
            'file' => 'document',
            default => 'media',
        };
    }
}
