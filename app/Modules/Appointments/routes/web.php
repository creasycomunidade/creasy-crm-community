<?php

use App\Modules\Appointments\Http\Controllers\AppointmentController;
use App\Modules\Appointments\Http\Controllers\AppointmentWorkspaceController;
use App\Modules\Appointments\Http\Controllers\PublicAppointmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->prefix('agendar')->name('appointments.public.')->group(function () {
    Route::get('/{token}', [PublicAppointmentController::class, 'show'])->name('show');
    Route::get('/{token}/profissional/{resourceUuid}', [PublicAppointmentController::class, 'show'])->name('professional');
    Route::get('/{token}/horarios', [PublicAppointmentController::class, 'slots'])->middleware('throttle:120,1')->name('slots');
    Route::post('/{token}', [PublicAppointmentController::class, 'store'])->middleware('throttle:10,1')->name('store');
});

Route::middleware(['web', 'client-app', 'module:appointments'])->prefix('painel/agendamentos')->name('client.appointments.')->group(function () {
    Route::get('/', [AppointmentWorkspaceController::class, 'dashboard'])->name('index');
    Route::get('/calendario', [AppointmentWorkspaceController::class, 'calendar'])->name('calendar');
    Route::get('/lista', [AppointmentWorkspaceController::class, 'appointments'])->name('list');
    Route::get('/clientes', [AppointmentWorkspaceController::class, 'clients'])->name('clients');
    Route::get('/servicos', [AppointmentWorkspaceController::class, 'services'])->name('services.index');
    Route::post('/servicos', [AppointmentWorkspaceController::class, 'storeService'])->name('services.store');
    Route::patch('/servicos/{service}', [AppointmentWorkspaceController::class, 'updateService'])->name('services.update');
    Route::patch('/servicos/{service}/alternar', [AppointmentWorkspaceController::class, 'toggleService'])->name('services.toggle');
    Route::get('/profissionais', [AppointmentWorkspaceController::class, 'resources'])->name('resources.index');
    Route::post('/profissionais', [AppointmentWorkspaceController::class, 'storeResource'])->name('resources.store');
    Route::patch('/profissionais/{resource}', [AppointmentWorkspaceController::class, 'updateResource'])->name('resources.update');
    Route::patch('/profissionais/{resource}/alternar', [AppointmentWorkspaceController::class, 'toggleResource'])->name('resources.toggle');
    Route::patch('/profissionais/{resource}/agenda', [AppointmentWorkspaceController::class, 'toggleResourceBooking'])->name('resources.booking.toggle');
    Route::get('/disponibilidade', [AppointmentWorkspaceController::class, 'availability'])->name('availability.index');
    Route::put('/disponibilidade/{resource}', [AppointmentWorkspaceController::class, 'updateAvailability'])->name('availability.update');
    Route::get('/horarios', [AppointmentWorkspaceController::class, 'slots'])->name('slots');
    Route::get('/canais', [AppointmentWorkspaceController::class, 'channels'])->name('channels.index');
    Route::put('/canais', [AppointmentWorkspaceController::class, 'updateChannels'])->name('channels.update');
    Route::post('/', [AppointmentController::class, 'store'])->name('store');
    Route::patch('/{appointment}', [AppointmentController::class, 'update'])->name('update');
    Route::patch('/{appointment}/status', [AppointmentController::class, 'status'])->name('status');
});
