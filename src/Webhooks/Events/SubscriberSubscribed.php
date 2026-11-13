<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Webhooks\Notifications\Subscribed;

/**
 * The subscriber joined outside the website, usually by SMS.
 *
 * $subscription is null when config("connex.subscriptions.store") is false.
 */
final readonly class SubscriberSubscribed
{
    use Dispatchable;

    public function __construct(
        public Subscribed $notification,
        public ?ConnexSubscription $subscription,
    ) {}
}
