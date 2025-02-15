<?php

use App\Http\Controllers\Api\Admin\AttributeValueController;
use App\Http\Controllers\Api\Admin\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\VoucherController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



Route::prefix('admin')->group(function () {
    // Admin Category
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/get-all-categories', [CategoryController::class, 'getParentCategories']);
        Route::get('/update/{id}', [CategoryController::class, 'show']);
        Route::post('/create', [CategoryController::class, 'store']);
        Route::put('/update/{id}', [CategoryController::class, 'update']);
        Route::delete('/delete/{id}', [CategoryController::class, 'destroy']);
        Route::delete('/hard-delete/{id}', [CategoryController::class, 'hardDelete']);
        Route::patch('/restore/{id}', [CategoryController::class, 'restore']);
        Route::get('/trash', [CategoryController::class, 'trash']);
    });

    // Admin Attribute
    Route::apiResource('attributes', AttributeValueController::class);

    // Admin Attribute Value
    Route::prefix('attribute_values')->group(function () {
        Route::get('/list/{id}', [AttributeValueController::class, 'index']);
        Route::get('/update/{id}', [AttributeValueController::class, 'show']);
        Route::post('/create', [AttributeValueController::class, 'store']);
        Route::put('/update/{id}', [AttributeValueController::class, 'update']);
        Route::delete('/delete/{id}', [AttributeValueController::class, 'destroy']);
    });

    // Admin Voucher
    Route::prefix('vouchers')->group(function () {
        Route::get('/', [VoucherController::class, 'index']);
        Route::post('/create', [VoucherController::class, 'store']);
        Route::get('/{code}', [VoucherController::class, 'show']);
        Route::put('/update/{id}', [VoucherController::class, 'update']);
        Route::delete('/delete/{code}', [VoucherController::class, 'destroy']);
    });
});
