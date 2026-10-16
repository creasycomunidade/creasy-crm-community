<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceMemberController extends Controller
{
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::in(['administrator', 'member'])],
        ]);

        $member = User::findOrFail($validated['user_id']);
        abort_unless(
            $workspace->client_id !== null
            && (int) $member->client_id === (int) $workspace->client_id,
            422,
            __('The selected user does not belong to this client.')
        );

        abort_if((int) $member->id === (int) $workspace->owner_id, 422, __('The owner role cannot be changed.'));

        $workspace->members()->syncWithoutDetaching([
            $member->id => ['role' => $validated['role']],
        ]);

        if (! $member->workspace_id) {
            $member->forceFill(['workspace_id' => $workspace->id])->saveQuietly();
        }

        return back()->with('success', __('Workspace access updated.'));
    }

    public function destroy(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        $this->authorize('update', $workspace);
        abort_if((int) $member->id === (int) $workspace->owner_id, 422, __('The workspace owner cannot be removed.'));

        $workspace->members()->detach($member->id);

        if ((int) $member->workspace_id === (int) $workspace->id) {
            $next = $member->accessibleWorkspaces()->first();
            $member->forceFill(['workspace_id' => $next?->id])->saveQuietly();
        }

        return back()->with('success', __('Workspace access removed.'));
    }
}
