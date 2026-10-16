<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;

class ClientWorkspaceService
{
    /**
     * Ensure the client has at least one workspace (default: client name, no owner until a user is linked).
     */
    public function ensureWorkspaceExists(Client $client): Workspace
    {
        $existing = $client->workspaces()->orderBy('id')->first();
        if ($existing) {
            return $existing;
        }

        return Workspace::create([
            'client_id' => $client->id,
            'name' => $client->name,
            'default_locale' => 'pt',
            'currency_code' => $client->base_currency,
        ]);
    }

    /**
     * Attach a client-scoped user to the client's workspace(s): claim unowned default workspace for the first
     * administrator, add others as members, and set primary workspace_id when missing.
     */
    public function syncClientUser(User $user): void
    {
        if (! $user->client_id) {
            return;
        }

        $client = $user->client;
        if (! $client) {
            return;
        }

        $this->ensureWorkspaceExists($client);

        $workspaces = $client->workspaces()->orderBy('id')->get();

        if ($user->isClientAdministrator()) {
            $orphan = $workspaces->firstWhere('owner_id', null);
            if ($orphan) {
                $orphan->forceFill(['owner_id' => $user->id])->saveQuietly();
                if (! $orphan->members()->where('user_id', $user->id)->exists()) {
                    $orphan->members()->attach($user->id, ['role' => 'owner']);
                }
            }
        }

        $user->refresh();

        if (! $user->workspace_id) {
            $primary = $user->accessibleWorkspaces()->first();
            if ($primary) {
                $user->forceFill(['workspace_id' => $primary->id])->saveQuietly();
            }
        }
    }
}
