<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use App\Services\WebhookDispatchService;
use App\Support\PublicHttpUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookEndpointController extends Controller
{
    public function __construct(private WebhookDispatchService $dispatcher) {}

    public function index(Request $request): Response
    {
        $endpoints = $request->user()->webhookEndpoints()
            ->with(['deliveries' => fn ($q) => $q->latest()->limit(5)])
            ->latest()
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'url' => $e->url,
                'description' => $e->description,
                'events' => $e->events,
                'enabled' => $e->enabled,
                'created_at' => $e->created_at->toIso8601String(),
                'recent_deliveries' => $e->deliveries->map(fn ($d) => [
                    'id' => $d->id,
                    'event' => $d->event,
                    'response_status' => $d->response_status,
                    'delivered_at' => $d->delivered_at?->toIso8601String(),
                    'created_at' => $d->created_at->toIso8601String(),
                ]),
            ]);

        return Inertia::render('client/Webhooks/Index', [
            'endpoints' => $endpoints,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:500', PublicHttpUrl::rule()],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', 'max:100'],
        ]);

        $request->user()->webhookEndpoints()->create([
            ...$validated,
            'secret' => WebhookEndpoint::generateSecret(),
            'enabled' => true,
        ]);

        return redirect()->route('client.webhooks.index')
            ->with('success', __('Webhook endpoint created.'));
    }

    public function update(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $this->authorize('update', $webhookEndpoint);

        $validated = $request->validate([
            'url' => ['required', 'string', 'max:500', PublicHttpUrl::rule()],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', 'max:100'],
            'enabled' => ['boolean'],
        ]);

        $webhookEndpoint->update($validated);

        return redirect()->route('client.webhooks.index')
            ->with('success', __('Webhook endpoint updated.'));
    }

    public function rotateSecret(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $this->authorize('update', $webhookEndpoint);

        $secret = WebhookEndpoint::generateSecret();
        $webhookEndpoint->update(['secret' => $secret]);

        return response()->json(['secret' => $secret]);
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $this->authorize('delete', $webhookEndpoint);
        $webhookEndpoint->delete();

        return redirect()->route('client.webhooks.index')
            ->with('success', __('Webhook endpoint deleted.'));
    }

    public function testDelivery(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $this->authorize('update', $webhookEndpoint);

        $this->dispatcher->dispatchToEndpoint($webhookEndpoint, 'test.ping', [
            'message' => 'This is a test webhook delivery from '.config('app.name'),
        ]);

        return back()->with('success', __('Test webhook queued.'));
    }

    public function deliveries(Request $request, WebhookEndpoint $webhookEndpoint): Response
    {
        $this->authorize('view', $webhookEndpoint);

        $deliveries = $webhookEndpoint->deliveries()->latest()->paginate(25);

        return Inertia::render('client/Webhooks/Deliveries', [
            'endpoint' => [
                'id' => $webhookEndpoint->id,
                'url' => $webhookEndpoint->url,
            ],
            'deliveries' => $deliveries,
        ]);
    }
}
