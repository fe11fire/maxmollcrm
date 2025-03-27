<?php

use App\Http\Controllers\HistoryStockController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\WarehouseController;
use App\Http\Resources\OrderCollection;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function (Request $request) {
    return 'hello';
});

Route::get('/warehouses', WarehouseController::class);

Route::get('/stocks', StockController::class);

Route::get('/orders', [OrderController::class, 'index']);

Route::post('/order', [OrderController::class, 'create']);

Route::put('/order', [OrderController::class, 'update']);

Route::put('/order/complete', [OrderController::class, 'complete']);

Route::put('/order/cancel', [OrderController::class, 'cancel']);

Route::put('/order/resume', [OrderController::class, 'resume']);

Route::get('/order/{id}/complete', [OrderController::class, 'getComplete']);

Route::get('/order/{id}/cancel', [OrderController::class, 'getCancel']);

Route::get('/order/{id}/resume', [OrderController::class, 'getResume']);

Route::get('/stocks/history', HistoryStockController::class);
