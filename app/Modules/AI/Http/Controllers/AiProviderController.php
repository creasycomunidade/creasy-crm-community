<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AiProviderConfig;
use App\Modules\AI\Services\AiModelCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiProviderController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isClientAdministrator(), 403);
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $configs = AiProviderConfig::where('workspace_id', $workspaceId)->get()->keyBy('provider');

        $providers = ['openai', 'anthropic', 'gemini'];
        $list = collect($providers)->map(fn ($p) => [
            'provider' => $p,
            'enabled' => $configs->get($p)?->enabled ?? false,
            'configured' => ! empty($configs->get($p)?->credentials),
            'default_model_chat' => $configs->get($p)?->default_model_chat ?? '',
        ]);

        return Inertia::render('AI/Providers/Index', [
            'providers' => $list,
            'modelCatalog' => AiModelCatalog::all(),
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        abort_unless($request->user()->isClientAdministrator(), 403);
        abort_unless(in_array($provider, ['openai', 'anthropic', 'gemini'], true), 404);
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'max:512'],
            'default_model_chat' => ['nullable', 'string', 'max:64', 'in:'.implode(',', AiModelCatalog::models($provider))],
            'default_model_embed' => ['nullable', 'string', 'max:64'],
            'enabled' => ['boolean'],
        ], [
            'api_key.string' => 'A chave da API deve ser um texto.',
            'api_key.max' => 'A chave da API não pode ter mais de :max caracteres.',
            'default_model_chat.in' => 'O modelo de IA selecionado é inválido.',
            'default_model_chat.max' => 'O nome do modelo não pode ter mais de :max caracteres.',
            'default_model_embed.max' => 'O nome do modelo de incorporação não pode ter mais de :max caracteres.',
            'enabled.boolean' => 'O campo de ativação deve ser verdadeiro ou falso.',
        ]);

        $config = AiProviderConfig::firstOrNew(['workspace_id' => $workspaceId, 'provider' => $provider]);
        $creds = $config->credentials ?? [];

        if (! empty($validated['api_key']) && ! preg_match('/^•+/', $validated['api_key'])) {
            $creds['api_key'] = $validated['api_key'];
        }

        $config->fill([
            'credentials' => $creds,
            'default_model_chat' => $validated['default_model_chat'] ?? $config->default_model_chat,
            'default_model_embed' => $validated['default_model_embed'] ?? $config->default_model_embed,
            'enabled' => (bool) $validated['enabled'],
        ])->save();

        return back()->with('success', 'Configuração do provedor '.ucfirst($provider).' salva.');
    }
}
