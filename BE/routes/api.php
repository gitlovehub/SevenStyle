<?php

use App\Http\Controllers\Api\Admin\CommentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::prefix('admin')->group(function () {
    Route::prefix('comments')->group(function () {
        Route::patch('{comment}/hide', [CommentController::class, 'hide']);
        Route::get('search', [CommentController::class, 'search']);
        Route::get('hidden', [CommentController::class, 'hiddenComment']);
    });
    Route::apiResource('comments', CommentController::class);
});
