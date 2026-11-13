<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NeaMatrix\Connex\Http\Middleware\SetConnexLocale;
use NeaMatrix\Connex\Login\Controllers\LoginController;
use NeaMatrix\Connex\Login\Controllers\LoginPageController;

Route::middleware(SetConnexLocale::class)->group(function (): void {
    Route::get('login', LoginPageController::class)->name('connex.login');

    Route::get('login/script', [LoginController::class, 'script'])
        ->middleware('throttle:connex-script')
        ->name('connex.login.script');

    Route::post('login/otp', [LoginController::class, 'requestOtp'])
        ->middleware('throttle:connex-otp')
        ->name('connex.login.otp');

    Route::post('login/otp/confirm', [LoginController::class, 'confirmOtp'])
        ->middleware('throttle:connex-confirm')
        ->name('connex.login.confirm');
});
