<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Events\MessageSent;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Services\ChatbotRunner;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use App\Modules\Whatsapp\Models\WhatsappAutoReply;
use App\Notifications\ConversationHandoverNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phrases that trigger AI-to-human handover.
 * Case-insensitive substring matching.
 */
const HANDOVER_PHRASES = [
    'talk to human', 'talk to agent', 'speak to agent', 'speak to human',
    'human please', 'real person', 'live agent', 'live support',
    'need a human', 'connect me to', 'transfer me',
];

class AutoReplyListener
{
    public function __construct(
        private readonly ChatbotRunner $runner,
        private readonly ChannelManager $channelManager,
    ) {}

    public function handle(MessageReceived $event): void
    {
        $msgId = $event->message->id ?? null;

        // Deduplication: ensure we never auto-reply twice for the same inbound message.
        // Using an atomic cache lock prevents races when webhooks are delivered in parallel.
        if ($msgId && ! Cache::add("auto_reply_lock:{$msgId}", 1, 60)) {
            return;
        }

        try {
            $this->process($event);
        } catch (\Throwable $e) {
            Log::error('AutoReplyListener unhandled exception', [
                'message_id' => $msgId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    private function process(MessageReceived $event): void
    {
        $message = $event->message;

        if ($message->direction !== 'in') {
            return;
        }

        $conversation = $message->conversation;
        $channelAccount = $conversation?->channelAccount;

        if (! $channelAccount) {
            return;
        }

        // Channel-level business hours are independent from chatbots. When the
        // company is closed, send the configured notice and do not let another
        // chatbot response contradict it.
        if ($this->handleOutsideBusinessHours($message, $conversation, $channelAccount->meta_json['operations'] ?? [])) {
            return;
        }

        if (($conversation->assigned_to ?? 'bot') === 'human') {
            return;
        }

        // ── 1. Keyword / trigger auto-reply rules (always run, no chatbot required) ──
        $autoReply = $this->findMatchingAutoReply(
            $conversation->workspace_id,
            $channelAccount->id,
            $message,
            $conversation,
            $message->channel,
        );

        if ($autoReply) {
            $this->dispatchAutoReply($autoReply, $message, $conversation);

            return;
        }

        // ── 2. Handover phrase detection ─────────────────────────────────────
        $body = strtolower($message->body ?? '');
        foreach (HANDOVER_PHRASES as $phrase) {
            if (str_contains($body, $phrase)) {
                $this->triggerHandover($conversation, 'user_request');

                return;
            }
        }

        // ── 3. AI chatbot (only if one is linked to this channel account) ─────
        $chatbotId = $channelAccount->meta_json['ai_chatbot_id'] ?? null;
        if (! $chatbotId) {
            return;
        }

        $chatbot = AiChatbot::find($chatbotId);
        if (! $chatbot || ! $chatbot->enabled) {
            return;
        }

        if ($chatbot->workspace_id !== $conversation->workspace_id) {
            return;
        }

        try {
            $reply = $this->runner->run($chatbot, $message);
            if ($reply === null) {
                return;
            }

            $botMessage = Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'out',
                'channel' => $message->channel,
                'type' => 'text',
                'body' => $reply,
                'payload' => [],
                'status' => 'queued',
                'sent_by' => 'bot',
                'sent_at' => now(),
            ]);

            try {
                $driver = $this->channelManager->driver($message->channel);
                $providerId = $driver->send($botMessage);
                $botMessage->update(['status' => 'sent', 'provider_message_id' => $providerId]);
            } catch (\Throwable $sendErr) {
                $botMessage->update(['status' => 'failed', 'error_json' => ['message' => $sendErr->getMessage()]]);
                Log::warning('AutoReplyListener AI chatbot send failed', [
                    'message_id' => $botMessage->id,
                    'channel' => $message->channel,
                    'error' => $sendErr->getMessage(),
                ]);
            }

            $conversation->update(['last_message_at' => now()]);
            $botMessage->load('conversation');
            MessageSent::dispatch($botMessage);
        } catch (\Throwable $e) {
            Log::error('AutoReplyListener AI chatbot run failed', [
                'message_id' => $message->id,
                'chatbot_id' => $chatbotId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Returns true when the message arrived outside the configured schedule and
     * the channel-level rule handled it (including cooldown suppression).
     */
    private function handleOutsideBusinessHours(Message $inbound, Conversation $conversation, array $operations): bool
    {
        if (! ($operations['outside_hours_enabled'] ?? false)) {
            return false;
        }

        $body = trim((string) ($operations['outside_hours_message'] ?? ''));
        if ($body === '' || ! $this->isOutsideBusinessHours($operations)) {
            return false;
        }

        $cooldownHours = max(1, min(72, (int) ($operations['outside_hours_cooldown_hours'] ?? 12)));
        $alreadySent = $conversation->messages()
            ->where('direction', 'out')
            ->where('sent_by', 'bot')
            ->where('created_at', '>=', now()->subHours($cooldownHours))
            ->latest('id')
            ->limit(50)
            ->get(['payload'])
            ->contains(fn (Message $message) => ($message->payload['system_auto_reply'] ?? null) === 'outside_business_hours');

        if ($alreadySent) {
            return true;
        }

        $lockKey = "outside_hours_reply:{$conversation->id}";
        if (! Cache::add($lockKey, 1, 30)) {
            return true;
        }

        $reply = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => $inbound->channel,
            'type' => 'text',
            'body' => $body,
            'payload' => [
                'provider_body' => $body,
                'system_auto_reply' => 'outside_business_hours',
            ],
            'status' => 'queued',
            'sent_by' => 'bot',
            'sent_at' => now(),
        ]);

        try {
            $providerId = $this->channelManager->driver($inbound->channel)->send($reply);
            $reply->update(['status' => 'sent', 'provider_message_id' => $providerId]);
        } catch (\Throwable $error) {
            $reply->update(['status' => 'failed', 'error_json' => ['message' => $error->getMessage()]]);
            Log::warning('Outside-hours automatic reply failed', [
                'message_id' => $reply->id,
                'channel' => $inbound->channel,
                'error' => $error->getMessage(),
            ]);
        }

        $conversation->update(['last_message_at' => now()]);
        $reply->load('conversation');
        MessageSent::dispatch($reply);

        return true;
    }

    private function isOutsideBusinessHours(array $operations): bool
    {
        $timezone = $operations['timezone'] ?? 'America/Sao_Paulo';
        try {
            $now = Carbon::now(new \DateTimeZone($timezone));
        } catch (\Throwable) {
            $now = Carbon::now('America/Sao_Paulo');
        }

        $day = strtolower($now->englishDayOfWeek);
        $schedule = $operations['business_hours'][$day] ?? null;
        if (! is_array($schedule) || ! ($schedule['enabled'] ?? false)) {
            return true;
        }

        $start = $this->minutesFromTime((string) ($schedule['start'] ?? '08:00'));
        $end = $this->minutesFromTime((string) ($schedule['end'] ?? '18:00'));
        $current = ($now->hour * 60) + $now->minute;

        // Support overnight shifts such as 22:00–06:00.
        if ($end <= $start) {
            return ! ($current >= $start || $current < $end);
        }

        return $current < $start || $current >= $end;
    }

    private function minutesFromTime(string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time, 2)), 2, 0);

        return max(0, min(1439, ($hour * 60) + $minute));
    }

    private function findMatchingAutoReply(
        int $workspaceId,
        int $channelAccountId,
        Message $message,
        Conversation $conversation,
        string $channel = 'whatsapp',
    ): ?WhatsappAutoReply {
        $rules = WhatsappAutoReply::where('workspace_id', $workspaceId)
            ->where('enabled', true)
            ->where(function ($q) use ($channelAccountId, $channel) {
                // Global rules (no channel account) only apply to WhatsApp
                if ($channel === 'whatsapp') {
                    $q->whereNull('channel_account_id')
                        ->orWhere('channel_account_id', $channelAccountId);
                } else {
                    $q->where('channel_account_id', $channelAccountId);
                }
            })
            ->orderBy('priority')
            ->get();

        $body = $message->body ?? '';
        $isFirstMessage = $conversation->messages()->count() === 1;

        foreach ($rules as $rule) {
            $matched = match ($rule->trigger_type) {
                'keyword' => $rule->matchesMessage($body),
                'welcome' => $isFirstMessage,
                'away' => $this->isOutsideSchedule($rule->schedule_json),
                'out_of_hours' => $this->isOutsideSchedule($rule->schedule_json),
                default => false,
            };
            if ($matched) {
                return $rule;
            }
        }

        return null;
    }

    private function dispatchAutoReply(WhatsappAutoReply $rule, Message $inbound, Conversation $conversation): void
    {
        $payload = $rule->payload_json ?? [];

        [$type, $body, $msgPayload] = match ($rule->response_kind) {
            'template' => [
                'template',
                $payload['template_name'] ?? '',
                ['template' => [
                    'name' => $payload['template_name'] ?? '',
                    'language' => $payload['language'] ?? 'en',
                    'components' => $payload['components'] ?? [],
                ]],
            ],
            'media' => [
                $payload['media_type'] ?? 'image',
                $payload['caption'] ?? '(media)',
                $payload,
            ],
            default => ['text', $payload['text'] ?? '', null],
        };

        if (empty($body) && $type === 'text') {
            return;
        }

        try {
            $botMessage = Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'out',
                'channel' => $inbound->channel,
                'type' => $type,
                'body' => $body,
                'payload' => $msgPayload ?? [],
                'status' => 'queued',
                'sent_by' => 'bot',
                'sent_at' => now(),
            ]);

            try {
                $driver = $this->channelManager->driver($inbound->channel);
                $providerId = $driver->send($botMessage);
                $botMessage->update(['status' => 'sent', 'provider_message_id' => $providerId]);
            } catch (\Throwable $sendErr) {
                $botMessage->update(['status' => 'failed', 'error_json' => ['message' => $sendErr->getMessage()]]);
                Log::warning('AutoReplyListener auto-reply send failed', [
                    'rule_id' => $rule->id,
                    'error' => $sendErr->getMessage(),
                ]);
            }

            $conversation->update(['last_message_at' => now()]);
            $botMessage->load('conversation');
            MessageSent::dispatch($botMessage);
        } catch (\Throwable $e) {
            Log::error('AutoReplyListener dispatchAutoReply failed', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Returns true when the current time falls OUTSIDE the defined business hours window.
     * schedule_json shape: { days: [1-5], start: "HH:MM", end: "HH:MM", timezone: "TZ" }
     * Days follow ISO-8601: 1=Monday … 7=Sunday.
     */
    private function isOutsideSchedule(?array $schedule): bool
    {
        if (empty($schedule)) {
            return false;
        }

        $timezone = $schedule['timezone'] ?? 'UTC';
        try {
            $now = Carbon::now(new \DateTimeZone($timezone));
        } catch (\Exception) {
            $now = Carbon::now();
        }

        $allowedDays = array_map('intval', $schedule['days'] ?? [1, 2, 3, 4, 5]);
        $dayOfWeek = $now->isoWeekday(); // 1=Mon … 7=Sun

        if (! in_array($dayOfWeek, $allowedDays, true)) {
            return true; // today is not a business day
        }

        $startParts = explode(':', $schedule['start'] ?? '09:00');
        $endParts = explode(':', $schedule['end'] ?? '18:00');

        $startMinutes = ((int) ($startParts[0] ?? 9)) * 60 + (int) ($startParts[1] ?? 0);
        $endMinutes = ((int) ($endParts[0] ?? 18)) * 60 + (int) ($endParts[1] ?? 0);
        $nowMinutes = $now->hour * 60 + $now->minute;

        return $nowMinutes < $startMinutes || $nowMinutes >= $endMinutes;
    }

    private function triggerHandover(Conversation $conversation, string $reason): void
    {
        $conversation->update([
            'assigned_to' => 'human',
            'handover_at' => now(),
        ]);

        // Notify all workspace members
        $members = User::forWorkspace($conversation->workspace_id)->get();
        foreach ($members as $member) {
            $member->notify(new ConversationHandoverNotification($conversation, $reason));
        }
    }
}
