<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Client\ApiTokenController;
use App\Http\Controllers\Client\AuditLogController as ClientAuditLogController;
use App\Http\Controllers\Client\InvitationController;
use App\Http\Controllers\Client\MediaController;
use App\Http\Controllers\Client\NotificationController;
use App\Http\Controllers\Client\OnboardingController;
use App\Http\Controllers\Client\SearchController;
use App\Http\Controllers\Client\Settings\DataExportController;
use App\Http\Controllers\Client\SettingsController as ClientSettingsController;
use App\Http\Controllers\Client\SupportTicketController;
use App\Http\Controllers\Client\TeamController;
use App\Http\Controllers\Client\WebhookEndpointController;
use App\Http\Controllers\Client\WebPushController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceFileController;
use App\Http\Controllers\WorkspaceFileShareController;
use App\Http\Controllers\WorkspaceFolderController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', \App\Http\Controllers\Client\Reports\InboxReportController::class)->name('dashboard');

    // Subscription





    // Coupon validation


    // Billing & Pricing


    // Team management (client admins only)
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::put('/team/{member}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('/team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');

    // Audit log (client admins only)
    Route::get('/audit-log', [ClientAuditLogController::class, 'index'])->name('audit-log.index');

    // Settings
    Route::get('/settings', [ClientSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [ClientSettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/notifications', [ClientSettingsController::class, 'notifications'])->name('settings.notifications');
    Route::get('/settings/data-export', [DataExportController::class, 'index'])->name('settings.data-export');
    Route::post('/settings/data-export', [DataExportController::class, 'store'])
        ->middleware('throttle:2,60')
        ->name('settings.data-export.store');

    // Workspaces (switcher)
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::post('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'store'])->name('workspaces.members.store');
    Route::delete('/workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('workspaces.members.destroy');

    // Private workspace files. Downloads are always authorised by workspace;
    // they are never exposed through the public storage symlink.
    Route::get('/workspace-files', [WorkspaceFileController::class, 'index'])->name('workspace-files.index')->middleware('module:ai');
    Route::post('/workspace-files', [WorkspaceFileController::class, 'store'])->name('workspace-files.store')->middleware('module:ai');
    Route::post('/workspace-files/new', [WorkspaceFileController::class, 'createText'])->name('workspace-files.create-text')->middleware('module:ai');
    Route::patch('/workspace-files/{file}', [WorkspaceFileController::class, 'update'])->name('workspace-files.update')->middleware('module:ai');
    Route::get('/workspace-files/{file}/preview', [WorkspaceFileController::class, 'preview'])->name('workspace-files.preview')->middleware('module:ai');
    Route::get('/workspace-files/{file}/download', [WorkspaceFileController::class, 'download'])->name('workspace-files.download')->middleware('module:ai');
    Route::delete('/workspace-files/{file}', [WorkspaceFileController::class, 'destroy'])->name('workspace-files.destroy')->middleware('module:ai');
    Route::post('/workspace-files/{file}/share', [WorkspaceFileShareController::class, 'store'])->name('workspace-files.share')->middleware('module:ai');
    Route::delete('/workspace-file-shares/{share}', [WorkspaceFileShareController::class, 'destroy'])->name('workspace-file-shares.destroy')->middleware('module:ai');
    Route::post('/workspace-folders', [WorkspaceFolderController::class, 'store'])->name('workspace-folders.store')->middleware('module:ai');
    Route::patch('/workspace-folders/{folder}', [WorkspaceFolderController::class, 'update'])->name('workspace-folders.update')->middleware('module:ai');
    Route::delete('/workspace-folders/{folder}', [WorkspaceFolderController::class, 'destroy'])->name('workspace-folders.destroy')->middleware('module:ai');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 2FA
    Route::get('/profile/two-factor', [TwoFactorController::class, 'show'])->name('profile.2fa');
    Route::post('/profile/two-factor/enable', [TwoFactorController::class, 'enable'])->name('profile.2fa.enable');
    Route::post('/profile/two-factor/disable', [TwoFactorController::class, 'disable'])->name('profile.2fa.disable');
    Route::post('/profile/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateCodes'])->name('profile.2fa.recovery-codes');

    // Session management
    Route::get('/profile/sessions', [SessionController::class, 'index'])->name('profile.sessions');
    Route::delete('/profile/sessions', [SessionController::class, 'destroy'])->name('profile.sessions.destroy');

    // Team invitations (send/revoke, client admin only)
    Route::post('/invitations', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    // API Tokens & Docs
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index')->middleware('module:developer_api');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store')->middleware('module:developer_api');
    Route::delete('/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy')->middleware('module:developer_api');
    Route::get('/api-docs', fn () => Inertia::render('client/Api/Docs'))->name('api-docs')->middleware('module:developer_api');

    // Media Library
    Route::get('/media', [MediaController::class, 'index'])->name('media.index')->middleware('module:media_library');
    Route::get('/media/library', [MediaController::class, 'library'])->name('media.library')->middleware('module:media_library');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store')->middleware('module:media_library');
    Route::delete('/media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy')->middleware('module:media_library');

    // Onboarding Wizard
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    // Global Search (⌘K)
    Route::get('/search', [SearchController::class, 'search'])->name('search');

    // Support Tickets
    Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportTicketController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');
    Route::get('/support/{supportTicket}', [SupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{supportTicket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');

    // In-app Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notification-preferences', [NotificationController::class, 'updatePreferences'])->name('notification-preferences.update');

    // Webhook Endpoints
    Route::get('/webhooks', [WebhookEndpointController::class, 'index'])->name('webhooks.index')->middleware('module:webhooks');
    Route::post('/webhooks', [WebhookEndpointController::class, 'store'])->name('webhooks.store')->middleware('module:webhooks');
    Route::put('/webhooks/{webhookEndpoint}', [WebhookEndpointController::class, 'update'])->name('webhooks.update')->middleware('module:webhooks');
    Route::delete('/webhooks/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhooks.destroy')->middleware('module:webhooks');
    Route::post('/webhooks/{webhookEndpoint}/rotate-secret', [WebhookEndpointController::class, 'rotateSecret'])->name('webhooks.rotate-secret')->middleware('module:webhooks');
    Route::post('/webhooks/{webhookEndpoint}/test', [WebhookEndpointController::class, 'testDelivery'])->name('webhooks.test')->middleware('module:webhooks');
    Route::get('/webhooks/{webhookEndpoint}/deliveries', [WebhookEndpointController::class, 'deliveries'])->name('webhooks.deliveries')->middleware('module:webhooks');

    // Web Push subscriptions
    Route::post('/push/subscribe', [WebPushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [WebPushController::class, 'unsubscribe'])->name('push.unsubscribe');
});
