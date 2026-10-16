<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function __construct(private MediaService $mediaService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $usedBytes = $this->mediaService->usedBytes($user);
        $quotaBytes = $this->mediaService->quotaBytes($user);

        $files = Media::where('mediable_type', get_class($user))
            ->where('mediable_id', $user->id)
            ->latest()
            ->paginate(24)
            ->through(fn ($m) => [
                'id' => $m->id,
                'filename' => $m->filename,
                'mime_type' => $m->mime_type,
                'size_bytes' => $m->size_bytes,
                'url' => $m->url(),
                'collection' => $m->collection,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return Inertia::render('client/Media/Index', [
            'files' => $files,
            'usedBytes' => $usedBytes,
            'quotaBytes' => $quotaBytes,
        ]);
    }

    /**
     * Return the current user's image and video library for in-app pickers.
     */
    public function library(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:all,image,video'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $type = $validated['type'] ?? 'all';
        $search = trim($validated['search'] ?? '');

        $files = Media::query()
            ->where('mediable_type', get_class($user))
            ->where('mediable_id', $user->id)
            ->where(function ($query) {
                $query->where('mime_type', 'like', 'image/%')
                    ->orWhere('mime_type', 'like', 'video/%');
            })
            ->when($type !== 'all', fn ($query) => $query->where('mime_type', 'like', $type.'/%'))
            ->when($search !== '', fn ($query) => $query->where('filename', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(24)
            ->through(fn (Media $medium) => [
                'id' => $medium->id,
                'filename' => $medium->filename,
                'mime_type' => $medium->mime_type,
                'size_bytes' => $medium->size_bytes,
                'url' => $medium->url(),
                'created_at' => $medium->created_at->toIso8601String(),
            ]);

        return response()->json($files);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            // Allow-list of safe media types only. HTML/SVG/scripts are excluded
            // to prevent stored-XSS via files served from the app origin.
            'file' => [
                'required', 'file', 'max:51200', // 50 MB max per file
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,mp3,wav,ogg,m4a,mp4,webm,mov',
            ],
            'collection' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $usedBytes = $this->mediaService->usedBytes($user);
        $quotaBytes = $this->mediaService->quotaBytes($user);

        if ($usedBytes + $validated['file']->getSize() > $quotaBytes) {
            return response()->json(['error' => __('Storage quota exceeded.')], 422);
        }

        $media = $this->mediaService->store($validated['file'], $user, $validated['collection'] ?? 'default');

        return response()->json([
            'id' => $media->id,
            'filename' => $media->filename,
            'url' => $media->url(),
            'size_bytes' => $media->size_bytes,
        ], 201);
    }

    public function destroy(Request $request, Media $medium): JsonResponse
    {
        abort_unless($medium->mediable_type === get_class($request->user()) && $medium->mediable_id === $request->user()->id, 403);

        $medium->delete();

        return response()->json(['ok' => true]);
    }
}
