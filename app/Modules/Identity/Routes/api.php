<?php

use App\Modules\Identity\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Public auth routes — throttled to mitigate brute-force / credential stuffing.
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('register',       [AuthController::class, 'register']);
    Route::post('login',          [AuthController::class, 'login']);
    Route::post('forgot-password',[AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
});

// Authenticated auth routes
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me',      [AuthController::class, 'me']);
});
