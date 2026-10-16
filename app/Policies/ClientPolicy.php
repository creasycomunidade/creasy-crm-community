<?php

namespace App\Policies;

use App\Models\AdminUser;
use App\Models\Client;

class ClientPolicy
{
    public function viewAny(?AdminUser $user): bool
    {
        return $user?->hasPermissionTo('view_clients') ?? false;
    }

    public function view(AdminUser $user, Client $client): bool
    {
        return $user->hasPermissionTo('view_clients');
    }

    public function create(AdminUser $user): bool
    {
        return $user->hasPermissionTo('create_clients');
    }

    public function update(AdminUser $user, Client $client): bool
    {
        return $user->hasPermissionTo('update_clients');
    }

    public function delete(AdminUser $user, Client $client): bool
    {
        return $user->hasPermissionTo('delete_clients');
    }


    public function impersonate(AdminUser $user, Client $client): bool
    {
        return $user->hasPermissionTo('view_clients');
    }
}
