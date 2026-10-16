<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $valid = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:100', 'unique:roles,key', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $valid['name'],
            'key' => $valid['key'],
            'description' => $valid['description'] ?? null,
            'is_system' => false,
        ]);

        if (! empty($valid['permission_ids'])) {
            $role->permissions()->sync($valid['permission_ids']);
        }

        return redirect()->route('admin.roles-permissions.index')->with('success', __('Role created.'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $valid = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($role->id), 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        if ($role->is_system) {
            // System role: key cannot be changed; name, description, and permissions can be updated
            $role->update([
                'name' => $valid['name'],
                'description' => $valid['description'] ?? null,
            ]);
        } else {
            $role->update([
                'name' => $valid['name'],
                'key' => $valid['key'],
                'description' => $valid['description'] ?? null,
            ]);
        }

        $role->permissions()->sync($valid['permission_ids'] ?? []);

        return redirect()->route('admin.roles-permissions.index')->with('success', __('Role updated.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return redirect()->route('admin.roles-permissions.index')->with('error', __('System role cannot be deleted.'));
        }

        if ($role->adminUsers()->count() > 0) {
            return redirect()->route('admin.roles-permissions.index')->with('error', __('Cannot delete role that is assigned to admins.'));
        }

        $role->delete();
        return redirect()->route('admin.roles-permissions.index')->with('success', __('Role deleted.'));
    }
}
