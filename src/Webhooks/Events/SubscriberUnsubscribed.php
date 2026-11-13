<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Webhooks\Notifications\Unsubscribed;

/**
 * The subscription was cancelled outside the website.
 *
 * $subscription is null when config("connex.subscriptions.store") is false.
 */
final readonly class SubscriberUnsubscribed
{
    use Dispatchable;

    public function __construct(
        public Unsubscribed $notification,
        public ?ConnexSubscription $subscription,
    ) {}
}
