<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Integrations\Services\Clients\WordPressClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WordPressContentController extends Controller
{
    public function index(Request $request, WorkspaceIntegration $connection): Response
    {
        $this->authorise($request, $connection);
        abort_unless($connection->provider === 'wordpress' && $connection->status === 'connected', 422);
        $client = new WordPressClient($connection->credentials);

        try {
            $items = array_merge($client->list('posts'), $client->list('pages'));
            $error = null;
        } catch (\Throwable $e) {
            $items = [];
            $error = $e->getMessage();
        }

        return Inertia::render('Integrations/WordPress', [
            'connection' => $connection,
            'items' => $items,
            'loadError' => $error,
        ]);
    }

    public function save(Request $request, WorkspaceIntegration $connection): RedirectResponse
    {
        $this->authorise($request, $connection);
        abort_unless($connection->provider === 'wordpress' && $connection->status === 'connected', 422);
        $data = $request->validate([
            'type' => ['required', 'in:posts,pages'],
            'id' => ['nullable', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:1000000'],
            'status' => ['required', 'in:draft,pending,private,publish'],
        ]);
        try {
            (new WordPressClient($connection->credentials))->save($data['type'], $data['id'] ?? null, $data);
        } catch (\Throwable $e) {
            return back()->with('error', 'Não foi possível salvar no WordPress: '.$e->getMessage());
        }

        return back()->with('success', 'Conteúdo salvo no WordPress.');
    }

    private function authorise(Request $request, WorkspaceIntegration $connection): void
    {
        $wid = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_unless($connection->workspace_id === $wid, 403);
    }
}
