<?php

use App\Modules\Integrations\Http\Controllers\WordPressContentController;
use App\Modules\Integrations\Http\Controllers\WorkspaceIntegrationController;
use App\Modules\Integrations\Http\Controllers\ZernioWorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'client-app', 'module:integrations'])->prefix('painel/integracoes')->name('client.integrations.')->group(function () {
    Route::get('/', [WorkspaceIntegrationController::class, 'index'])->name('index');
    Route::post('/', [WorkspaceIntegrationController::class, 'store'])->name('store');
    Route::post('/{connection}/testar', [WorkspaceIntegrationController::class, 'test'])->name('test');
    Route::delete('/{connection}', [WorkspaceIntegrationController::class, 'destroy'])->name('destroy');
    Route::get('/{connection}/wordpress', [WordPressContentController::class, 'index'])->name('wordpress.index');
    Route::post('/{connection}/wordpress', [WordPressContentController::class, 'save'])->name('wordpress.save');
    Route::middleware('module:unified_connections')->group(function () {
        Route::post('/zernio/preparar', [ZernioWorkspaceController::class, 'provision'])->name('zernio.provision');
        Route::post('/zernio/sincronizar', [ZernioWorkspaceController::class, 'sync'])->name('zernio.sync');
        Route::get('/zernio/conectar/{platform}', [ZernioWorkspaceController::class, 'connect'])->name('zernio.connect');
        Route::get('/zernio/conectar-anuncios/{platform}', [ZernioWorkspaceController::class, 'connectAds'])->name('zernio.connect-ads');
        Route::get('/zernio/retorno', [ZernioWorkspaceController::class, 'callback'])->name('zernio.callback');
    });
});
