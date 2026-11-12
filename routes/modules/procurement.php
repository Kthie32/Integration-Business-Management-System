<?php

use App\Http\Controllers\Procurement\RequisitionController;
use App\Http\Controllers\Procurement\RequisitionDocumentController;
use App\Http\Controllers\Procurement\RequisitionFulfilmentController;
use App\Http\Controllers\Procurement\RequisitionWorkflowController;
use App\Http\Controllers\Procurement\SupplierController;
use Illuminate\Support\Facades\Route;

/*
 * Procurement: suppliers and requisitions. Authorization is enforced by policies in each controller.
 */

Route::resource('suppliers', SupplierController::class);

Route::resource('requisitions', RequisitionController::class);
Route::get('requisitions/{requisition}/attachment', [RequisitionController::class, 'attachment'])->name('requisitions.attachment');

Route::controller(RequisitionWorkflowController::class)->prefix('requisitions/{requisition}')->name('requisitions.')->group(function (): void {
    Route::post('submit', 'submit')->name('submit');
    Route::post('approve', 'approve')->name('approve');
    Route::post('reject', 'reject')->name('reject');
    Route::post('return', 'return')->name('return');
    Route::post('cancel', 'cancel')->name('cancel');
});

Route::controller(RequisitionFulfilmentController::class)->prefix('requisitions/{requisition}/fulfil')->name('requisitions.fulfil')->group(function (): void {
    Route::get('/', 'create');
    Route::post('/', 'store')->name('.store');
});

Route::controller(RequisitionDocumentController::class)->prefix('requisitions/{requisition}')->name('requisitions.')->group(function (): void {
    Route::get('pdf', 'pdf')->name('pdf');
    Route::get('print', 'print')->name('print');
});
