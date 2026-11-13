<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NeaMatrix\Connex\Account\Controllers\AccountController;
use NeaMatrix\Connex\Account\Controllers\SubscriptionController;
use NeaMatrix\Connex\Http\Middleware\SetConnexLocale;

Route::middleware(SetConnexLocale::class)->prefix('account')->group(function (): void {
    Route::get('/', AccountController::class)->name('connex.account');

    Route::middleware('throttle:connex-otp')->group(function (): void {
        Route::post('activation', [SubscriptionController::class, 'requestActivation'])->name('connex.account.activation');
        Route::post('cancellation', [SubscriptionController::class, 'requestCancellation'])->name('connex.account.cancellation');
    });

    Route::middleware('throttle:connex-confirm')->group(function (): void {
        Route::post('activation/confirm', [SubscriptionController::class, 'confirmActivation'])->name('connex.account.activation.confirm');
        Route::post('cancellation/confirm', [SubscriptionController::class, 'confirmCancellation'])->name('connex.account.cancellation.confirm');
    });
});
