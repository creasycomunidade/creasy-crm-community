<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user->client_id || ! $user->isClientAdministrator()) {
            abort(403, __('Only client administrators can manage the team.'));
        }

        $client = $user->client;
        if (! $client) {
            abort(404);
        }

        $users = $client->users()
            ->orderByRaw("CASE WHEN client_role = 'administrator' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'client_role' => $u->client_role ?? 'staff',
                'status' => $u->status ?? 'active',
                'created_at' => $u->created_at->toIso8601String(),
            ]);

        return Inertia::render('client/Team/Index', [
            'users' => $users,
            'client' => ['id' => $client->id, 'name' => $client->name],
            'invitations' => Invitation::where('client_id', $client->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Invitation $inv) => [
                    'id' => $inv->id,
                    'email' => $inv->email,
                    'client_role' => $inv->client_role,
                    'expires_at' => $inv->expires_at->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->client_id || ! $user->isClientAdministrator()) {
            abort(403, __('Only client administrators can manage the team.'));
        }

        $client = $user->client;
        if (! $client) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'client_role' => ['required', 'string', 'in:administrator,staff'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $client->users()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'client_role' => $validated['client_role'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('client.team.index')->with('success', __('Team member added.'));
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $user = $request->user();
        if (! $user->client_id || ! $user->isClientAdministrator()) {
            abort(403, __('Only client administrators can manage the team.'));
        }

        $client = $user->client;
        if (! $client || $member->client_id !== $client->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'client_role' => ['required', 'string', 'in:administrator,staff'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $member->name = $validated['name'];
        $member->email = $validated['email'];
        $member->client_role = $validated['client_role'];
        $member->status = $validated['status'];
        if (! empty($validated['password'])) {
            $member->password = $validated['password'];
        }
        $member->save();

        return redirect()->route('client.team.index')->with('success', __('Team member updated.'));
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $user = $request->user();
        if (! $user->client_id || ! $user->isClientAdministrator()) {
            abort(403, __('Only client administrators can manage the team.'));
        }

        $client = $user->client;
        if (! $client || $member->client_id !== $client->id) {
            abort(404);
        }

        $adminCount = $client->users()->where('client_role', User::CLIENT_ROLE_ADMINISTRATOR)->count();
        if ($member->client_role === User::CLIENT_ROLE_ADMINISTRATOR && $adminCount <= 1) {
            return redirect()->route('client.team.index')->with('error', __('Cannot remove the last administrator.'));
        }

        if ($member->id === $user->id) {
            return redirect()->route('client.team.index')->with('error', __('You cannot remove yourself.'));
        }

        $member->delete();

        return redirect()->route('client.team.index')->with('success', __('Team member removed.'));
    }
}
