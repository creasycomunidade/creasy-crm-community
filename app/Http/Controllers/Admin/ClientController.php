<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request): Response
    {
        $this->authorizeForUser($request->user('admin'), 'viewAny', Client::class);

        $query = Client::query();

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $clients = $query->orderBy('name')->paginate(20)->withQueryString()->through(function (Client $c) {

            return [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'address' => $c->address,
                'status' => $c->status,
                'base_currency' => $c->base_currency,
                'currency_symbol' => $c->currency_symbol,
                'currency_position' => $c->currency_position,
            ];
        });


        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'create', Client::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'base_currency' => ['nullable', 'string', 'max:10'],
            'currency_symbol' => ['nullable', 'string', 'max:16'],
            'currency_position' => ['nullable', 'string', 'in:before,after'],
        ]);

        $validated['status'] = $validated['status'] ?? 'active';
        $validated['base_currency'] = $validated['base_currency'] ?? 'BRL';
        $validated['currency_symbol'] = $validated['currency_symbol'] ?? 'R$';
        $validated['currency_position'] = $validated['currency_position'] ?? 'before';

        $client = Client::create($validated);

        $this->auditLog->logAdmin('client.created', Client::class, (int) $client->id, ['name' => $client->name]);

        return redirect()->route('admin.clients.index')->with('success', __('Client created.'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'update', $client);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'base_currency' => ['nullable', 'string', 'max:10'],
            'currency_symbol' => ['nullable', 'string', 'max:16'],
            'currency_position' => ['nullable', 'string', 'in:before,after'],
        ]);

        $client->update($validated);

        $this->auditLog->logAdmin('client.updated', Client::class, (int) $client->id, ['name' => $client->name]);

        return redirect()->back()->with('success', __('Client updated.'));
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'delete', $client);

        $name = $client->name;
        $client->delete();

        $this->auditLog->logAdmin('client.deleted', null, null, ['name' => $name]);

        return redirect()->route('admin.clients.index')->with('success', __('Client deleted.'));
    }

    public function users(Request $request, Client $client): JsonResponse
    {
        $this->authorizeForUser($request->user('admin'), 'view', $client);

        $users = $client->users()->orderBy('created_at')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'client_role' => $u->client_role ?? 'staff',
            'status' => $u->status ?? 'active',
            'created_at' => $u->created_at->toIso8601String(),
        ]);

        return response()->json(['users' => $users, 'client' => ['id' => $client->id, 'name' => $client->name]]);
    }

    public function storeUser(Request $request, Client $client): RedirectResponse|JsonResponse
    {
        $this->authorizeForUser($request->user('admin'), 'update', $client);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'client_role' => ['required', 'string', 'in:administrator,staff'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $user = $client->users()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'client_role' => $validated['client_role'],
            'status' => $validated['status'],
            // Users provisioned by a platform administrator are ready to use;
            // leaving this null blocks every client route behind `verified`.
            'email_verified_at' => now(),
        ]);

        $this->auditLog->logAdmin('client.user_created', User::class, (int) $user->id, ['client_id' => $client->id, 'email' => $user->email]);

        if ($request->wantsJson()) {
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'client_role' => $user->client_role,
                    'status' => $user->status,
                    'created_at' => $user->created_at->toIso8601String(),
                ],
            ], 201);
        }

        return redirect()->back()->with('success', __('User added.'));
    }

    public function updateUser(Request $request, Client $client, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeForUser($request->user('admin'), 'update', $client);
        if ($user->client_id !== $client->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'client_role' => ['required', 'string', 'in:administrator,staff'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->client_role = $validated['client_role'];
        $user->status = $validated['status'];
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        $this->auditLog->logAdmin('client.user_updated', User::class, (int) $user->id, ['client_id' => $client->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'client_role' => $user->client_role,
                    'status' => $user->status,
                    'created_at' => $user->created_at->toIso8601String(),
                ],
            ]);
        }

        return redirect()->back()->with('success', __('User updated.'));
    }

    public function destroyUser(Request $request, Client $client, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeForUser($request->user('admin'), 'update', $client);
        if ($user->client_id !== $client->id) {
            abort(404);
        }

        $adminCount = $client->users()->where('client_role', User::CLIENT_ROLE_ADMINISTRATOR)->count();
        if ($user->client_role === User::CLIENT_ROLE_ADMINISTRATOR && $adminCount <= 1) {
            if ($request->wantsJson()) {
                return response()->json(['message' => __('Cannot delete the last client administrator.')], 422);
            }

            return redirect()->back()->with('error', __('Cannot delete the last client administrator.'));
        }

        $user->delete();
        $this->auditLog->logAdmin('client.user_deleted', User::class, null, ['client_id' => $client->id, 'email' => $user->email]);

        if ($request->wantsJson()) {
            return response()->json([], 204);
        }

        return redirect()->back()->with('success', __('User removed.'));
    }


    public function impersonate(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeForUser($request->user('admin'), 'impersonate', $client);

        if ($request->session()->get('impersonating')) {
            $currentClientUser = Auth::guard('web')->user();
            if (
                $currentClientUser
                && (int) $request->session()->get('impersonated_client_id') === (int) $client->id
                && (int) $currentClientUser->client_id === (int) $client->id
            ) {
                return redirect()->route('client.dashboard');
            }

            return redirect()->route('admin.clients.index')->with('error', __('Already impersonating.'));
        }

        $targetUser = $client->users()
            ->where('status', User::STATUS_ACTIVE)
            ->orderByRaw("CASE WHEN client_role = 'administrator' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->first();

        if (! $targetUser) {
            return redirect()->back()->with('error', __('Client has no active users. Add a user first.'));
        }

        $admin = $request->user('admin');

        $request->session()->put('impersonator_admin_id', $admin->id);
        $request->session()->put('impersonating', true);
        $request->session()->put('impersonated_client_id', $client->id);

        Auth::guard('web')->login($targetUser, $request->boolean('remember', false));
        $request->session()->put(
            'current_workspace_id',
            $targetUser->workspace_id
                ?? $targetUser->accessibleWorkspaces()->first()?->id
        );

        $this->auditLog->logAdmin('impersonation.started', User::class, (int) $targetUser->id, [
            'client_id' => $client->id,
            'client_name' => $client->name,
        ]);

        return redirect()->route('client.dashboard');
    }

    /**
     * Export all clients as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeForUser($request->user('admin'), 'viewAny', Client::class);

        $q = $request->get('search');

        return response()->streamDownload(function () use ($q) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Email', 'Phone', 'Status', 'Created At']);

            Client::query()
                ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                ->orderBy('name')
                ->chunk(200, function ($clients) use ($handle) {
                    foreach ($clients as $c) {
                        fputcsv($handle, [
                            $c->id,
                            $c->name,
                            $c->email,
                            $c->phone,
                            $c->status,
                            $c->created_at->toDateString(),
                        ]);
                    }
                });

            fclose($handle);
        }, 'clients_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
