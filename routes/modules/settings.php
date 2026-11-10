<?php

use App\Http\Controllers\Settings\FinanceSettingsController;
use App\Http\Controllers\Settings\NumberingSettingsController;
use App\Http\Controllers\Settings\OrganizationSettingsController;
use App\Http\Controllers\Settings\SystemSettingsController;
use App\Http\Controllers\Settings\TaxRateController;
use Illuminate\Support\Facades\Route;

/*
 * Settings. Each section is limited to its permission: settings.organization, settings.finance or settings.system.
 */

Route::prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/', [SystemSettingsController::class, 'index'])->name('index');

    Route::middleware('permission:settings.organization')->controller(OrganizationSettingsController::class)->group(function (): void {
        Route::get('organization', 'edit')->name('organization.edit');
        Route::put('organization', 'update')->name('organization.update');
        Route::delete('organization/logo', 'destroyLogo')->name('organization.logo.destroy');
    });

    Route::middleware('permission:settings.finance')->group(function (): void {
        Route::get('finance', [FinanceSettingsController::class, 'edit'])->name('finance.edit');
        Route::put('finance', [FinanceSettingsController::class, 'update'])->name('finance.update');

        Route::resource('taxes', TaxRateController::class)
            ->parameters(['taxes' => 'tax_rate'])
            ->except(['show', 'destroy']);

        Route::get('numbering', [NumberingSettingsController::class, 'index'])->name('numbering.index');
        Route::put('numbering/{documentType}', [NumberingSettingsController::class, 'update'])->name('numbering.update');
    });

    Route::middleware('permission:settings.system')->controller(SystemSettingsController::class)->group(function (): void {
        Route::get('system', 'edit')->name('system.edit');
        Route::put('system', 'update')->name('system.update');
    });
});
