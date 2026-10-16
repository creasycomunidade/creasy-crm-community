<?php

namespace App\Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleCalendarController extends Controller
{
    private const OAUTH_SESSION_KEY = 'google_drive_oauth';

    public function connect(Request $request, GoogleDriveService $google): RedirectResponse
    {
        if (! $google->isConfigured()) {
            return back()->with('error', 'O aplicativo Google ainda não foi configurado pelo administrador do sistema.');
        }

        $state = Str::random(64);
        $request->session()->put(self::OAUTH_SESSION_KEY, [
            'state' => $state,
            'workspace_id' => $this->workspaceId($request),
            'user_id' => (int) $request->user()->id,
            'return_to' => 'appointments',
            'created_at' => now()->timestamp,
        ]);

        return redirect()->away($google->authorizationUrl($state, route('client.ai.google-drive.callback')));
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }
}
