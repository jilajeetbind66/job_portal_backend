<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Authentication Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('web')->group(function () {

        Route::post('/auth/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1')
            ->name('auth.register');

        Route::post('/auth/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('auth.login');


        /*
        |--------------------------------------------------------------------------
        | Authenticated Routes
        |--------------------------------------------------------------------------
        */

  Route::middleware([
    'auth:sanctum',
    'user.active',
    'partner.active',
     ])->group(function () {

    Route::get('/auth/me', [AuthController::class, 'me'])
        ->name('auth.me');

    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->name('auth.logout');
       });
    });
});