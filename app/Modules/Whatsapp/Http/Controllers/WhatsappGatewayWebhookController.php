<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Events\MessageReceived;
use App\Events\MessageSent;
use App\Modules\Whatsapp\Services\GatewayMessageContent;
use App\Events\MessageStatusUpdated;
use App\Http\Controllers\Controller;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Whatsapp\Services\WhatsappGatewayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappGatewayWebhookController extends Controller
{
    public function receive(Request $request, string $token)
    {
        $account = ChannelAccount::whereIn('provider', WhatsappGatewayClient::PROVIDERS)
            ->where('webhook_token_hash', hash('sha256', $token))->first();
        abort_unless($account, 404);
        $waha = $account->provider === 'waha';
        $instance = $request->input($waha ? 'session' : 'instance');
        abort_unless(is_string($instance) && hash_equals((string) ($account->credentials['instance'] ?? ''), $instance), 403);
        $event = strtolower((string) $request->input('event'));
        $data = $request->input($waha ? 'payload' : 'data', []);
        abort_unless(is_array($data), 422);
        $account->update(['meta_json' => array_merge($account->meta_json ?? [], ['last_webhook_at' => now()->toIso8601String(), 'last_webhook_event' => $event])]);

        if (in_array($event, ['session.status', 'connection.update'], true)) {
            $state = (string) ($data[$waha ? 'status' : 'state'] ?? 'unknown');
            $account->update(['status' => in_array($state, ['WORKING', 'open'], true) ? 'active' : 'inactive',
                'meta_json' => array_merge($account->meta_json ?? [], ['connection_status' => $state])]);
        } elseif (in_array($event, ['message.ack', 'messages.update'], true)) {
            foreach (array_is_list($data) ? $data : [$data] as $update) {
                if (is_array($update)) {
                    $this->receipt($account, $update, $waha);
                }
            }
        } elseif (in_array($event, ['message', 'message.any', 'message.reaction', 'messages.upsert'], true)) {
            foreach (array_is_list($data) ? $data : [$data] as $item) {
                if (is_array($item)) {
                    $this->message($account, $item, $waha);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    private function message(ChannelAccount $account, array $data, bool $waha): void
    {
        $fromMe = $waha ? ($data['fromMe'] ?? false) : data_get($data, 'key.fromMe', false);
        if ($fromMe && $waha && ($data['source'] ?? null) === 'api') {
            return; // The sending pipeline persists API messages; phone echoes must be imported.
        }
        $id = $waha ? WhatsappGatewayClient::wahaMessageId($data) : data_get($data, 'key.id');
        $jid = $waha ? WhatsappGatewayClient::wahaChatId($data) : (data_get($data, 'key.remoteJidAlt') ?: data_get($data, 'key.remoteJid', ''));
        // Linked IDs are valid direct conversations, but never phone numbers.
        if (! is_string($id) || $id === '' || ! is_string($jid)
            || ! preg_match('/^[1-9][0-9]{5,19}@(c\.us|s\.whatsapp\.net|lid)$/', $jid)) {
            return;
        }
        $phoneJid = $jid;
        if ($waha && str_ends_with($jid, '@lid')) {
            $phoneJid = data_get($data, '_data.key.remoteJidAlt') ?? ($data['fromAlt'] ?? '');
            if (! is_string($phoneJid) || ! preg_match('/^[1-9][0-9]{6,14}@(c\.us|s\.whatsapp\.net)$/', $phoneJid)) {
                try {
                    $phoneJid = WhatsappGatewayClient::forAccount($account)->phoneNumberForLid($jid) ?? '';
                } catch (\Throwable) {
                    $phoneJid = ''; // Receive the message even when the provider cannot resolve a phone.
                }
            }
        }
        $phone = is_string($phoneJid) && preg_match('/^([1-9][0-9]{6,14})@(c\.us|s\.whatsapp\.net)$/', $phoneJid, $matches)
            ? '+'.$matches[1] : null;
        if (! $phone && (! $waha || ! str_ends_with($jid, '@lid'))) {
            return;
        }
        [$type, $body, $payload] = GatewayMessageContent::parse($data, $waha);
        $fullId = $account->provider.':'.$account->id.':'.$id;
        $message = DB::transaction(function () use ($account, $fullId, $phone, $jid, $body, $type, $payload, $fromMe, $id, $waha) {
            // Serialize webhook replays per account; persist deduplication in the database.
            ChannelAccount::whereKey($account->id)->lockForUpdate()->first();
            $ids = [$fullId];
            if ($waha && preg_match('/^true_[^_]+_([^_]+)(?:_.*)?$/', $id, $parts)) {
                $ids[] = $account->provider.':'.$account->id.':'.$parts[1];
            }
            if (Message::whereIn('provider_message_id', $ids)->exists()) {
                return null;
            }
            $existing = Conversation::where('workspace_id', $account->workspace_id)
                ->where('channel_account_id', $account->id)->where('external_thread_id', $jid)->first();
            $contact = $existing?->contact;
            if (! $contact && $phone) {
                $contact = Contact::firstOrCreate(['workspace_id' => $account->workspace_id, 'phone_e164' => $phone],
                    ['source' => $account->provider.'_inbound', 'opt_in_whatsapp' => false]);
            }
            if (! $contact) {
                $contact = Contact::create(['workspace_id' => $account->workspace_id,
                    'first_name' => 'Contato WhatsApp', 'source' => $account->provider.'_inbound', 'opt_in_whatsapp' => false,
                    'custom_fields' => ['waha_lid' => $jid]]);
            }
            // Inbound contact does not automatically authorize marketing campaigns.
            $conversation = Conversation::firstOrCreate([
                'workspace_id' => $account->workspace_id, 'contact_id' => $contact->id, 'channel_account_id' => $account->id,
            ], ['status' => 'open', 'external_thread_id' => $jid]);
            $message = Message::create([
                'conversation_id' => $conversation->id, 'direction' => $fromMe ? 'out' : 'in', 'channel' => 'whatsapp',
                'type' => $type, 'body' => $body, 'payload' => $payload,
                'status' => $fromMe ? 'sent' : 'delivered', 'provider_message_id' => $fullId, 'sent_by' => 'human', 'sent_at' => now(),
            ]);
            $conversation->update(array_merge(['last_message_at' => now()], $fromMe ? [] : [
                'last_inbound_at' => now(), 'status' => 'open', 'unread_count' => $conversation->unread_count + 1]));

            return $message;
        });
        if ($message) {
            try {
                app(\App\Modules\Whatsapp\Services\GatewayMediaStorage::class)->cache($message);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Gateway media could not be cached on receipt', ['message_id' => $message->id, 'error' => $e->getMessage()]);
            }
            $message->load('conversation');
            $fromMe ? MessageSent::dispatch($message) : MessageReceived::dispatch($message);
        }
    }

    private function receipt(ChannelAccount $account, array $data, bool $waha): void
    {
        $id = $waha ? WhatsappGatewayClient::wahaMessageId($data) : (data_get($data, 'key.id') ?? ($data['keyId'] ?? null));
        $raw = $waha ? ($data['ack'] ?? null) : ($data['status'] ?? null);
        $status = $waha
            ? match ($raw) {
                2 => 'delivered', 3, 4 => 'read', default => null
            }
        : match ($raw) {
            'DELIVERY_ACK', 3 => 'delivered', 'READ', 'PLAYED', 4, 5 => 'read', default => null
        };
        if (! is_string($id) || ! $status) {
            return;
        }
        $fullId = $account->provider.':'.$account->id.':'.$id;
        $ids = [$fullId];
        // NOWEB sendText returns a raw key.id; receipt webhooks serialize the
        // same key as true_chat@c.us_ID. Match only within this account.
        if ($waha && preg_match('/^true_[^_]+_([^_]+)(?:_.*)?$/', $id, $parts)) {
            $ids[] = $account->provider.':'.$account->id.':'.$parts[1];
        }
        $message = Message::whereIn('provider_message_id', $ids)->where('direction', 'out')
            ->whereHas('conversation', fn ($q) => $q->where('channel_account_id', $account->id))->first();
        if ($message && ! in_array($message->status, $status === 'read' ? ['read'] : ['read', 'delivered'], true)) {
            $message->update(['status' => $status]);
            MessageStatusUpdated::dispatch($message);
        }
        $recipient = CampaignRecipient::whereIn('provider_message_id', $ids)
            ->whereHas('campaign', fn ($q) => $q->where('workspace_id', $account->workspace_id)->where('channel_account_id', $account->id))
            ->first();
        if ($recipient && ! $recipient->read_at) {
            $recipient->update(array_filter(['status' => $status,
                'delivered_at' => $recipient->delivered_at ?? now(), 'read_at' => $status === 'read' ? now() : null]));
        }
    }
}
