<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NeaMatrix\Connex\Account\Controllers\AccountTicketController;

Route::get('account/enter', [AccountTicketController::class, 'enter'])
    ->middleware('throttle:connex-token')
    ->name('connex.account.enter');
