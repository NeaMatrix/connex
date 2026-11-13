<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NeaMatrix\Connex\Login\Controllers\TokenController;

Route::post('api/token', TokenController::class)
    ->middleware('throttle:connex-token')
    ->name('connex.token');
