<?php

namespace App\Modules\Telegram\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelegramSetupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bot_token' => ['required', 'string', 'max:255', 'regex:/^\d+:[A-Za-z0-9_-]{20,}$/'],
        ], [
            'bot_token.regex' => __('The Telegram bot token format is invalid.'),
        ]);

        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        $workspace = $request->user()->accessibleWorkspaces()->firstWhere('id', $workspaceId);
        abort_unless($workspace && $workspace->canManageMembers($request->user()), 403);

        $token = trim($validated['bot_token']);
        $me = Http::timeout(15)->get("https://api.telegram.org/bot{$token}/getMe");
        if (! $me->successful() || ! $me->json('ok')) {
            return back()->with('error', __('Telegram rejected this bot token.'));
        }

        $bot = $me->json('result', []);
        $botId = (string) ($bot['id'] ?? '');
        if ($botId === '') {
            return back()->with('error', __('Telegram did not return a bot identifier.'));
        }

        $conflict = ChannelAccount::where('channel', 'telegram')
            ->where('external_account_id', $botId)
            ->where('workspace_id', '!=', $workspaceId)
            ->exists();
        if ($conflict) {
            return back()->with('error', __('This Telegram bot is already connected to another workspace.'));
        }

        $existing = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'telegram')
            ->where('external_account_id', $botId)
            ->first();

        $limit = $request->user()->effectiveSubscription()?->plan?->limitValue('telegram_bots');
        if (! $existing && $limit !== null) {
            $current = ChannelAccount::where('workspace_id', $workspaceId)->where('channel', 'telegram')->count();
            if ($current >= (int) $limit) {
                return back()->with('error', __('Your plan Telegram bot limit has been reached.'));
            }
        }

        $webhookToken = Str::random(48);
        $webhookSecret = Str::random(48);
        $webhookUrl = route('webhooks.telegram.receive', ['token' => $webhookToken]);

        $setWebhook = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $webhookUrl,
            'secret_token' => $webhookSecret,
            'allowed_updates' => ['message', 'edited_message', 'callback_query'],
            'drop_pending_updates' => false,
        ]);
        if (! $setWebhook->successful() || ! $setWebhook->json('ok')) {
            return back()->with('error', __('Telegram could not register the webhook: :message', [
                'message' => $setWebhook->json('description', 'unknown error'),
            ]));
        }

        DB::transaction(function () use ($workspaceId, $token, $bot, $botId, $webhookToken, $webhookSecret) {
            $account = ChannelAccount::where('channel', 'telegram')
                ->where('external_account_id', $botId)
                ->lockForUpdate()
                ->first();

            if ($account && (int) $account->workspace_id !== $workspaceId) {
                abort(422, __('This Telegram bot is already connected to another workspace.'));
            }

            $account ??= new ChannelAccount;
            $account->fill([
                'workspace_id' => $workspaceId,
                'channel' => 'telegram',
                'provider' => 'telegram',
                'external_account_id' => $botId,
                'display_name' => mb_substr('@'.($bot['username'] ?? ($bot['first_name'] ?? $botId)), 0, 128),
                'credentials' => [
                    'bot_token' => $token,
                    'webhook_secret' => $webhookSecret,
                ],
                'meta_json' => array_merge($account->meta_json ?? [], [
                    'bot_id' => $botId,
                    'username' => $bot['username'] ?? null,
                    'first_name' => $bot['first_name'] ?? null,
                    'webhook_token' => $webhookToken,
                ]),
                'status' => 'active',
            ]);
            $account->save();
        });

        return back()->with('success', __('Telegram bot connected successfully.'));
    }

    public function destroy(Request $request, ChannelAccount $channelAccount): RedirectResponse
    {
        abort_unless($channelAccount->channel === 'telegram', 404);

        $workspace = $request->user()->accessibleWorkspaces()->firstWhere('id', $channelAccount->workspace_id);
        abort_unless($workspace && $workspace->canManageMembers($request->user()), 403);

        $token = $channelAccount->credentials['bot_token'] ?? null;
        if ($token) {
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/deleteWebhook");
        }
        $channelAccount->delete();

        return back()->with('success', __('Telegram bot disconnected.'));
    }
}
