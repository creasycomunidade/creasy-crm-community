<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\User;
use App\Services\WorkspaceLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /**
     * List workspaces the user can access (for switcher).
     */
    public function index(Request $request, WorkspaceLimitService $limits): Response
    {
        $workspaces = $request->user()->accessibleWorkspaces();

        return Inertia::render('client/Workspaces/Index', [
            'workspaces' => $workspaces->map(fn (Workspace $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'is_owner' => $w->owner_id === $request->user()->id,
                'role' => $w->memberRole($request->user()),
                'can_manage_members' => $w->canManageMembers($request->user()),
                'can_edit' => $request->user()->can('update', $w),
                'can_delete' => $request->user()->can('delete', $w) && $workspaces->count() > 1,
                'members' => $w->members()->get(['users.id', 'users.name', 'users.email'])->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role,
                    'is_owner' => (int) $member->id === (int) $w->owner_id,
                ])->values(),
            ]),
            'availableUsers' => $request->user()->client_id
                ? $request->user()->client->users()->where('status', User::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name', 'email'])
                : [],
            'canCreate' => $limits->canCreate($request->user()),
            'workspaceLimit' => $limits->limitFor($request->user()),
            'workspaceCount' => $limits->countFor($request->user()),
        ]);
    }

    /**
     * Switch current workspace (set session and update user.workspace_id).
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', Rule::exists('workspaces', 'id')],
        ]);

        $workspace = Workspace::findOrFail($validated['workspace_id']);

        $this->authorize('view', $workspace);

        $request->session()->put('current_workspace_id', $workspace->id);
        $request->user()->update(['workspace_id' => $workspace->id]);

        return redirect()->intended(route('client.dashboard'));
    }

    /**
     * Create a new workspace (owner is current user).
     */
    public function store(Request $request, WorkspaceLimitService $limits): RedirectResponse
    {
        $this->authorize('create', Workspace::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace = DB::transaction(function () use ($request, $validated, $limits) {
            if ($request->user()->client_id) {
                $request->user()->client()->lockForUpdate()->first();
            } else {
                User::whereKey($request->user()->id)->lockForUpdate()->first();
            }

            if (! $limits->canCreate($request->user())) {
                abort(422, __('Your plan workspace limit has been reached.'));
            }

            $workspace = Workspace::create([
                'name' => $validated['name'],
                'owner_id' => $request->user()->id,
                'client_id' => $request->user()->client_id,
                'default_locale' => $request->user()->locale ?? 'pt',
                'currency_code' => $request->user()->display_currency,
            ]);

            $workspace->members()->attach($request->user()->id, ['role' => 'owner']);

            return $workspace;
        });

        $request->session()->put('current_workspace_id', $workspace->id);
        $request->user()->update(['workspace_id' => $workspace->id]);

        return redirect()->route('client.dashboard')->with('success', __('Workspace created.'));
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $workspace->update(['name' => trim($validated['name'])]);

        return back()->with('success', 'Nome do espaço de trabalho atualizado.');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);
        $other = $request->user()->accessibleWorkspaces()->firstWhere('id', '!=', $workspace->id);
        abort_unless($other, 422, 'Não é possível excluir o único espaço de trabalho disponível.');

        DB::transaction(function () use ($request, $workspace, $other): void {
            // Soft deletion preserves auditability and avoids orphaning tenant
            // records. The workspace immediately disappears from every normal
            // query, but an administrator can still recover it if necessary.
            $workspace->delete();
            if ((int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id) === (int) $workspace->id) {
                $request->session()->put('current_workspace_id', $other->id);
                $request->user()->update(['workspace_id' => $other->id]);
            }
        });

        return redirect()->route('client.workspaces.index')->with('success', 'Espaço de trabalho excluído.');
    }
}
