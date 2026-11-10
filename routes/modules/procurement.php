<?php

use App\Http\Controllers\Procurement\SupplierController;
use Illuminate\Support\Facades\Route;

/*
 * Procurement: suppliers (requisitions and goods receiving are added in later phases).
 */

Route::resource('suppliers', SupplierController::class);
