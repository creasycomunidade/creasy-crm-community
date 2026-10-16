<?php

namespace App\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookDispatchService
{
    /**
     * Dispatch an event to all matching webhook endpoints for a user.
     */
    public function dispatch(User $user, string $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::where('user_id', $user->id)
            ->where('enabled', true)
            ->get();

        foreach ($endpoints as $endpoint) {
            if ($endpoint->listensTo($event)) {
                DispatchWebhookJob::dispatch($endpoint, $event, $payload);
            }
        }
    }

    /**
     * Dispatch an event to a specific endpoint.
     */
    public function dispatchToEndpoint(WebhookEndpoint $endpoint, string $event, array $payload): void
    {
        DispatchWebhookJob::dispatch($endpoint, $event, $payload);
    }
}
