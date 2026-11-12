<?php

use App\Http\Controllers\HR\DepartmentController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\EmployeeDocumentController;
use App\Http\Controllers\HR\EmployeeStatusController;
use App\Http\Controllers\HR\SalaryStructureController;
use Illuminate\Support\Facades\Route;

/*
 * People: departments and employees. Authorization is enforced by policies in each controller and form request.
 */

Route::resource('departments', DepartmentController::class)->except('show');

Route::resource('employees', EmployeeController::class)->except('destroy');
Route::patch('employees/{employee}/status', EmployeeStatusController::class)->name('employees.status.update');

Route::scopeBindings()->controller(EmployeeDocumentController::class)->prefix('employees/{employee}/documents')->name('employees.documents.')->group(function (): void {
    Route::post('/', 'store')->name('store');
    Route::get('{document}', 'show')->name('show');
    Route::delete('{document}', 'destroy')->name('destroy');
});

Route::controller(SalaryStructureController::class)->prefix('employees/{employee}/salary-structures')->name('employees.salary-structures.')->group(function (): void {
    Route::post('/', 'store')->name('store');
    Route::delete('{structure}', 'destroy')->name('destroy');
});
