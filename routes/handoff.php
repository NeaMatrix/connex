<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NeaMatrix\Connex\Login\Controllers\HandoffController;

Route::post('api/handoff', HandoffController::class)
    ->middleware('throttle:connex-confirm')
    ->name('connex.handoff');
