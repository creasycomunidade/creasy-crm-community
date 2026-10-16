<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WebchatChannelController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);

        $accounts = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('channel', 'webchat')
            ->where(function ($query) {
                $query->whereNull('meta_json->webchat->archived')
                    ->orWhere('meta_json->webchat->archived', false);
            })
            ->latest()
            ->get(['id', 'display_name', 'status', 'external_account_id', 'meta_json', 'created_at'])
            ->map(fn (ChannelAccount $account) => array_merge($account->toArray(), [
                'embed_code' => '<script src="'.route('public.webchat.script', ['key' => $account->external_account_id]).'" async></script>',
                'ai_chatbot_id' => data_get($account->meta_json, 'ai_chatbot_id'),
            ]));

        return Inertia::render('Inbox/Webchat/Index', [
            'accounts' => $accounts,
            'chatbots' => AiChatbot::query()
                ->where('workspace_id', $workspaceId)
                ->where('enabled', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'humanAgents' => User::forWorkspace($workspaceId)
                ->where('status', User::STATUS_ACTIVE)
                ->where('client_role', User::CLIENT_ROLE_STAFF)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $data = $this->validated($request, $workspaceId);

        ChannelAccount::create([
            'workspace_id' => $workspaceId,
            'channel' => 'webchat',
            'provider' => 'respondesk',
            'display_name' => $data['name'],
            'external_account_id' => Str::random(40),
            'status' => $data['enabled'] ? 'active' : 'inactive',
            'meta_json' => $this->meta($data),
        ]);

        return back()->with('success', 'Canal de chat do site criado. Copie o código e instale no site.');
    }

    public function update(Request $request, ChannelAccount $channelAccount): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $this->authorise($channelAccount, $workspaceId);
        $data = $this->validated($request, $workspaceId);

        $channelAccount->update([
            'display_name' => $data['name'],
            'status' => $data['enabled'] ? 'active' : 'inactive',
            'meta_json' => $this->meta($data, $channelAccount->meta_json ?? []),
        ]);

        return back()->with('success', 'Canal de chat do site atualizado.');
    }

    public function destroy(Request $request, ChannelAccount $channelAccount): RedirectResponse
    {
        $this->authorise($channelAccount, $this->workspaceId($request));
        if ($channelAccount->conversations()->exists()) {
            $meta = $channelAccount->meta_json ?? [];
            data_set($meta, 'webchat.archived', true);
            $channelAccount->update(['status' => 'inactive', 'meta_json' => $meta]);
        } else {
            $channelAccount->delete();
        }

        return back()->with('success', 'Canal de chat do site removido. As conversas anteriores foram preservadas.');
    }

    private function validated(Request $request, int $workspaceId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'title' => ['required', 'string', 'max:80'],
            'welcome_message' => ['required', 'string', 'max:500'],
            'input_placeholder' => ['required', 'string', 'max:120'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['required', Rule::in(['bottom_right', 'bottom_left'])],
            'allowed_domains' => ['nullable', 'array', 'max:30'],
            'allowed_domains.*' => ['string', 'max:255'],
            'require_name' => ['required', 'boolean'],
            'require_email' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
            'chatbot_id' => ['nullable', 'integer'],
            'timezone' => ['required', 'timezone'],
            'business_hours' => ['required', 'array'],
            'business_hours.*.enabled' => ['required', 'boolean'],
            'business_hours.*.start' => ['nullable', 'date_format:H:i'],
            'business_hours.*.end' => ['nullable', 'date_format:H:i'],
            'outside_hours_enabled' => ['required', 'boolean'],
            'outside_hours_message' => ['nullable', 'string', 'max:2000'],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => ['integer'],
        ]);

        if (! empty($data['chatbot_id'])) {
            AiChatbot::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($data['chatbot_id'])
                ->where('enabled', true)
                ->firstOrFail();
        }

        $data['allowed_user_ids'] = User::forWorkspace($workspaceId)
            ->where('status', User::STATUS_ACTIVE)
            ->whereIn('id', $data['allowed_user_ids'] ?? [])
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $data['allowed_domains'] = collect($data['allowed_domains'] ?? [])
            ->map(fn ($domain) => $this->normaliseDomain((string) $domain))
            ->filter()->unique()->values()->all();

        return $data;
    }

    private function meta(array $data, array $current = []): array
    {
        return array_merge($current, [
            'ai_chatbot_id' => $data['chatbot_id'] ?: null,
            'webchat' => [
                'title' => trim($data['title']),
                'welcome_message' => trim($data['welcome_message']),
                'input_placeholder' => trim($data['input_placeholder']),
                'primary_color' => strtolower($data['primary_color']),
                'position' => $data['position'],
                'allowed_domains' => $data['allowed_domains'],
                'require_name' => (bool) $data['require_name'],
                'require_email' => (bool) $data['require_email'],
            ],
            'operations' => [
                'timezone' => $data['timezone'],
                'business_hours' => $data['business_hours'],
                'human_access_mode' => 'selected',
                'allowed_user_ids' => $data['allowed_user_ids'],
                'outside_hours_enabled' => (bool) $data['outside_hours_enabled'],
                'outside_hours_message' => trim((string) ($data['outside_hours_message'] ?? '')),
                'outside_hours_cooldown_hours' => 12,
            ],
        ]);
    }

    private function normaliseDomain(string $domain): string
    {
        $domain = trim(strtolower($domain));
        if ($domain === '') {
            return '';
        }

        $host = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST);

        return preg_replace('/^www\./', '', (string) $host) ?: '';
    }

    private function authorise(ChannelAccount $account, int $workspaceId): void
    {
        abort_unless((int) $account->workspace_id === $workspaceId && $account->channel === 'webchat', 404);
    }

    private function workspaceId(Request $request): int
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isClientAdministrator(), 403);

        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }
}
