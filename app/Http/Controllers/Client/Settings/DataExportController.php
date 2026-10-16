<?php

namespace App\Http\Controllers\Client\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateWorkspaceExportJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataExportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isClientAdministrator(), 403);

        return Inertia::render('client/Settings/DataExport', [
            'status' => session('export_status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isClientAdministrator(), 403);

        GenerateWorkspaceExportJob::dispatch($request->user()->id)
            ->onQueue('default');

        return back()->with('export_status', 'Sua exportação está sendo gerada. Você receberá por e-mail um link temporário para download.');
    }
}
