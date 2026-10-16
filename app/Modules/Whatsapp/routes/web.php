<?php

use App\Modules\Whatsapp\Http\Controllers\WhatsappAutoReplyController;
use App\Modules\Whatsapp\Http\Controllers\WhatsappEmbeddedSignupController;
use App\Modules\Whatsapp\Http\Controllers\WhatsappSetupController;
use App\Modules\Whatsapp\Http\Controllers\WhatsappTemplateController;
use App\Modules\Whatsapp\Http\Controllers\WhatsappWidgetController;
use App\Modules\Whatsapp\Http\Controllers\EvolutionGoSetupController;

use Illuminate\Support\Facades\Route;

// Public: JS widget embed (no auth)
Route::get('/widgets/whatsapp/{key}.js', [WhatsappWidgetController::class, 'embed'])->name('whatsapp.widget.embed');

// Authenticated client routes
Route::middleware(['web', 'client-app', 'module:channel_management'])->prefix('painel/whatsapp')->name('client.whatsapp.')->group(function () {
    // Setup GET redirects to the unified Channel Setup page
    Route::get('/setup', fn () => redirect()->route('client.inbox.setup'))->name('setup');
    Route::post('/setup/embedded-signup', [WhatsappEmbeddedSignupController::class, 'store'])->name('setup.embedded-signup');
    Route::post('/setup/{waba}/reregister-webhook', [WhatsappEmbeddedSignupController::class, 'reregisterWebhook'])->name('setup.reregister-webhook');
    Route::delete('/setup/{waba}', [WhatsappSetupController::class, 'destroy'])->name('setup.destroy');
    Route::post('/setup/{waba}/sync-phone-numbers', [WhatsappSetupController::class, 'syncPhoneNumbers'])->name('setup.sync-phone-numbers');
    Route::post('/setup/{waba}/phone/{phoneNumberId}/refresh-status', [WhatsappSetupController::class, 'refreshPhoneStatus'])->name('setup.refresh-phone-status');
    Route::post('/setup/{waba}/phone/{phoneNumberId}/change-name', [WhatsappSetupController::class, 'changeDisplayName'])->name('setup.change-display-name');

    Route::prefix('/gateway')->name('gateway.')->group(function () {
        $controller = \App\Modules\Whatsapp\Http\Controllers\WhatsappGatewaySetupController::class;
        Route::post('/', [$controller, 'store'])->name('store');
        Route::get('/{channelAccount}/status', [$controller, 'status'])->name('status');
        Route::get('/{channelAccount}/qr', [$controller, 'qr'])->name('qr');
        Route::post('/{channelAccount}/reconnect', [$controller, 'reconnect'])->name('reconnect');
        Route::delete('/{channelAccount}', [$controller, 'destroy'])->name('destroy');
    });

    Route::prefix('/evolution-go')->name('evolution-go.')->group(function () {
        Route::post('/', [EvolutionGoSetupController::class, 'store'])->name('store');
        Route::get('/{channelAccount}/status', [EvolutionGoSetupController::class, 'status'])->name('status');
        Route::get('/{channelAccount}/qr', [EvolutionGoSetupController::class, 'qr'])->name('qr');
        Route::put('/{channelAccount}/settings', [EvolutionGoSetupController::class, 'updateSettings'])->name('settings');
        Route::post('/{channelAccount}/reconnect', [EvolutionGoSetupController::class, 'reconnect'])->name('reconnect');
        Route::delete('/{channelAccount}', [EvolutionGoSetupController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('module:whatsapp_tools')->group(function () {
        // Templates
        Route::get('/templates', [WhatsappTemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/create', [WhatsappTemplateController::class, 'create'])->name('templates.create');
        Route::post('/templates', [WhatsappTemplateController::class, 'store'])->name('templates.store');
        Route::post('/templates/sync', [WhatsappTemplateController::class, 'sync'])->name('templates.sync');
        Route::post('/templates/upload-media', [WhatsappTemplateController::class, 'uploadMedia'])->name('templates.upload-media');
        Route::get('/templates/{template}/edit', [WhatsappTemplateController::class, 'edit'])->name('templates.edit');
        Route::put('/templates/{template}', [WhatsappTemplateController::class, 'update'])->name('templates.update');
        Route::delete('/templates/{template}', [WhatsappTemplateController::class, 'destroy'])->name('templates.destroy');

        // Auto-replies
        Route::get('/auto-replies', [WhatsappAutoReplyController::class, 'index'])->name('auto-replies.index');
        Route::post('/auto-replies', [WhatsappAutoReplyController::class, 'store'])->name('auto-replies.store');
        Route::put('/auto-replies/{autoReply}', [WhatsappAutoReplyController::class, 'update'])->name('auto-replies.update');
        Route::delete('/auto-replies/{autoReply}', [WhatsappAutoReplyController::class, 'destroy'])->name('auto-replies.destroy');

        // Widgets
        Route::get('/widget', [WhatsappWidgetController::class, 'index'])->name('widget.index');
        Route::get('/widgets/create', [WhatsappWidgetController::class, 'create'])->name('widgets.create');
        Route::post('/widgets', [WhatsappWidgetController::class, 'store'])->name('widgets.store');
        Route::get('/widgets/{widget}/edit', [WhatsappWidgetController::class, 'edit'])->name('widgets.edit');
        Route::put('/widgets/{widget}', [WhatsappWidgetController::class, 'update'])->name('widgets.update');
        Route::delete('/widgets/{widget}', [WhatsappWidgetController::class, 'destroy'])->name('widgets.destroy');
    });
});
