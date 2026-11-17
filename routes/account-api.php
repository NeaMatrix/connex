<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NeaMatrix\Connex\Account\Controllers\AccountTicketController;
use NeaMatrix\Connex\Account\Controllers\PlanController;
use NeaMatrix\Connex\Account\Controllers\SubscriptionController;

Route::prefix('api')->group(function (): void {
    Route::get('subscription', [SubscriptionController::class, 'show'])->name('connex.api.subscription');
    Route::get('subscription/plans', [PlanController::class, 'index'])->name('connex.api.plans');
    Route::get('subscription/transactions', [PlanController::class, 'transactions'])->name('connex.api.transactions');

    Route::middleware('throttle:connex-account-otp')->group(function (): void {
        Route::post('subscription/activation', [SubscriptionController::class, 'requestActivation'])->name('connex.api.activation');
        Route::post('subscription/cancellation', [SubscriptionController::class, 'requestCancellation'])->name('connex.api.cancellation');
        Route::post('subscription/plan-change', [PlanController::class, 'request'])->name('connex.api.plan-change');
    });

    Route::middleware('throttle:connex-account-confirm')->group(function (): void {
        Route::post('subscription/activation/confirm', [SubscriptionController::class, 'confirmActivation'])->name('connex.api.activation.confirm');
        Route::post('subscription/cancellation/confirm', [SubscriptionController::class, 'confirmCancellation'])->name('connex.api.cancellation.confirm');
        Route::post('subscription/plan-change/confirm', [PlanController::class, 'confirm'])->name('connex.api.plan-change.confirm');
    });

    Route::post('account-link', [AccountTicketController::class, 'issue'])->name('connex.api.account-link');
});
