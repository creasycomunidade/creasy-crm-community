<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Whatsapp\Jobs\TemplateSyncJob;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Modules\Whatsapp\Services\CloudApiClient;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        // Collect phone numbers for the workspace to power the phone-number filter
        $wabaIds = WhatsappBusinessAccount::where('workspace_id', $workspaceId)->pluck('waba_id');
        $wabaIdMap = WhatsappBusinessAccount::where('workspace_id', $workspaceId)->pluck('waba_id', 'id');

        $phoneNumbers = WhatsappPhoneNumber::whereIn('waba_id_fk', $wabaIdMap->keys())
            ->get()
            ->map(fn ($p) => [
                'phone_number_id' => $p->phone_number_id,
                'display_phone'   => $p->display_phone,
                'verified_name'   => $p->verified_name,
                'waba_id'         => $wabaIdMap[$p->waba_id_fk] ?? null,
            ]);

        $templates = WhatsappTemplate::where('workspace_id', $workspaceId)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->phone_number_id, function ($q) use ($request, $wabaIdMap, $phoneNumbers) {
                $phone = $phoneNumbers->firstWhere('phone_number_id', $request->phone_number_id);
                if ($phone) {
                    $q->where('waba_id', $phone['waba_id']);
                }
            })
            ->latest()->get();

        return Inertia::render('Whatsapp/Templates/Index', [
            'templates'    => $templates,
            'phoneNumbers' => $phoneNumbers,
            'filters'      => $request->only('status', 'search', 'phone_number_id'),
        ]);
    }

    public function create(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $wabaIdMap = WhatsappBusinessAccount::where('workspace_id', $workspaceId)->pluck('waba_id', 'id');

        $phoneNumbers = WhatsappPhoneNumber::whereIn('waba_id_fk', $wabaIdMap->keys())
            ->get()
            ->map(fn ($p) => [
                'phone_number_id' => $p->phone_number_id,
                'display_phone'   => $p->display_phone,
                'verified_name'   => $p->verified_name,
                'waba_id'         => $wabaIdMap[$p->waba_id_fk] ?? null,
            ]);

        return Inertia::render('Whatsapp/Templates/Editor', [
            'template'     => null,
            'phoneNumbers' => $phoneNumbers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        // If a specific phone number was selected, resolve its WABA; otherwise fall back to first WABA
        $waba = null;
        if ($request->filled('phone_number_id')) {
            $wabaIdMap = WhatsappBusinessAccount::where('workspace_id', $workspaceId)->pluck('waba_id', 'id');
            $phone = WhatsappPhoneNumber::whereIn('waba_id_fk', $wabaIdMap->keys())
                ->where('phone_number_id', $request->phone_number_id)
                ->first();
            if ($phone) {
                $waba = WhatsappBusinessAccount::find($phone->waba_id_fk);
            }
        }
        if (!$waba) {
            $waba = WhatsappBusinessAccount::where('workspace_id', $workspaceId)->firstOrFail();
        }

        $validated = $request->validate($this->templateRules());

        $this->assertComponentMultiplicity($validated['components']);

        $metaPayload = $this->buildMetaPayload($validated);

        $template = WhatsappTemplate::create([
            'workspace_id' => $workspaceId,
            'waba_id' => $waba->waba_id,
            'name' => $validated['name'],
            'language' => $validated['language'],
            'category' => $validated['category'],
            'components' => $validated['components'],
            'status' => 'PENDING',
        ]);

        $client = CloudApiClient::forWorkspace($workspaceId);
        if ($client) {
            $resp = $client->submitTemplate($waba->waba_id, $metaPayload);

            if ($resp->successful()) {
                $template->update(['meta_template_id' => $resp->json('id')]);
            } else {
                $metaError = $resp->json('error.error_user_msg')
                    ?? $resp->json('error.message')
                    ?? 'Meta rejected the template (HTTP '.$resp->status().')';

                Log::warning('WhatsApp template submission failed', [
                    'workspace_id' => $workspaceId,
                    'template_id' => $template->id,
                    'meta_error' => $metaError,
                    'payload' => $metaPayload,
                ]);

                $template->update(['status' => 'REJECTED', 'rejection_reason' => $metaError]);

                return redirect()->route('client.whatsapp.templates.index')
                    ->with('error', 'O modelo foi salvo, mas a Meta o rejeitou: '.$metaError);
            }
        }

        return redirect()->route('client.whatsapp.templates.index')
            ->with('success', 'Template submitted to Meta for approval.');
    }

    public function edit(Request $request, WhatsappTemplate $template): Response
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless($template->workspace_id === $workspaceId, 403);

        $wabaIdMap = WhatsappBusinessAccount::where('workspace_id', $workspaceId)->pluck('waba_id', 'id');

        $phoneNumbers = WhatsappPhoneNumber::whereIn('waba_id_fk', $wabaIdMap->keys())
            ->get()
            ->map(fn ($p) => [
                'phone_number_id' => $p->phone_number_id,
                'display_phone'   => $p->display_phone,
                'verified_name'   => $p->verified_name,
                'waba_id'         => $wabaIdMap[$p->waba_id_fk] ?? null,
            ]);

        return Inertia::render('Whatsapp/Templates/Editor', [
            'template'     => $template->only('id', 'name', 'language', 'category', 'status', 'components'),
            'phoneNumbers' => $phoneNumbers,
        ]);
    }

    public function update(Request $request, WhatsappTemplate $template): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless($template->workspace_id === $workspaceId, 403);

        // Name and language are immutable on Meta once a template exists — keep the originals.
        $validated = $request->validate($this->templateRules(nameRequired: false));
        $validated['name'] = $template->name;
        $validated['language'] = $template->language;

        $this->assertComponentMultiplicity($validated['components']);

        $metaPayload = $this->buildMetaPayload($validated);

        $template->update([
            'category'   => $validated['category'],
            'components' => $validated['components'],
        ]);

        $client = CloudApiClient::forWorkspace($workspaceId);
        if ($client) {
            // Editing an existing Meta template uses its template id; otherwise (re)submit as new.
            $resp = $template->meta_template_id
                ? $client->editTemplate($template->meta_template_id, $metaPayload)
                : $client->submitTemplate($template->waba_id, $metaPayload);

            if ($resp->successful()) {
                $template->update([
                    'status' => 'PENDING',
                    'rejection_reason' => null,
                    'meta_template_id' => $template->meta_template_id ?? $resp->json('id'),
                ]);
            } else {
                $metaError = $resp->json('error.error_user_msg')
                    ?? $resp->json('error.message')
                    ?? 'Meta rejected the template (HTTP '.$resp->status().')';

                Log::warning('WhatsApp template edit failed', [
                    'workspace_id' => $workspaceId,
                    'template_id' => $template->id,
                    'meta_error' => $metaError,
                    'payload' => $metaPayload,
                ]);

                $template->update(['status' => 'REJECTED', 'rejection_reason' => $metaError]);

                return redirect()->route('client.whatsapp.templates.index')
                    ->with('error', 'O modelo foi salvo, mas a Meta rejeitou a alteração: '.$metaError);
            }
        }

        return redirect()->route('client.whatsapp.templates.index')
            ->with('success', 'Modelo atualizado e reenviado à Meta para aprovação.');
    }

    public function destroy(Request $request, WhatsappTemplate $template): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless($template->workspace_id === $workspaceId, 403);

        $metaWarning = null;
        $client = CloudApiClient::forWorkspace($workspaceId);
        if ($client) {
            try {
                $resp = $client->deleteTemplate($template->waba_id, $template->name);
                if (! $resp->successful()) {
                    $metaWarning = $resp->json('error.error_user_msg')
                        ?? $resp->json('error.message')
                        ?? 'Meta returned HTTP '.$resp->status();
                    Log::warning('WhatsApp template delete failed on Meta', [
                        'workspace_id' => $workspaceId,
                        'template_id' => $template->id,
                        'meta_error' => $metaWarning,
                    ]);
                }
            } catch (\Throwable $e) {
                $metaWarning = $e->getMessage();
                Log::warning('WhatsApp template delete threw', [
                    'workspace_id' => $workspaceId,
                    'template_id' => $template->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $name = $template->name;
        $template->delete();

        if ($metaWarning) {
            return back()->with('error', "Deleted “{$name}” locally, but Meta reported: {$metaWarning}");
        }

        return back()->with('success', "Modelo “{$name}” excluído.");
    }

    /**
     * Upload a header media file and return the Meta resumable-upload handle.
     * The frontend stores this handle and includes it in components[].example.header_handle.
     */
    public function uploadMedia(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $waba = WhatsappBusinessAccount::where('workspace_id', $workspaceId)->firstOrFail();

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,mp4,pdf',
                'max:102400', // 100 MB ceiling; Meta specific limits enforced by their API
            ],
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $format = match (true) {
            str_starts_with($mime, 'image/') => 'IMAGE',
            str_starts_with($mime, 'video/') => 'VIDEO',
            $mime === 'application/pdf' => 'DOCUMENT',
            default => 'IMAGE',
        };

        $creds = $waba->credentials ?? [];
        $token = $creds['system_user_token'] ?? '';
        if (empty($token)) {
            $token = CredentialResolver::system()->meta()?->systemUserToken() ?? '';
        }

        $appId = CredentialResolver::system()->meta()?->appId() ?? '';

        if (empty($token) || empty($appId)) {
            return response()->json(['error' => 'Missing Meta credentials (system user token or app ID).'], 422);
        }

        try {
            $handle = CloudApiClient::resumableUpload($appId, $token, $file->getRealPath(), $mime);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp header media upload failed', [
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['handle' => $handle, 'format' => $format]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $waba = WhatsappBusinessAccount::where('workspace_id', $workspaceId)->first();

        if (! $waba) {
            return back()->withErrors(['sync' => 'Conecte uma conta do WhatsApp Business antes de sincronizar os modelos.']);
        }

        try {
            (new TemplateSyncJob($waba->id))->handle();
        } catch (HttpConnectionException $e) {
            Log::warning('WhatsApp template sync failed (TLS/network)', [
                'workspace_id' => $workspaceId,
                'waba_id' => $waba->waba_id,
                'exception' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'sync' => 'Could not reach Meta (TLS/certificate). Set HTTP_CLIENT_CA_PATH in .env to a valid cacert.pem file, or fix curl.cainfo in php.ini. See .env.example.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp template sync failed', [
                'workspace_id' => $workspaceId,
                'waba_id' => $waba->waba_id,
                'exception' => $e->getMessage(),
            ]);

            return back()->withErrors(['sync' => 'Não foi possível sincronizar os modelos da Meta: '.$e->getMessage()]);
        }

        $count = WhatsappTemplate::where('workspace_id', $workspaceId)->count();

        return back()->with('success', "Synced templates from Meta ({$count} in your workspace).");
    }

    /**
     * Shared validation rules for creating and editing templates.
     * On edit the name is locked to the original, so it need not be required.
     */
    private function templateRules(bool $nameRequired = true): array
    {
        return [
            'phone_number_id' => ['nullable', 'string'],
            'name' => [$nameRequired ? 'required' : 'nullable', 'string', 'regex:/^[a-z0-9_]+$/', 'max:128'],
            'language' => [$nameRequired ? 'required' : 'nullable', 'string', 'max:8'],
            'category' => ['required', 'in:MARKETING,UTILITY,AUTHENTICATION'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.type' => ['required', 'string', 'in:HEADER,BODY,FOOTER,BUTTONS'],
            'components.*.format' => ['nullable', 'string', 'in:TEXT,IMAGE,VIDEO,DOCUMENT'],
            'components.*.text' => ['nullable', 'string', 'max:1024'],
            'components.*.example' => ['nullable', 'array'],
            'components.*.example.header_text' => ['nullable', 'array'],
            'components.*.example.header_text.*' => ['nullable', 'string', 'max:60'],
            'components.*.example.header_handle' => ['nullable', 'array'],
            'components.*.example.header_handle.*' => ['nullable', 'string'],
            'components.*.example.body_text' => ['nullable', 'array'],
            'components.*.example.body_text.*' => ['nullable', 'array'],
            'components.*.example.body_text.*.*' => ['nullable', 'string'],
            'components.*.buttons' => ['nullable', 'array', 'max:10'],
            'components.*.buttons.*.type' => ['required_with:components.*.buttons', 'string', 'in:QUICK_REPLY,URL,PHONE_NUMBER'],
            'components.*.buttons.*.text' => ['required_with:components.*.buttons', 'string', 'max:25'],
            'components.*.buttons.*.url' => ['nullable', 'string', 'max:2000'],
            'components.*.buttons.*.phone_number' => ['nullable', 'string', 'max:20'],
            'components.*.buttons.*.example' => ['nullable', 'array'],
        ];
    }

    /**
     * Enforce WhatsApp's component multiplicity: exactly one BODY, at most one
     * HEADER / FOOTER / BUTTONS block.
     */
    private function assertComponentMultiplicity(array $components): void
    {
        $typeCounts = array_count_values(array_column($components, 'type'));
        if (($typeCounts['BODY'] ?? 0) !== 1) {
            throw ValidationException::withMessages(['components' => 'Exactly one BODY component is required.']);
        }
        foreach (['HEADER', 'FOOTER', 'BUTTONS'] as $single) {
            if (($typeCounts[$single] ?? 0) > 1) {
                throw ValidationException::withMessages(['components' => "Only one {$single} component is allowed."]);
            }
        }
    }

    /**
     * Transform the validated component array into the exact shape Meta's API expects,
     * stripping null/empty fields that would cause a validation error on their end.
     */
    private function buildMetaPayload(array $validated): array
    {
        $components = [];

        foreach ($validated['components'] as $comp) {
            $type = $comp['type'];

            if ($type === 'BUTTONS') {
                $buttons = [];
                foreach ($comp['buttons'] ?? [] as $btn) {
                    $b = ['type' => $btn['type'], 'text' => $btn['text']];
                    if ($btn['type'] === 'URL') {
                        $b['url'] = $btn['url'] ?? '';
                        if (! empty($btn['example'])) {
                            $b['example'] = $btn['example'];
                        }
                    } elseif ($btn['type'] === 'PHONE_NUMBER') {
                        $b['phone_number'] = $btn['phone_number'] ?? '';
                    }
                    $buttons[] = $b;
                }
                if (! empty($buttons)) {
                    $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
                }

                continue;
            }

            $built = ['type' => $type];

            if ($type === 'HEADER') {
                $format = $comp['format'] ?? 'TEXT';
                $built['format'] = $format;

                if ($format === 'TEXT') {
                    $built['text'] = $comp['text'] ?? '';
                    $headerExamples = $comp['example']['header_text'] ?? [];
                    if (! empty($headerExamples)) {
                        $built['example'] = ['header_text' => array_values($headerExamples)];
                    }
                } else {
                    // IMAGE / VIDEO / DOCUMENT — no text, needs header_handle example
                    $handles = $comp['example']['header_handle'] ?? [];
                    if (! empty($handles)) {
                        $built['example'] = ['header_handle' => array_values($handles)];
                    }
                }
            } elseif ($type === 'BODY') {
                $built['text'] = $comp['text'] ?? '';
                $bodyExamples = $comp['example']['body_text'] ?? [];
                if (! empty($bodyExamples)) {
                    $built['example'] = ['body_text' => $bodyExamples];
                }
            } elseif ($type === 'FOOTER') {
                $built['text'] = $comp['text'] ?? '';
            }

            $components[] = $built;
        }

        return [
            'name' => $validated['name'],
            'language' => $validated['language'],
            'category' => $validated['category'],
            'components' => $components,
        ];
    }
}
