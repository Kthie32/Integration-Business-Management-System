<?php

use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

/*
 * Reports hub (FR-RPT-01…06). Each report checks its own permission; PDF and Excel also need reports.export.
 */

Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{report}', 'show')->where('report', '[a-z0-9-]+')->name('show');
    Route::get('{report}/{format}', 'export')->where(['report' => '[a-z0-9-]+', 'format' => 'pdf|xlsx|print'])->name('export');
});
