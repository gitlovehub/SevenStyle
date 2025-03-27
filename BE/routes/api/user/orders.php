<?php

use App\Http\Controllers\Api\User\OrderClientController;
use Illuminate\Support\Facades\Route;
Route::get('/order_statuses', [OrderClientController::class, 'getOrderStatuses']);
Route::get('/orders_for_user', [OrderClientController::class, 'getOrdersForUser']);
Route::get('/order_detail/{code}', [OrderClientController::class, 'getOrderDetail']);