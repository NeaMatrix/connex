<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Subscriptions;

use Illuminate\Database\Eloquent\Model;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Data\OtpChallenge;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Exceptions\ConnexException;
use NeaMatrix\Connex\Models\ConnexSubscription;

/**
 * Activates, cancels and refreshes the subscription that belongs to a user,
 * keeping the local mirror up to date after every change.
 */
class SubscriptionManager
{
    public function __construct(
        private readonly ConnexClient $connex,
        private readonly SubscriptionStore $store,
    ) {}

    /**
     * Pull the latest state from Connex. Returns null when the user has no subscription.
     *
     * @throws ConnexException
     */
    public function refresh(Model $owner): ?ConnexSubscription
    {
        $subscription = $this->store->forOwner($owner);

        return $subscription === null
            ? null
            : $this->store->recordDetails($this->connex->subscriberDetails($subscription->msisdn));
    }

    /**
     * @throws ConnexException|NotSubscribed
     */
    public function requestActivation(Model $owner): OtpChallenge
    {
        return $this->connex->requestActivation($this->subscriptionOf($owner)->msisdn);
    }

    /**
     * @throws ConnexException|NotSubscribed
     */
    public function confirmActivation(Model $owner, string $otp, DeviceType $device = DeviceType::Web): ConnexSubscription
    {
        $change = $this->connex->confirmActivation($this->subscriptionOf($owner)->msisdn, $otp, $device);

        return $this->store->recordChange($change);
    }

    /**
     * @throws ConnexException|NotSubscribed
     */
    public function requestCancellation(Model $owner): OtpChallenge
    {
        return $this->connex->requestCancellation($this->subscriptionOf($owner)->msisdn);
    }

    /**
     * @throws ConnexException|NotSubscribed
     */
    public function confirmCancellation(Model $owner, string $otp, DeviceType $device = DeviceType::Web): ConnexSubscription
    {
        $change = $this->connex->confirmCancellation($this->subscriptionOf($owner)->msisdn, $otp, $device);

        return $this->store->recordChange($change);
    }

    private function subscriptionOf(Model $owner): ConnexSubscription
    {
        return $this->store->forOwner($owner) ?? throw new NotSubscribed;
    }
}
