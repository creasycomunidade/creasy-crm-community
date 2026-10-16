<?php

use App\Modules\Integrations\Http\Controllers\ZernioWebhookController;
use Illuminate\Support\Facades\Route;



/**
 * Webhook intake routes – no CSRF, no auth.
 *
 * All webhook endpoints are public and protected inside each controller by
 * provider signatures or high-entropy, per-connection secret URL tokens.
 *
 * Each route:
 *   GET  – used for webhook verification (hub.challenge / verify_token)
 *   POST – receives inbound events
 */

use App\Http\Controllers\Webhooks\AutomationWebhookController;
use App\Modules\Broadcasting\Http\Controllers\EmailTrackingController;
use App\Modules\Broadcasting\Http\Controllers\SmsStatusWebhookController;
use App\Modules\Ecommerce\Http\Controllers\EcommerceOAuthController;
use App\Modules\Ecommerce\Http\Controllers\EcommerceWebhookController;
use App\Modules\Inbox\Http\Controllers\MetaWebhookController;
use App\Modules\Telegram\Http\Controllers\TelegramWebhookController;
use App\Modules\Whatsapp\Http\Controllers\EvolutionGoWebhookController;
use App\Modules\Whatsapp\Http\Controllers\WhatsappWebhookController;

// ─── WhatsApp Cloud API ──────────────────────────────────────────────────────
Route::middleware('throttle:webhooks')->group(function () {
    Route::prefix('webhooks/whatsapp')->name('webhooks.whatsapp.')->group(function () {
        // Global endpoint for embedded-signup WABAs (shared Meta App callback URL).
        // Must be declared before /{token} so "global" is not captured as a token.
        Route::get('/global', [WhatsappWebhookController::class, 'verifyGlobal'])->name('global.verify');
        Route::post('/global', [WhatsappWebhookController::class, 'receiveGlobal'])->name('global.receive');

        // Per-WABA token endpoints (used by manually configured WABAs).
        Route::get('/{token}', [WhatsappWebhookController::class, 'verify'])->name('verify');
        Route::post('/{token}', [WhatsappWebhookController::class, 'receive'])->name('receive');
    });

    // ─── Meta (Instagram + Messenger) ───────────────────────────────────────────
    Route::prefix('webhooks/meta')->name('webhooks.meta.')->group(function () {
        Route::get('/{token}', [MetaWebhookController::class, 'verify'])->name('verify');
        Route::post('/{token}', [MetaWebhookController::class, 'receive'])->name('receive');
    });

    Route::post('webhooks/telegram/{token}', [TelegramWebhookController::class, 'receive'])
        ->name('webhooks.telegram.receive')
        ->where('token', '[A-Za-z0-9]{48}');

    Route::post('webhooks/whatsapp-gateway/{token}', [\App\Modules\Whatsapp\Http\Controllers\WhatsappGatewayWebhookController::class, 'receive'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->name('webhooks.whatsapp-gateway.receive')->where('token', '[a-f0-9]{64}');

    Route::post('webhooks/evolution-go/{token}', [EvolutionGoWebhookController::class, 'receive'])
        ->name('webhooks.evolution-go.receive')
        ->where('token', '[a-f0-9]{64}');

}); // end throttle:webhooks

Route::post('webhooks/automation/{trigger_token}', [\App\Http\Controllers\Webhooks\AutomationWebhookController::class, 'receive'])->middleware('throttle:webhooks')->name('webhooks.automation.receive');
