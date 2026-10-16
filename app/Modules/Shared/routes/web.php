<?php

use App\Modules\Shared\Http\Controllers\ContactController;
use App\Modules\Shared\Http\Controllers\SegmentController;
use App\Modules\Shared\Http\Controllers\WhatsappContactImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'client-app', 'module:contacts'])->prefix('painel')->name('client.')->group(function () {
    // Contacts
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/bulk-import', [ContactController::class, 'bulkImport'])->name('contacts.bulk-import');
    Route::get('/contacts/whatsapp-import', [WhatsappContactImportController::class, 'index'])->name('contacts.whatsapp-import');
    Route::post('/contacts/whatsapp-import/groups', [WhatsappContactImportController::class, 'groups'])->name('contacts.whatsapp-import.groups');
    Route::post('/contacts/whatsapp-import/preview', [WhatsappContactImportController::class, 'preview'])->name('contacts.whatsapp-import.preview');
    Route::post('/contacts/whatsapp-import', [WhatsappContactImportController::class, 'store'])->name('contacts.whatsapp-import.store');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::post('/contacts/bulk', [ContactController::class, 'bulkStore'])->name('contacts.bulk-store');
    Route::post('/contacts/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::delete('/contacts', [ContactController::class, 'bulkDestroy'])->name('contacts.bulk-destroy');
    Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
    Route::post('/contacts/{contact}/avatar', [ContactController::class, 'uploadAvatar'])->name('contacts.avatar.upload');
    Route::delete('/contacts/{contact}/avatar', [ContactController::class, 'deleteAvatar'])->name('contacts.avatar.delete');

    // Segments
    Route::get('/segments', [SegmentController::class, 'index'])->name('segments.index');
    Route::post('/segments', [SegmentController::class, 'store'])->name('segments.store');
    Route::put('/segments/{segment}', [SegmentController::class, 'update'])->name('segments.update');
    Route::delete('/segments/{segment}', [SegmentController::class, 'destroy'])->name('segments.destroy');
    Route::get('/segments/{segment}/contacts', [SegmentController::class, 'manageContacts'])->name('segments.contacts');
    Route::post('/segments/{segment}/contacts', [SegmentController::class, 'attachContacts'])->name('segments.contacts.attach');
    Route::delete('/segments/{segment}/contacts/{contact}', [SegmentController::class, 'detachContact'])->name('segments.contacts.detach');
});
