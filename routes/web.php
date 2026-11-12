<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
 * Authentication routes (login, password reset, two-factor, profile/password updates) are registered by Fortify.
 */

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function (): void {
    /*
     * The signed-in user's own account. Kept outside the two-factor requirement so users can set it up here.
     */
    Route::prefix('account')->name('account.')->controller(AccountController::class)->group(function (): void {
        Route::get('profile', 'profile')->name('profile');
        Route::get('security', 'security')->name('security');
        Route::delete('sessions/others', 'endOtherSessions')->name('sessions.destroy-others');
        Route::delete('sessions/{session}', 'endSession')->name('sessions.destroy');
    });

    Route::middleware('two-factor.enforced')->group(function (): void {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::controller(NotificationController::class)->prefix('notifications')->name('notifications.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('read-all', 'markAllAsRead')->name('read-all');
            Route::post('{notification}/open', 'open')->name('open');
        });

        require __DIR__.'/modules/admin.php';
        require __DIR__.'/modules/settings.php';
        require __DIR__.'/modules/sales.php';
        require __DIR__.'/modules/finance.php';
        require __DIR__.'/modules/procurement.php';
        require __DIR__.'/modules/approvals.php';
        require __DIR__.'/modules/hr.php';
        require __DIR__.'/modules/payroll.php';
        require __DIR__.'/modules/inventory.php';
    });
});
