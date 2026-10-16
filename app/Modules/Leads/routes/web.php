<?php

use App\Modules\Leads\Http\Controllers\LeadController;
use App\Modules\Leads\Http\Controllers\PipelineController;
use App\Modules\Leads\Http\Controllers\SalesFunnelController;
use App\Modules\Leads\Http\Controllers\TaskKanbanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'client-app', 'module:crm'])->prefix('painel/crm/pipelines')->name('client.crm.pipelines.')->group(function () {
    Route::get('/', [PipelineController::class, 'index'])->name('index');
    Route::post('/', [PipelineController::class, 'store'])->name('store');
    Route::put('/{pipeline}', [PipelineController::class, 'update'])->name('update');
    Route::delete('/{pipeline}', [PipelineController::class, 'destroy'])->name('destroy');

    Route::post('/{pipeline}/stages', [PipelineController::class, 'storeStage'])->name('stages.store');
    Route::put('/stages/{stage}', [PipelineController::class, 'updateStage'])->name('stages.update');
    Route::delete('/stages/{stage}', [PipelineController::class, 'destroyStage'])->name('stages.destroy');

    Route::post('/{pipeline}/deals', [PipelineController::class, 'storeDeal'])->name('deals.store');
    Route::put('/deals/{deal}', [PipelineController::class, 'updateDeal'])->name('deals.update');
    Route::patch('/deals/{deal}/move', [PipelineController::class, 'moveDeal'])->name('deals.move');
    Route::delete('/deals/{deal}', [PipelineController::class, 'destroyDeal'])->name('deals.destroy');
});

Route::middleware(['web', 'client-app', 'module:crm'])
    ->get('painel/crm/funil', SalesFunnelController::class)
    ->name('client.crm.funnel.index');

Route::middleware(['web', 'client-app', 'module:crm'])->prefix('painel/crm/tasks')->name('client.crm.tasks.')->group(function () {
    Route::get('/', [TaskKanbanController::class, 'index'])->name('index');
    Route::post('/', [TaskKanbanController::class, 'store'])->name('store');
    Route::post('/boards', [TaskKanbanController::class, 'storeBoard'])->name('boards.store');
    Route::put('/boards/{board}', [TaskKanbanController::class, 'updateBoard'])->name('boards.update');
    Route::delete('/boards/{board}', [TaskKanbanController::class, 'destroyBoard'])->name('boards.destroy');
    Route::put('/{task}', [TaskKanbanController::class, 'update'])->name('update');
    Route::patch('/{task}/move', [TaskKanbanController::class, 'move'])->name('move');
    Route::patch('/{task}/checklist/{item}', [TaskKanbanController::class, 'toggleChecklist'])->name('checklist.toggle');
    Route::delete('/{task}', [TaskKanbanController::class, 'destroy'])->name('destroy');
    Route::post('/columns', [TaskKanbanController::class, 'storeColumn'])->name('columns.store');
    Route::put('/columns/{column}', [TaskKanbanController::class, 'updateColumn'])->name('columns.update');
});

Route::middleware(['web', 'client-app', 'module:crm'])
    ->post('painel/inbox/conversations/{conversation}/messages/{message}/task', [TaskKanbanController::class, 'storeFromMessage'])
    ->name('client.inbox.messages.task');
