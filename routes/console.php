<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Daily summary of low-stock items (the command itself honours the low-stock setting).
 */
Schedule::command('ibms:stock:low-stock-check')->dailyAt('07:00')->withoutOverlapping();

/*
 * Weekly ledger check: reports (without changing anything) any balance that differs from the movements.
 */
Schedule::command('ibms:stock:reconcile')->weeklyOn(1, '06:00')->withoutOverlapping();
