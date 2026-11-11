<?php

use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\QuotationDocumentController;
use App\Http\Controllers\Sales\QuotationWorkflowController;
use Illuminate\Support\Facades\Route;

/*
 * Sales: customers and quotations (invoices and payments are added in later phases).
 */

Route::resource('customers', CustomerController::class);

Route::resource('quotations', QuotationController::class);

Route::controller(QuotationWorkflowController::class)->prefix('quotations/{quotation}')->name('quotations.')->group(function (): void {
    Route::post('submit', 'submit')->name('submit');
    Route::post('approve', 'approve')->name('approve');
    Route::post('return', 'returnToDraft')->name('return');
    Route::post('sent', 'markSent')->name('sent');
    Route::post('accept', 'accept')->name('accept');
    Route::post('reject', 'reject')->name('reject');
    Route::post('cancel', 'cancel')->name('cancel');
    Route::post('revise', 'revise')->name('revise');
});

Route::controller(QuotationDocumentController::class)->prefix('quotations/{quotation}')->name('quotations.')->group(function (): void {
    Route::get('pdf', 'pdf')->middleware('throttle:30,1')->name('pdf');
    Route::get('print', 'print')->name('print');
});
