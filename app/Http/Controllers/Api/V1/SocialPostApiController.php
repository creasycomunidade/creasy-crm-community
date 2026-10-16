<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Social\Jobs\PublishSocialPostJob;
use App\Modules\Social\Models\SocialAccount;
use App\Modules\Social\Models\SocialPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialPostApiController extends WorkspaceScopedController
{
    /**
     * GET /api/v1/social/accounts
     */
    public function accounts(Request $request): JsonResponse
    {
        $accounts = SocialAccount::where('workspace_id', $this->workspaceId($request))
            ->where('active', true)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'network' => $a->network,
                'name' => $a->name,
                'picture_url' => $a->picture_url,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $accounts]);
    }

    /**
     * POST /api/v1/social/posts
     * Schedule or immediately publish a post.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'title' => ['nullable', 'string', 'max:256'],
            'media_urls' => ['nullable', 'array'],
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['integer'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        $wsId = $this->workspaceId($request);

        // Verify all account IDs belong to this workspace
        $accountCount = SocialAccount::where('workspace_id', $wsId)
            ->whereIn('id', $validated['account_ids'])
            ->count();

        if ($accountCount !== count($validated['account_ids'])) {
            return response()->json(['error' => 'One or more account_ids are invalid.'], 422);
        }

        $post = SocialPost::create([
            'workspace_id' => $wsId,
            'body' => $validated['body'],
            'title' => $validated['title'] ?? null,
            'media_urls' => $validated['media_urls'] ?? [],
            'target_accounts' => $validated['account_ids'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'status' => $validated['scheduled_at'] ? 'scheduled' : 'publishing',
        ]);

        if (! $validated['scheduled_at']) {
            PublishSocialPostJob::dispatch($post->id)->onQueue('social');
        }

        return response()->json([
            'id' => $post->id,
            'status' => $post->status,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            'created_at' => $post->created_at->toIso8601String(),
        ], 201);
    }
}
