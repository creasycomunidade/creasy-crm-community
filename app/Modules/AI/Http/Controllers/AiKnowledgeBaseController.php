<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Jobs\IndexDocumentJob;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Integrations\Services\GoogleDriveService;
use App\Services\StorageManager;
use App\Support\PublicHttpUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\CommonMark\CommonMarkConverter;
use Inertia\Inertia;
use Inertia\Response;

class AiKnowledgeBaseController extends Controller
{
    public function __construct(private StorageManager $storage) {}

    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $kbs = AiKnowledgeBase::where('workspace_id', $workspaceId)
            ->withCount('documents')
            ->latest()->get();

        return Inertia::render('AI/KnowledgeBases/Index', ['knowledgeBases' => $kbs]);
    }

    public function show(Request $request, AiKnowledgeBase $kb, GoogleDriveService $googleDrive): Response
    {
        $this->authorise($request, $kb);
        $kb->load('documents');

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $driveConnection = WorkspaceIntegration::where('workspace_id', $workspaceId)
            ->where('provider', 'google_drive')
            ->first();

        return Inertia::render('AI/KnowledgeBases/Show', [
            'kb' => $kb,
            'googleDrive' => [
                'configured' => $googleDrive->isConfigured(),
                'connected' => $driveConnection?->status === 'connected',
                'status' => $driveConnection?->status,
                'email' => $driveConnection?->settings['email'] ?? null,
                'last_error' => $driveConnection?->last_error,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $validated = $request->validate(['name' => ['required', 'string', 'max:128']]);
        AiKnowledgeBase::create(array_merge($validated, ['workspace_id' => $workspaceId]));

        return back()->with('success', 'Base de conhecimento criada.');
    }

    public function addDocument(Request $request, AiKnowledgeBase $kb): RedirectResponse
    {
        $this->authorise($request, $kb);

        $validated = $request->validate([
            'source_type' => ['required', 'in:file,url,text,sitemap,faq'],
            'source_ref' => [
                'nullable',
                'string',
                in_array($request->input('source_type'), ['text', 'faq'], true) ? 'max:2097152' : 'max:2048',
                'required_if:source_type,url,sitemap',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if (in_array($request->input('source_type'), ['url', 'sitemap'], true)) {
                        PublicHttpUrl::rule()($attribute, $value, $fail);
                    }
                },
            ],
            'title' => ['nullable', 'string', 'max:256'],
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            $request->validate([
                'file' => [
                    'file',
                    'max:20480',
                    'mimes:pdf,txt,md,csv,docx,doc,xlsx,xls,json',
                ],
            ]);
            $file = $request->file('file');
            $diskName = $this->storage->diskName();
            $path = $this->storage->prefixedPath('kb-docs/'.$file->hashName());
            $this->storage->disk()->putFileAs(dirname($path), $file, basename($path));
            $validated['source_ref'] = $path;
            $validated['title'] = $validated['title'] ?? $file->getClientOriginalName();
        }

        $doc = AiKbDocument::create(array_merge($validated, ['kb_id' => $kb->id, 'status' => 'pending']));

        try {
            IndexDocumentJob::dispatch($doc->id)->onQueue('ai');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['source_type' => $e->getMessage()]);
        }

        return back()->with('success', 'Documento enviado para a fila de indexação.');
    }

    public function reindex(Request $request, AiKbDocument $document): RedirectResponse
    {
        $kb = $document->load('knowledgeBase')->knowledgeBase;
        $this->authorise($request, $kb);
        $document->update(['status' => 'pending']);

        try {
            IndexDocumentJob::dispatch($document->id)->onQueue('ai');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        return back()->with('success', 'Reindexação enviada para a fila.');
    }

    public function documentContent(Request $request, AiKbDocument $document): JsonResponse
    {
        $kb = $document->load('knowledgeBase')->knowledgeBase;
        $this->authorise($request, $kb);

        $format = $this->documentFormat($document);
        $content = $document->content_override;
        if ($content === null && ! $document->external_provider && in_array($document->source_type, ['text', 'faq'], true)) {
            $content = $document->source_type === 'faq'
                ? $this->readableFaq((string) $document->source_ref)
                : (string) $document->source_ref;
        }
        if ($content === null && $format === 'markdown') {
            $content = $this->readOriginalMarkdown($document);
        }
        if ($content === null) {
            $content = $this->rebuildIndexedContent($document);
        }

        return response()->json([
            'content' => $content,
            'has_override' => $document->content_override !== null,
            'source_ref' => in_array($document->source_type, ['url', 'sitemap'], true)
                ? (string) $document->source_ref
                : null,
            'source_type' => $document->source_type,
            'external_provider' => $document->external_provider,
            'status' => $document->status,
            'format' => $format,
        ]);
    }

    public function markdownPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:2097152'],
        ]);
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return response()->json([
            'html' => (string) $converter->convert((string) ($validated['content'] ?? '')),
        ]);
    }

    public function updateDocument(Request $request, AiKbDocument $document): RedirectResponse
    {
        $kb = $document->load('knowledgeBase')->knowledgeBase;
        $this->authorise($request, $kb);

        $isDirectText = ! $document->external_provider && in_array($document->source_type, ['text', 'faq'], true);
        $rules = [
            'title' => ['required', 'string', 'max:256'],
            'restore_source' => ['sometimes', 'boolean'],
        ];
        $restoreSource = $request->boolean('restore_source');
        if (! $restoreSource) {
            $rules['content'] = [$isDirectText ? 'required_without:source_ref' : 'required', 'nullable', 'string', 'max:2097152'];
        }
        $canEditSource = ! $document->external_provider && in_array($document->source_type, ['text', 'faq', 'url', 'sitemap'], true);
        if ($canEditSource) {
            $rules['source_ref'] = [
                $isDirectText && ! $restoreSource ? 'required_without:content' : 'nullable', 'string', 'max:2097152',
                function (string $attribute, mixed $value, \Closure $fail) use ($document): void {
                    if (in_array($document->source_type, ['url', 'sitemap'], true)) {
                        PublicHttpUrl::rule()($attribute, $value, $fail);
                    }
                },
            ];
        }

        $validated = $request->validate($rules);
        $directText = $validated['content'] ?? $validated['source_ref'] ?? $document->source_ref;
        $document->update([
            'title' => $validated['title'],
            'source_ref' => $isDirectText && ! $restoreSource
                ? $directText
                : ($canEditSource && array_key_exists('source_ref', $validated) ? $validated['source_ref'] : $document->source_ref),
            'content_override' => $restoreSource || $isDirectText ? null : $validated['content'],
            'content_override_updated_at' => ! $restoreSource && ! $isDirectText ? now() : null,
            'status' => 'pending',
            'sync_error' => null,
        ]);

        try {
            IndexDocumentJob::dispatch($document->id)->onQueue('ai');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        return back()->with('success', 'Documento atualizado e enviado para reindexação.');
    }

    /** Reconstruct the indexed text while removing the overlap between adjacent chunks. */
    private function rebuildIndexedContent(AiKbDocument $document): string
    {
        $chunks = $document->chunks()->orderBy('ord')->pluck('content')->map(fn ($value) => trim((string) $value));
        if ($chunks->isEmpty()) {
            return '';
        }

        $result = preg_split('/\s+/u', $chunks->shift()) ?: [];
        foreach ($chunks as $chunk) {
            $words = preg_split('/\s+/u', $chunk) ?: [];
            $maximum = min(150, count($result), count($words));
            $overlap = 0;
            for ($length = $maximum; $length > 0; $length--) {
                if (array_slice($result, -$length) === array_slice($words, 0, $length)) {
                    $overlap = $length;
                    break;
                }
            }
            array_push($result, ...array_slice($words, $overlap));
        }

        return trim(implode(' ', $result));
    }

    private function documentFormat(AiKbDocument $document): string
    {
        $extension = strtolower(pathinfo((string) $document->source_ref, PATHINFO_EXTENSION));
        $titleExtension = strtolower(pathinfo((string) $document->title, PATHINFO_EXTENSION));

        return in_array($extension, ['md', 'markdown'], true)
            || in_array($titleExtension, ['md', 'markdown'], true)
            ? 'markdown'
            : 'plain_text';
    }

    private function readOriginalMarkdown(AiKbDocument $document): ?string
    {
        if ($document->source_type !== 'file' || $document->external_provider) {
            return null;
        }

        $path = (string) $document->source_ref;
        $disk = $this->storage->disk();
        if ($path === '' || ! $disk->exists($path)) {
            return null;
        }

        $content = (string) $disk->get($path);

        return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
    }

    private function readableFaq(string $source): string
    {
        $pairs = json_decode($source, true);
        if (! is_array($pairs)) {
            return $source;
        }

        return collect($pairs)
            ->filter(fn ($pair) => is_array($pair))
            ->map(function (array $pair): string {
                $question = trim((string) ($pair['question'] ?? ''));
                $answer = trim((string) ($pair['answer'] ?? ''));

                return trim("Pergunta: {$question}\nResposta: {$answer}");
            })
            ->filter()
            ->implode("\n\n");
    }

    public function destroyDocument(Request $request, AiKbDocument $document): RedirectResponse
    {
        $kb = $document->load('knowledgeBase')->knowledgeBase;
        $this->authorise($request, $kb);
        $document->chunks()->delete();
        $document->delete();

        return back()->with('success', 'Documento removido.');
    }

    private function authorise(Request $request, AiKnowledgeBase $kb): void
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless((int) $kb->workspace_id === (int) $workspaceId, 403);
    }
}
