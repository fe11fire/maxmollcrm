<?php

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
