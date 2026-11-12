<?php

use App\Http\Controllers\Payroll\PayComponentController;
use App\Http\Controllers\Payroll\PayrollAdjustmentController;
use App\Http\Controllers\Payroll\PayrollExportController;
use App\Http\Controllers\Payroll\PayrollRunController;
use App\Http\Controllers\Payroll\PayrollRunWorkflowController;
use App\Http\Controllers\Payroll\PayslipController;
use App\Http\Controllers\Payroll\StatutoryRateController;
use Illuminate\Support\Facades\Route;

/*
 * Payroll: runs, payslips and payroll configuration. Authorization is enforced by policies in each controller.
 */

Route::resource('payroll-runs', PayrollRunController::class)->except(['edit', 'update'])
    ->parameters(['payroll-runs' => 'payrollRun']);

Route::controller(PayrollRunWorkflowController::class)->prefix('payroll-runs/{payrollRun}')->name('payroll-runs.')->group(function (): void {
    Route::post('calculate', 'calculate')->name('calculate');
    Route::post('submit', 'submit')->name('submit');
    Route::post('approve', 'approve')->name('approve');
    Route::post('return', 'return')->name('return');
    Route::post('mark-paid', 'markPaid')->name('mark-paid');
});

Route::scopeBindings()->controller(PayrollAdjustmentController::class)->prefix('payroll-runs/{payrollRun}/adjustments')->name('payroll-runs.adjustments.')->group(function (): void {
    Route::post('/', 'store')->name('store');
    Route::delete('{adjustment}', 'destroy')->name('destroy');
});

Route::get('payroll-runs/{payrollRun}/export/{report}.{format}', PayrollExportController::class)
    ->whereIn('report', ['register', 'deductions', 'payments'])
    ->whereIn('format', ['pdf', 'xlsx', 'print'])
    ->name('payroll-runs.export');

Route::get('my-payslips', [PayslipController::class, 'mine'])->name('payslips.mine');

Route::controller(PayslipController::class)->prefix('payslips/{payslip}')->name('payslips.')->group(function (): void {
    Route::get('/', 'show')->name('show');
    Route::get('pdf', 'pdf')->name('pdf');
    Route::get('print', 'print')->name('print');
});

Route::resource('pay-components', PayComponentController::class)->except(['create', 'show'])
    ->parameters(['pay-components' => 'payComponent']);

Route::resource('statutory-rates', StatutoryRateController::class)->except(['show', 'destroy'])
    ->parameters(['statutory-rates' => 'statutoryRate']);
Route::post('statutory-rates/{statutoryRate}/verify', [StatutoryRateController::class, 'verify'])->name('statutory-rates.verify');
