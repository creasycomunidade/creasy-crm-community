<?php

use App\Modules\AI\Http\Controllers\AiChatbotController;
use App\Modules\AI\Http\Controllers\AiKnowledgeBaseController;
use App\Modules\AI\Http\Controllers\AiProviderController;
use App\Modules\AI\Http\Controllers\GoogleDriveKnowledgeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'client-app', 'module:ai'])->prefix('painel/ai')->name('client.ai.')->group(function () {
    // Provider configs
    Route::get('/providers', [AiProviderController::class, 'index'])->name('providers.index');
    Route::put('/providers/{provider}', [AiProviderController::class, 'update'])->name('providers.update');

    // Knowledge bases
    Route::get('/knowledge-bases', [AiKnowledgeBaseController::class, 'index'])->name('knowledge-bases.index');
    Route::post('/knowledge-bases', [AiKnowledgeBaseController::class, 'store'])->name('knowledge-bases.store')->middleware('limit:knowledge_bases,knowledge_bases');
    Route::get('/knowledge-bases/{kb}', [AiKnowledgeBaseController::class, 'show'])->name('knowledge-bases.show');
    Route::post('/knowledge-bases/{kb}/documents', [AiKnowledgeBaseController::class, 'addDocument'])->name('knowledge-bases.documents.add');
    Route::get('/documents/{document}/content', [AiKnowledgeBaseController::class, 'documentContent'])->name('documents.content');
    Route::post('/markdown/preview', [AiKnowledgeBaseController::class, 'markdownPreview'])->name('markdown.preview');
    Route::post('/documents/{document}/reindex', [AiKnowledgeBaseController::class, 'reindex'])->name('documents.reindex');
    Route::patch('/documents/{document}', [AiKnowledgeBaseController::class, 'updateDocument'])->name('documents.update');
    Route::delete('/documents/{document}', [AiKnowledgeBaseController::class, 'destroyDocument'])->name('documents.destroy');

    // Google Drive sources — OAuth credentials and indexed content are isolated
    // per workspace. The callback remains behind the authenticated client app.
    Route::get('/google-drive/connect', [GoogleDriveKnowledgeController::class, 'connect'])->name('google-drive.connect');
    Route::get('/google-drive/files', [GoogleDriveKnowledgeController::class, 'files'])->name('google-drive.files');
    Route::post('/knowledge-bases/{kb}/google-drive', [GoogleDriveKnowledgeController::class, 'import'])->name('knowledge-bases.google-drive.import');
    Route::delete('/google-drive', [GoogleDriveKnowledgeController::class, 'disconnect'])->name('google-drive.disconnect');

    // Chatbots
    Route::get('/chatbots', [AiChatbotController::class, 'index'])->name('chatbots.index');
    Route::post('/chatbots', [AiChatbotController::class, 'store'])->name('chatbots.store');
    Route::put('/chatbots/{chatbot}', [AiChatbotController::class, 'update'])->name('chatbots.update');
    Route::delete('/chatbots/{chatbot}', [AiChatbotController::class, 'destroy'])->name('chatbots.destroy');
    Route::post('/chatbots/{chatbot}/playground', [AiChatbotController::class, 'playground'])->name('chatbots.playground');

    Route::get('/google-drive/callback', [GoogleDriveKnowledgeController::class, 'callback'])->name('google-drive.callback');
});
