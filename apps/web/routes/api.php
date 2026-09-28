<?php

use App\Http\Controllers\Api\Internal\Account\UserController as InternalUserController;
use App\Http\Controllers\Api\V1\Account\AuthController as V1AuthController;
use App\Http\Controllers\Api\V1\Account\UserController as V1UserController;
use Illuminate\Support\Facades\Route;

/*
| /api/internal/* Session + CSRF for the web (Inertia) frontend. Api\Internal controllers.
| /api/v1/*       Bearer token (Sanctum) for the mobile and desktop clients. Api\V1 controllers,
|                 versioned apart from Internal so the external contract stays stable.
| Both call the same services.
*/

Route::middleware(['web', 'auth'])->prefix('internal')->name('api.internal.')->group(function () {
    Route::apiResource('users', InternalUserController::class)->except(['destroy']);
    Route::patch('users/{user}/status', [InternalUserController::class, 'updateStatus'])->name('users.status');
});

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [V1AuthController::class, 'login'])->middleware('throttle:login')->name('login');
        Route::post('forgot-password', [V1AuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('forgot-password');
        Route::post('reset-password', [V1AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('reset-password');

        Route::middleware(['auth:sanctum', 'active'])->group(function () {
            Route::post('logout', [V1AuthController::class, 'logout'])->name('logout');
            Route::get('me', [V1AuthController::class, 'me'])->name('me');
        });
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::apiResource('users', V1UserController::class)->except(['destroy']);
        Route::patch('users/{user}/status', [V1UserController::class, 'updateStatus'])->name('users.status');
    });
});
