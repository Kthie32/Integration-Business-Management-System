<?php

use App\Http\Controllers\Sales\CustomerController;
use Illuminate\Support\Facades\Route;

/*
 * Sales: customers (quotations, invoices and payments are added in later phases).
 */

Route::resource('customers', CustomerController::class);
