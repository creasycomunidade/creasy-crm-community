<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    /**
     * Can access admin area (dashboard, users, payments, AI requests, audit log).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Can manage sensitive settings (plans, system settings, locales, currencies, templates).
     */
    public function manageSensitive(User $user): bool
    {
        return $user->isAdmin();
    }
}
