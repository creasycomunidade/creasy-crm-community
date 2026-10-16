<?php

use App\Modules\Inbox\Http\Controllers\CannedReplyController;
use App\Modules\Inbox\Http\Controllers\InboxController;
use App\Modules\Inbox\Http\Controllers\InboxSetupController;
use App\Modules\Inbox\Http\Controllers\InternalNoteController;
use App\Modules\Inbox\Http\Controllers\LabelController;
use App\Modules\Inbox\Http\Controllers\ScheduledMessageController;
use App\Modules\Inbox\Http\Controllers\WebchatChannelController;
use App\Modules\Inbox\Http\Controllers\WebchatPublicController;
use Illuminate\Support\Facades\Route;
Route::middleware(['web', 'throttle:120,1'])->prefix('widgets/chat')->name('public.webchat.')->group(function () {
    Route::get('/{key}.js', [WebchatPublicController::class, 'script'])->name('script');
    Route::get('/{key}', [WebchatPublicController::class, 'frame'])->name('frame');
    Route::post('/{key}/session', [WebchatPublicController::class, 'session'])->name('session');
    Route::get('/{key}/messages', [WebchatPublicController::class, 'messages'])->name('messages');
    Route::post('/{key}/messages', [WebchatPublicController::class, 'send'])->name('send');
});

Route::middleware(['web', 'client-app', 'module:inbox'])->prefix('painel/inbox')->name('client.inbox.')->group(function () {
    Route::middleware('module:message_scheduler')->group(function () {
        Route::get('/scheduled-messages', [ScheduledMessageController::class, 'index'])->name('scheduled-messages.index');
        Route::get('/scheduled-messages/create', [ScheduledMessageController::class, 'create'])->name('scheduled-messages.create');
        Route::post('/scheduled-messages', [ScheduledMessageController::class, 'store'])->name('scheduled-messages.store');
        Route::patch('/scheduled-messages/{scheduledMessage}', [ScheduledMessageController::class, 'update'])->name('scheduled-messages.update');
        Route::post('/scheduled-messages/{scheduledMessage}/pause', [ScheduledMessageController::class, 'pause'])->name('scheduled-messages.pause');
        Route::post('/scheduled-messages/{scheduledMessage}/resume', [ScheduledMessageController::class, 'resume'])->name('scheduled-messages.resume');
        Route::post('/scheduled-messages/{scheduledMessage}/cancel', [ScheduledMessageController::class, 'cancel'])->name('scheduled-messages.cancel');
        Route::delete('/scheduled-messages/{scheduledMessage}', [ScheduledMessageController::class, 'destroy'])->name('scheduled-messages.destroy');
    });
    Route::get('/', [InboxController::class, 'index'])->name('index');
    Route::get('/updates', [InboxController::class, 'updates'])->name('workspace-updates');
    Route::get('/notification-updates', [InboxController::class, 'notificationUpdates'])->name('notification-updates');
    Route::get('/contacts/search', [InboxController::class, 'contactSearch'])->name('contacts.search');
    Route::get('/channel-accounts', [InboxController::class, 'channelAccounts'])->name('channel-accounts');
    Route::get('/templates', [InboxController::class, 'templates'])->name('templates');
    Route::post('/conversations/{conversation}/upload-media', [InboxController::class, 'uploadMedia'])->name('upload-media');
    Route::get('/conversations/{conversation}/messages/{message}/media', [InboxController::class, 'serveMedia'])->name('message-media');
    Route::post('/conversations/{conversation}/messages/{message}/transcribe', [InboxController::class, 'transcribeAudio'])->name('message-transcribe');
    Route::post('/conversations/start', [InboxController::class, 'startConversation'])->name('start');
    Route::get('/conversations/{conversation}/updates', [InboxController::class, 'updates'])->name('updates');
    Route::get('/conversations/{conversation}', [InboxController::class, 'show'])->name('show');
    Route::post('/conversations/{conversation}/reply', [InboxController::class, 'reply'])->name('reply')->middleware('limit:whatsapp_messages_per_month,whatsapp_messages');
    Route::post('/conversations/{conversation}/share-product', [InboxController::class, 'shareProduct'])->name('share-product')->middleware('limit:whatsapp_messages_per_month,whatsapp_messages');
    Route::post('/conversations/{conversation}/kanban', [InboxController::class, 'updateKanban'])->name('kanban.update');
    Route::patch('/conversations/{conversation}/contact-permissions', [InboxController::class, 'updateContactPermissions'])->name('contact-permissions.update');
    Route::patch('/conversations/{conversation}/contact-segmentation', [InboxController::class, 'updateContactSegmentation'])->name('contact-segmentation.update');
    Route::post('/contact-tags', [InboxController::class, 'storeContactTag'])->name('contact-tags.store');
    Route::post('/conversations/{conversation}/assign', [InboxController::class, 'assign'])->name('assign');
    Route::post('/conversations/{conversation}/status', [InboxController::class, 'updateStatus'])->name('status');
    Route::post('/conversations/{conversation}/typing', [InboxController::class, 'typing'])->name('typing');
    Route::get('/conversations/{conversation}/notes', [InternalNoteController::class, 'index'])->name('notes.index');
    Route::post('/conversations/{conversation}/notes', [InternalNoteController::class, 'store'])->name('notes.store');
    Route::post('/conversations/{conversation}/handover', [InboxController::class, 'handover'])->name('handover');

    // Canned replies
    Route::get('/canned-replies', [CannedReplyController::class, 'index'])->name('canned-replies.index');
    Route::get('/canned-replies/list', [CannedReplyController::class, 'list'])->name('canned-replies.list');
    Route::post('/canned-replies', [CannedReplyController::class, 'store'])->name('canned-replies.store');
    Route::put('/canned-replies/{cannedReply}', [CannedReplyController::class, 'update'])->name('canned-replies.update');
    Route::delete('/canned-replies/{cannedReply}', [CannedReplyController::class, 'destroy'])->name('canned-replies.destroy');

    // Labels
    Route::get('/labels', [LabelController::class, 'index'])->name('labels.index');
    Route::post('/labels', [LabelController::class, 'store'])->name('labels.store');
    Route::put('/labels/{label}', [LabelController::class, 'update'])->name('labels.update');
    Route::delete('/labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');
    Route::post('/conversations/{conversation}/labels', [LabelController::class, 'attach'])->name('labels.attach');
    Route::delete('/conversations/{conversation}/labels/{label}', [LabelController::class, 'detach'])->name('labels.detach');

    // Channel account setup (Instagram / Messenger)
    Route::middleware('module:channel_management')->group(function () {
        Route::get('/setup', [InboxSetupController::class, 'index'])->name('setup');
        Route::get('/setup/webchat', [WebchatChannelController::class, 'index'])->name('webchat.index');
        Route::post('/setup/webchat', [WebchatChannelController::class, 'store'])->name('webchat.store');
        Route::put('/setup/webchat/{channelAccount}', [WebchatChannelController::class, 'update'])->name('webchat.update');
        Route::delete('/setup/webchat/{channelAccount}', [WebchatChannelController::class, 'destroy'])->name('webchat.destroy');
        Route::post('/setup/embedded-signup/instagram', [InboxSetupController::class, 'embeddedSignupInstagram'])->name('setup.embedded-signup.instagram');
        Route::post('/setup/embedded-signup/messenger', [InboxSetupController::class, 'embeddedSignupMessenger'])->name('setup.embedded-signup.messenger');
        Route::patch('/setup/{channelAccount}/chatbot', [InboxSetupController::class, 'assignChatbot'])->name('setup.assign-chatbot');
        Route::patch('/setup/{channelAccount}/operations', [InboxSetupController::class, 'updateOperations'])->name('setup.operations.update');
        Route::delete('/setup/{channelAccount}', [InboxSetupController::class, 'destroy'])->name('setup.destroy');
    });
});
