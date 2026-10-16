<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $valid = $request->validate([
            'key' => ['required', 'string', 'max:100', 'unique:permissions,key', 'regex:/^[a-z][a-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Permission::create($valid);

        return redirect()->route('admin.roles-permissions.index')->with('success', __('Permission created.'));
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $valid = $request->validate([
            'key' => ['required', 'string', 'max:100', 'unique:permissions,key,' . $permission->id, 'regex:/^[a-z][a-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $permission->update($valid);

        return redirect()->route('admin.roles-permissions.index')->with('success', __('Permission updated.'));
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();
        return redirect()->route('admin.roles-permissions.index')->with('success', __('Permission deleted.'));
    }
}
