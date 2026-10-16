<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AdminUser::query()->with('roles');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $admins = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        $admins->getCollection()->transform(function (AdminUser $admin) {
            return [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'status' => $admin->status,
                'roles' => $admin->roles->map(fn (Role $r) => ['id' => $r->id, 'key' => $r->key, 'name' => $r->name]),
                'created_at' => $admin->created_at->toIso8601String(),
            ];
        });

        $roles = Role::orderBy('name')->get(['id', 'key', 'name']);

        return Inertia::render('Admin/Admins/Index', [
            'admins' => $admins,
            'roles' => $roles,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $valid = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:admin_users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in([AdminUser::STATUS_ACTIVE, AdminUser::STATUS_INACTIVE])],
            'role_ids' => ['array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $admin = AdminUser::create([
            'name' => $valid['name'],
            'email' => $valid['email'],
            'password' => Hash::make($valid['password']),
            'status' => $valid['status'],
        ]);

        if (! empty($valid['role_ids'])) {
            $admin->roles()->sync($valid['role_ids']);
        }

        return redirect()->route('admin.admins.index')->with('success', __('Admin created.'));
    }

    public function update(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $valid = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('admin_users')->ignore($adminUser->id)],
            'status' => ['required', Rule::in([AdminUser::STATUS_ACTIVE, AdminUser::STATUS_INACTIVE])],
            'role_ids' => ['array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $adminUser->update([
            'name' => $valid['name'],
            'email' => $valid['email'],
            'status' => $valid['status'],
        ]);

        $adminUser->roles()->sync($valid['role_ids'] ?? []);

        return redirect()->route('admin.admins.index')->with('success', __('Admin updated.'));
    }

    public function destroy(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $current = $request->user('admin');
        if ($current && $current->id === $adminUser->id) {
            return redirect()->route('admin.admins.index')->with('error', __('You cannot delete yourself.'));
        }

        if ($adminUser->isSuperAdmin()) {
            $superAdminCount = AdminUser::whereHas('roles', fn ($q) => $q->where('key', \App\Models\Role::KEY_SUPER_ADMIN))->count();
            if ($superAdminCount <= 1) {
                return redirect()->route('admin.admins.index')->with('error', __('Cannot delete the last Super Admin.'));
            }
        }

        $adminUser->delete();
        return redirect()->route('admin.admins.index')->with('success', __('Admin deleted.'));
    }

    public function toggleStatus(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $current = $request->user('admin');
        if ($current && $current->id === $adminUser->id) {
            return redirect()->route('admin.admins.index')->with('error', __('You cannot deactivate yourself.'));
        }

        $adminUser->update([
            'status' => $adminUser->status === AdminUser::STATUS_ACTIVE ? AdminUser::STATUS_INACTIVE : AdminUser::STATUS_ACTIVE,
        ]);

        return redirect()->route('admin.admins.index')->with('success', __('Status updated.'));
    }
}
