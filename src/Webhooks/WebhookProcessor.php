<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use NeaMatrix\Connex\Enums\ChargeType;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Subscriptions\SubscriptionStore;
use NeaMatrix\Connex\Webhooks\Events\SubscriberReactivated;
use NeaMatrix\Connex\Webhooks\Events\SubscriberSubscribed;
use NeaMatrix\Connex\Webhooks\Events\SubscriberUnsubscribed;
use NeaMatrix\Connex\Webhooks\Events\SubscriptionRenewed;
use NeaMatrix\Connex\Webhooks\Notifications\Reactivated;
use NeaMatrix\Connex\Webhooks\Notifications\Renewed;
use NeaMatrix\Connex\Webhooks\Notifications\Subscribed;
use NeaMatrix\Connex\Webhooks\Notifications\Unsubscribed;
use NeaMatrix\Connex\Webhooks\Notifications\WebhookNotification;

/**
 * Applies a Connex notification to the local mirror and dispatches its event.
 */
class WebhookProcessor
{
    public function __construct(
        private readonly SubscriptionStore $store,
        private readonly Dispatcher $events,
        private readonly ConnectionInterface $database,
        private readonly bool $storesSubscriptions = true,
    ) {}

    public function process(WebhookNotification $notification): void
    {
        $event = $this->database->transaction(fn (): ?object => match (true) {
            $notification instanceof Subscribed => new SubscriberSubscribed($notification, $this->subscribed($notification)),
            $notification instanceof Unsubscribed => new SubscriberUnsubscribed($notification, $this->unsubscribed($notification)),
            $notification instanceof Reactivated => new SubscriberReactivated($notification, $this->reactivated($notification)),
            $notification instanceof Renewed => $this->renewed($notification),
            default => null,
        });

        if ($event !== null) {
            $this->events->dispatch($event);
        }
    }

    private function subscribed(Subscribed $notification): ?ConnexSubscription
    {
        return $this->store($notification, [
            'status' => SubscriptionStatus::Active,
            'subscribed_at' => $notification->subscribedAt,
            'expires_at' => $notification->expiresAt,
            'plan_name' => $notification->planName,
        ]);
    }

    private function unsubscribed(Unsubscribed $notification): ?ConnexSubscription
    {
        return $this->store($notification, [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => $notification->cancelledAt,
            'cancelled_by' => $notification->cancelledBy,
        ]);
    }

    private function reactivated(Reactivated $notification): ?ConnexSubscription
    {
        return $this->store($notification, [
            'status' => SubscriptionStatus::Active,
            'reactivated_at' => $notification->reactivatedAt,
        ]);
    }

    /**
     * Renewals carry a transaction id; a repeated one is acknowledged but not re-dispatched.
     */
    private function renewed(Renewed $notification): ?SubscriptionRenewed
    {
        if (! $this->storesSubscriptions) {
            return new SubscriptionRenewed($notification, null);
        }

        $subscription = $this->store->record($notification->msisdn, [
            'status' => SubscriptionStatus::Active,
            'expires_at' => $notification->expiresAt,
        ]);

        $isNew = $this->store->recordCharge($subscription, $notification->charge(), ChargeType::Renewal);

        return $isNew ? new SubscriptionRenewed($notification, $subscription) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(WebhookNotification $notification, array $attributes): ?ConnexSubscription
    {
        return $this->storesSubscriptions
            ? $this->store->record($notification->msisdn(), $attributes)
            : null;
    }
}
