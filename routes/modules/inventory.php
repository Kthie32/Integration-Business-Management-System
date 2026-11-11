<?php

use App\Http\Controllers\Inventory\GoodsReceiptController;
use App\Http\Controllers\Inventory\ItemCategoryController;
use App\Http\Controllers\Inventory\ItemController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
 * Inventory: items, categories, warehouses, stock ledger, goods receiving and adjustments.
 * Authorization is enforced by policies and permission gates in each controller.
 */

Route::get('items/{item}/stock-card', [ItemController::class, 'stockCard'])->name('items.stock-card');
Route::resource('items', ItemController::class);

Route::resource('item-categories', ItemCategoryController::class)->except(['create', 'show'])
    ->parameters(['item-categories' => 'item_category']);

Route::resource('warehouses', WarehouseController::class)->except(['show', 'destroy']);

Route::controller(StockController::class)->prefix('stock')->name('stock.')->group(function (): void {
    Route::get('levels', 'levels')->name('levels');
    Route::get('movements', 'movements')->name('movements');
    Route::post('movements/{movement}/reverse', 'reverse')->name('movements.reverse');
    Route::get('{operation}', 'create')->whereIn('operation', ['issue', 'transfer', 'opening'])->name('operations.create');
    Route::post('{operation}', 'store')->whereIn('operation', ['issue', 'transfer', 'opening'])->name('operations.store');
});

Route::resource('goods-receipts', GoodsReceiptController::class)->parameters(['goods-receipts' => 'goods_receipt']);
Route::post('goods-receipts/{goods_receipt}/post', [GoodsReceiptController::class, 'post'])->name('goods-receipts.post');
Route::post('goods-receipts/{goods_receipt}/void', [GoodsReceiptController::class, 'void'])->name('goods-receipts.void');

Route::resource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'create', 'store', 'show'])
    ->parameters(['stock-adjustments' => 'stock_adjustment']);
Route::controller(StockAdjustmentController::class)->prefix('stock-adjustments/{stock_adjustment}')->name('stock-adjustments.')->group(function (): void {
    Route::post('approve', 'approve')->name('approve');
    Route::post('reject', 'reject')->name('reject');
    Route::post('cancel', 'cancel')->name('cancel');
});
