<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\AI\Jobs\IndexDocumentJob;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Services\StorageManager;
use App\Support\PublicHttpUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiKnowledgeBaseApiController extends WorkspaceScopedController
{
    public function __construct(private StorageManager $storage) {}

    /**
     * GET /api/v1/ai/knowledge-bases
     */
    public function index(Request $request): JsonResponse
    {
        $kbs = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))
            ->withCount('documents')
            ->latest('id')
            ->get()
            ->map(fn ($kb) => $this->formatKb($kb));

        return response()->json(['data' => $kbs]);
    }

    /**
     * POST /api/v1/ai/knowledge-bases
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'embedding_model' => ['nullable', 'string'],
        ]);

        $kb = AiKnowledgeBase::create(array_merge($validated, [
            'workspace_id' => $this->workspaceId($request),
        ]));

        return response()->json($this->formatKb($kb), 201);
    }

    /**
     * GET /api/v1/ai/knowledge-bases/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))
            ->with('documents')
            ->find($id);

        if (! $kb) {
            return response()->json(['error' => 'Knowledge base not found.'], 404);
        }

        return response()->json(array_merge($this->formatKb($kb), [
            'documents' => $kb->documents->map(fn ($d) => $this->formatDoc($d)),
        ]));
    }

    /**
     * POST /api/v1/ai/knowledge-bases/{id}/documents
     */
    public function addDocument(Request $request, int $id): JsonResponse
    {
        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $kb) {
            return response()->json(['error' => 'Knowledge base not found.'], 404);
        }

        $validated = $request->validate([
            'source_type' => ['required', 'string', 'in:file,url,text,sitemap,faq'],
            'source_ref' => [
                'nullable',
                'string',
                'max:512',
                'required_if:source_type,url,sitemap',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if (in_array($request->input('source_type'), ['url', 'sitemap'], true)) {
                        PublicHttpUrl::rule()($attribute, $value, $fail);
                    }
                },
            ],
            'title' => ['nullable', 'string', 'max:256'],
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $this->storage->prefixedPath('kb-docs/'.$file->hashName());
            $this->storage->disk()->putFileAs(dirname($path), $file, basename($path));
            $validated['source_ref'] = $path;
            $validated['title'] = $validated['title'] ?? $file->getClientOriginalName();
        }

        if (empty($validated['source_ref']) && $validated['source_type'] !== 'text') {
            return response()->json(['error' => 'source_ref is required for source_type '.$validated['source_type'].'.'], 422);
        }

        $doc = AiKbDocument::create(array_merge($validated, ['kb_id' => $kb->id, 'status' => 'pending']));
        IndexDocumentJob::dispatch($doc->id)->onQueue('ai');

        return response()->json($this->formatDoc($doc), 201);
    }

    /**
     * DELETE /api/v1/ai/knowledge-bases/{kbId}/documents/{docId}
     */
    public function destroyDocument(Request $request, int $kbId, int $docId): JsonResponse
    {
        $kb = AiKnowledgeBase::where('workspace_id', $this->workspaceId($request))->find($kbId);

        if (! $kb) {
            return response()->json(['error' => 'Knowledge base not found.'], 404);
        }

        $doc = AiKbDocument::where('kb_id', $kb->id)->find($docId);
        if (! $doc) {
            return response()->json(['error' => 'Document not found.'], 404);
        }

        $doc->chunks()->delete();
        $doc->delete();

        return response()->json(['ok' => true]);
    }

    private function formatKb(AiKnowledgeBase $kb): array
    {
        return [
            'id' => $kb->id,
            'name' => $kb->name,
            'embedding_model' => $kb->embedding_model,
            'status' => $kb->status,
            'workspace_id' => $kb->workspace_id,
            'documents_count' => $kb->documents_count ?? null,
            'created_at' => $kb->created_at->toIso8601String(),
        ];
    }

    private function formatDoc(AiKbDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'kb_id' => $doc->kb_id,
            'source_type' => $doc->source_type,
            'source_ref' => $doc->source_ref,
            'title' => $doc->title,
            'status' => $doc->status,
            'created_at' => $doc->created_at->toIso8601String(),
        ];
    }
}
