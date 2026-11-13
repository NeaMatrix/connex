<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Webhooks\Notifications\Reactivated;

/**
 * A cancelled subscription was turned back on outside the website.
 *
 * $subscription is null when config("connex.subscriptions.store") is false.
 */
final readonly class SubscriberReactivated
{
    use Dispatchable;

    public function __construct(
        public Reactivated $notification,
        public ?ConnexSubscription $subscription,
    ) {}
}
