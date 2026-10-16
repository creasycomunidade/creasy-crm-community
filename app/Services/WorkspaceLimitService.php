<?php

namespace App\Services;

use App\Models\ClientSetting;
use App\Models\User;
use App\Models\Workspace;

class WorkspaceLimitService
{
    public function limitFor(User $user): ?int
    {
        return 1;
    }

    public function countFor(User $user): int
    {
        if ($user->client_id) {
            return Workspace::where('client_id', $user->client_id)->count();
        }

        return Workspace::where('owner_id', $user->id)->count();
    }

    public function canCreate(User $user): bool
    {
        if (! $user->isClientAdministrator()) {
            return false;
        }

        $limit = $this->limitFor($user);

        return $limit === null || $this->countFor($user) < $limit;
    }
}
