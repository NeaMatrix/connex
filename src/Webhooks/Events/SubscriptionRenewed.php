<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Webhooks\Notifications\Renewed;

/**
 * Connex charged the subscriber for another period. Fired once per transaction.
 *
 * $subscription is null when config("connex.subscriptions.store") is false.
 */
final readonly class SubscriptionRenewed
{
    use Dispatchable;

    public function __construct(
        public Renewed $notification,
        public ?ConnexSubscription $subscription,
    ) {}
}
