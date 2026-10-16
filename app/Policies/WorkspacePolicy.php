<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->isClientAdministrator();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->canManageMembers($user);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }
}
