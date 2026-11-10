<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserAccountController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Administration: users, roles and the audit log. Authorization is enforced by policies in each controller.
 */

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::resource('users', UserController::class)->except('destroy');

    Route::controller(UserAccountController::class)->prefix('users/{user}')->name('users.')->group(function (): void {
        Route::patch('deactivate', 'deactivate')->name('deactivate');
        Route::patch('activate', 'activate')->name('activate');
        Route::post('password-link', 'sendPasswordLink')->middleware('throttle:6,1')->name('password-link');
        Route::delete('sessions', 'endAllSessions')->name('sessions.destroy-all');
        Route::delete('sessions/{session}', 'endSession')->name('sessions.destroy');
    });

    Route::resource('roles', RoleController::class)->except('show');

    Route::middleware('permission:audit.view')->group(function (): void {
        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-log/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });
});
