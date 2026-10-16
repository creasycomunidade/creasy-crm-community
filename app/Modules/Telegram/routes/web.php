<?php

use App\Modules\Telegram\Http\Controllers\TelegramSetupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'client-app', 'module:channel_management'])->prefix('painel/inbox/setup/telegram')->name('client.telegram.')->group(function () {
    Route::post('/', [TelegramSetupController::class, 'store'])->name('store');
    Route::delete('/{channelAccount}', [TelegramSetupController::class, 'destroy'])->name('destroy');
});
