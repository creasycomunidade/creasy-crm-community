<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->id === $endpoint->user_id;
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->id === $endpoint->user_id;
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->id === $endpoint->user_id;
    }
}
