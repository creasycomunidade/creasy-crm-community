<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url'],
            'p256dh' => ['required', 'string'],
            'auth' => ['required', 'string'],
            'ua' => ['nullable', 'string', 'max:512'],
        ]);

        PushSubscription::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'endpoint' => $validated['endpoint'],
            ],
            [
                'p256dh_key' => $validated['p256dh'],
                'auth_key' => $validated['auth'],
                'ua' => $validated['ua'] ?? null,
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => ['required', 'string']]);

        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $request->endpoint)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
