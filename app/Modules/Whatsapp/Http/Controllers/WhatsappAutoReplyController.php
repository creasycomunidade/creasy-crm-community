<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Whatsapp\Models\WhatsappAutoReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappAutoReplyController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $rules = WhatsappAutoReply::where('workspace_id', $workspaceId)->orderBy('priority')->get();
        $channelAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->whereIn('channel', ['whatsapp', 'webchat'])
            ->where('status', 'active')
            ->orderBy('channel')->orderBy('display_name')
            ->get(['id', 'channel', 'display_name']);

        return Inertia::render('Whatsapp/AutoReplies/Index', compact('rules', 'channelAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $validated = $this->validated($request);
        $this->validateRegexKeywords($validated);
        $this->validateChannelAccount($validated, (int) $workspaceId);

        WhatsappAutoReply::create(array_merge($validated, ['workspace_id' => $workspaceId]));

        return back()->with('success', 'Regra de resposta automática criada.');
    }

    public function update(Request $request, WhatsappAutoReply $autoReply): RedirectResponse
    {
        $this->authorise($request, $autoReply);
        $validated = $this->validated($request, true);
        $this->validateRegexKeywords($validated);
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $this->validateChannelAccount($validated, (int) $workspaceId, $autoReply);

        $autoReply->update($validated);

        return back()->with('success', 'Regra de resposta automática atualizada.');
    }

    public function destroy(Request $request, WhatsappAutoReply $autoReply): RedirectResponse
    {
        $this->authorise($request, $autoReply);
        $autoReply->delete();

        return back()->with('success', 'Regra de resposta automática excluída.');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'channel_account_id' => [$partial ? 'sometimes' : 'nullable', 'nullable', 'integer'],
            'trigger_type' => [$sometimes, 'in:keyword,welcome,away,out_of_hours'],
            'match_mode' => [$sometimes, 'in:exact,contains,regex'],
            'keywords' => ['nullable', 'array'],
            'schedule_json' => ['nullable', 'array'],
            'response_kind' => [$sometimes, 'in:text,template,media,flow'],
            'payload_json' => [$sometimes, 'array'],
            'enabled' => ['boolean'],
            'priority' => ['integer', 'min:0'],
        ]);
    }

    private function validateRegexKeywords(array $validated): void
    {
        if (($validated['match_mode'] ?? null) !== 'regex') return;

        foreach ($validated['keywords'] ?? [] as $keyword) {
            if (! WhatsappAutoReply::isValidRegex((string) $keyword)) {
                throw ValidationException::withMessages([
                    'keywords' => "Expressão regular inválida: {$keyword}",
                ]);
            }
        }
    }

    private function validateChannelAccount(array $validated, int $workspaceId, ?WhatsappAutoReply $current = null): void
    {
        $accountId = array_key_exists('channel_account_id', $validated)
            ? $validated['channel_account_id']
            : $current?->channel_account_id;

        if (empty($accountId)) return;

        $account = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($accountId)
            ->whereIn('channel', ['whatsapp', 'webchat'])
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'channel_account_id' => 'Selecione um canal válido deste espaço de trabalho.',
            ]);
        }

        $responseKind = $validated['response_kind'] ?? $current?->response_kind ?? 'text';
        if ($account->channel === 'webchat' && $responseKind !== 'text') {
            throw ValidationException::withMessages([
                'response_kind' => 'No Chat do site, use resposta em texto.',
            ]);
        }
    }

    private function authorise(Request $request, WhatsappAutoReply $rule): void
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless((int) $rule->workspace_id === (int) $workspaceId, 403);
    }
}