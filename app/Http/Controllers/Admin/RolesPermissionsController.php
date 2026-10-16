<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RolesPermissionsController extends Controller
{
    public function index(Request $request): Response
    {
        $rolesQuery = Role::query()
            ->with('permissions:id')
            ->withCount(['permissions', 'adminUsers'])
            ->orderBy('name');

        if ($request->filled('role_search')) {
            $q = $request->role_search;
            $rolesQuery->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('key', 'like', "%{$q}%");
            });
        }

        $permissionsQuery = Permission::query()->orderBy('category')->orderBy('key');

        if ($request->filled('permission_search')) {
            $q = $request->permission_search;
            $permissionsQuery->where(function ($query) use ($q) {
                $query->where('key', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%");
            });
        }

        $roles = $rolesQuery->get()->map(fn (Role $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'key' => $r->key,
            'description' => $r->description,
            'is_system' => $r->is_system,
            'permissions_count' => $r->permissions_count,
            'admins_count' => $r->admin_users_count,
            'permission_ids' => $r->permissions->pluck('id')->all(),
        ]);

        $permissions = $permissionsQuery->get()->map(fn (Permission $p) => [
            'id' => $p->id,
            'key' => $p->key,
            'name' => $p->name,
            'category' => $p->category,
            'description' => $p->description,
        ]);

        return Inertia::render('Admin/RolesPermissions/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'filters' => $request->only(['role_search', 'permission_search']),
        ]);
    }
}
