<?php

namespace App\Modules\Automation\Services;

use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Integrations\Models\ZernioAccountMapping;
use Illuminate\Support\Facades\DB;

class SocialAutomationTriggers
{
    public static function catalog(): array
    {
        return [];
    }

    public static function accounts(int $workspaceId): array
    {
        $accounts = ChannelAccount::where('workspace_id', $workspaceId)->get()->map(fn ($a) => [
            'key' => $a->provider === 'zernio' && $a->external_account_id ? 'zernio:'.$a->external_account_id : 'channel:'.$a->id,
            'platform' => $a->channel === 'messenger' ? 'facebook' : $a->channel,
            'events' => $a->provider === 'zernio' ? null : (in_array($a->channel, ['instagram', 'messenger']) ? ['message.received', 'comment.received'] : ['message.received']),
            'name' => $a->display_name ?: 'Conta #'.$a->id, 'provider' => $a->provider, 'status' => $a->status,
        ]);
        return $accounts->unique('key')->values()->all();
    }

    public function fire(int $workspaceId, string $platform, string $event, string $accountKey, string $eventId, array $context, ?int $contactId = null): void
    {
        $type = $platform.'.'.$event;
        if ($event !== 'message.received' && ! in_array($type, array_column(self::catalog(), 'value'), true)) {
            return;
        }
        $types = [$type];
        if ($event === 'message.received') {
            $types[] = 'message.received';
        }
        $context = array_merge($context, ['trigger_platform' => $platform, 'trigger_event' => $event,
            'trigger_account' => $accountKey, 'trigger_event_id' => $accountKey.':'.$event.':'.$eventId]);
        foreach (Automation::where('workspace_id', $workspaceId)->where('status', 'active')->whereIn('trigger_type', $types)->get() as $automation) {
            $config = $automation->trigger_config ?? [];
            if (! empty($config['account_key']) && $config['account_key'] !== $accountKey) continue;
            if (! empty($config['platform']) && $config['platform'] !== $platform) continue;
            if (! empty($config['post_id']) && (string) $config['post_id'] !== (string) ($context['post_id'] ?? '')) continue;
            $keywords = array_filter((array) ($config['keywords'] ?? []), fn ($v) => is_string($v) && trim($v) !== '');
            $body = mb_strtolower((string) ($context['message_body'] ?? $context['comment_text'] ?? $context['review_text'] ?? $context['post_content'] ?? ''));
            if ($keywords && ! collect($keywords)->contains(fn ($word) => str_contains($body, mb_strtolower(trim($word))))) continue;
            DB::transaction(function () use ($automation, $context, $contactId) {
                $locked = Automation::whereKey($automation->id)->lockForUpdate()->first();
                if ($locked?->status !== 'active') return;
                if (AutomationRun::where('automation_id', $automation->id)->where('context->trigger_event_id', $context['trigger_event_id'])->exists()) return;
                $run = AutomationRun::create(['automation_id' => $automation->id, 'contact_id' => $contactId,
                    'status' => 'pending', 'context' => $context, 'started_at' => now()]);
                ExecuteAutomationRunJob::dispatch($run->id)->onQueue('automation')->afterCommit();
            });
        }
    }
}
