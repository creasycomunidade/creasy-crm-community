<?php

use App\Http\Controllers\Client\Reports\AiReportController;
use App\Http\Controllers\Client\Reports\AutomationReportController;
use App\Http\Controllers\Client\Reports\CampaignReportController;
use App\Http\Controllers\Client\Reports\ExportController;
use App\Http\Controllers\Client\Reports\InboxReportController;
use App\Http\Controllers\Client\Reports\SocialReportController;
use Illuminate\Support\Facades\Route;

// ─── Report pages ─────────────────────────────────────────────────────────────
Route::middleware(['web', 'client-app', 'module:reports'])->prefix('painel/reports')->name('client.reports.')->group(function () {
    // Per-report overview pages
    Route::get('/campaigns', function () {
        return redirect()->route('client.campaigns.index');
    })->name('campaigns.index');
    Route::get('/campaigns/{campaign}', CampaignReportController::class)->name('campaigns.show');
    Route::get('/ai', AiReportController::class)->name('ai.index');
    Route::get('/inbox', InboxReportController::class)->name('inbox.index');
    Route::get('/automations', AutomationReportController::class)->name('automations.index');
    Route::get('/social', SocialReportController::class)->name('social.index');
});

// ─── CSV Exports ──────────────────────────────────────────────────────────────
Route::middleware(['web', 'client-app', 'module:reports'])->prefix('reports/exports')->name('reports.exports.')->group(function () {
    Route::get('contacts', [ExportController::class, 'contacts'])->name('contacts');
    Route::get('campaign-recipients/{campaign}', [ExportController::class, 'campaignRecipients'])->name('campaign-recipients');
    Route::get('conversations', [ExportController::class, 'conversations'])->name('conversations');
    Route::get('ai-runs', [ExportController::class, 'aiRuns'])->name('ai-runs');
    Route::get('audit-log', [ExportController::class, 'auditLog'])->name('audit-log');
});
