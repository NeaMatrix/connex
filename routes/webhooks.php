<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NeaMatrix\Connex\Webhooks\Http\VerifyWebhookSource;
use NeaMatrix\Connex\Webhooks\Http\WebhookController;

Route::post('{type}', WebhookController::class)
    ->middleware(VerifyWebhookSource::class)
    ->whereIn('type', ['subscribe', 'unsubscribe', 'reactivate', 'renewal'])
    ->name('connex.webhooks');
