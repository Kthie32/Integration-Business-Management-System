<?php

use App\Http\Controllers\Finance\ExpenseCategoryController;
use App\Http\Controllers\Finance\ExpenseController;
use Illuminate\Support\Facades\Route;

/*
 * Finance: expenses and expense categories. Authorization is enforced by policies in each controller.
 */

Route::resource('expense-categories', ExpenseCategoryController::class)->except(['create', 'show'])
    ->parameters(['expense-categories' => 'expense_category']);

Route::resource('expenses', ExpenseController::class);
Route::controller(ExpenseController::class)->prefix('expenses/{expense}')->name('expenses.')->group(function (): void {
    Route::post('record', 'record')->name('record');
    Route::post('void', 'void')->name('void');
    Route::get('attachment', 'attachment')->name('attachment');
});
