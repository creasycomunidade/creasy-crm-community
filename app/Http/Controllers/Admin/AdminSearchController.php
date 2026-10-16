<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->get('q', ''));

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $lower = strtolower($query);
        $results = [];

        // ── Navigation pages ────────────────────────────────────────────────
        $navItems = [
            ['label' => 'Dashboard',            'href' => route('admin.dashboard'),            'icon' => 'LayoutDashboard'],
            ['label' => 'Clients',               'href' => route('admin.clients.index'),        'icon' => 'Building2'],
            ['label' => 'Users',                 'href' => route('admin.clients.index'),          'icon' => 'Users'],
            ['label' => 'Admin Settings',        'href' => route('admin.settings.index'),       'icon' => 'Settings'],
            ['label' => 'CMS Pages',             'href' => route('admin.cms-pages.index'),            'icon' => 'FileText'],
        ];

        foreach ($navItems as $item) {
            if (str_contains(strtolower($item['label']), $lower)) {
                $results[] = array_merge($item, ['type' => 'page']);
            }
        }

        // ── Clients ─────────────────────────────────────────────────────────
        Client::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->each(function (Client $client) use (&$results) {
                $results[] = [
                    'type' => 'client',
                    'label' => $client->name,
                    'sub' => $client->email,
                    'href' => route('admin.clients.index', ['search' => $client->name]),
                    'icon' => 'Building2',
                ];
            });

        // ── Users ────────────────────────────────────────────────────────────
        User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->each(function (User $user) use (&$results) {
                $results[] = [
                    'type' => 'user',
                    'label' => $user->name,
                    'sub' => $user->email,
                    'href' => route('admin.clients.index', ['search' => $user->client?->name ?? $user->email]),
                    'icon' => 'User',
                ];
            });

        return response()->json(['results' => array_slice($results, 0, 15)]);
    }
}
