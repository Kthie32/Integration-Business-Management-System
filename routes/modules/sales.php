<?php

use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\InvoiceController;
use App\Http\Controllers\Sales\InvoiceDocumentController;
use App\Http\Controllers\Sales\InvoiceWorkflowController;
use App\Http\Controllers\Sales\PaymentController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\QuotationDocumentController;
use App\Http\Controllers\Sales\QuotationWorkflowController;
use Illuminate\Support\Facades\Route;

/*
 * Sales: customers, quotations, invoices and payments.
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

Route::post('quotations/{quotation}/convert', [InvoiceWorkflowController::class, 'convertQuotation'])->name('quotations.convert');

Route::resource('invoices', InvoiceController::class);

Route::controller(InvoiceWorkflowController::class)->prefix('invoices/{invoice}')->name('invoices.')->group(function (): void {
    Route::post('issue', 'issue')->name('issue');
    Route::post('void', 'void')->name('void');
    Route::post('copy', 'copy')->name('copy');
});

Route::controller(InvoiceDocumentController::class)->prefix('invoices/{invoice}')->name('invoices.')->group(function (): void {
    Route::get('pdf', 'pdf')->middleware('throttle:30,1')->name('pdf');
    Route::get('print', 'print')->name('print');
});

Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
