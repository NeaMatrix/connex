<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Contracts\ResolvesUsers;
use NeaMatrix\Connex\Data\OtpChallenge;
use NeaMatrix\Connex\Data\ProtectedScript;
use NeaMatrix\Connex\Exceptions\ConnexException;
use NeaMatrix\Connex\Login\Events\SubscriberLoggedIn;
use NeaMatrix\Connex\Subscriptions\SubscriptionStore;
use NeaMatrix\Connex\Support\MsisdnNormalizer;

/**
 * The three steps of a Connex login: load the anti-fraud script, send the OTP,
 * confirm the OTP. Each step reads what it needs from {@see LoginSession}.
 */
class LoginFlow
{
    public function __construct(
        private readonly ConnexClient $connex,
        private readonly MsisdnNormalizer $msisdns,
        private readonly SubscriptionStore $subscriptions,
        private readonly ResolvesUsers $users,
        private readonly Dispatcher $events,
        private readonly bool $storesSubscriptions = true,
    ) {}

    /**
     * @throws ConnexException
     */
    public function loadScript(LoginSession $session, string $targetElement): ProtectedScript
    {
        $script = $this->connex->protectedScript($targetElement);
        $session->rememberTransaction($script->transactionId);

        return $script;
    }

    /**
     * @throws ConnexException|LoginExpired|PlanSelectionRequired
     */
    public function requestOtp(LoginSession $session, string $phone): OtpChallenge
    {
        $transactionId = $session->transactionId() ?? throw LoginExpired::missing('anti-fraud transaction');
        $msisdn = $this->msisdns->normalize($phone);

        $challenge = $this->connex->login($msisdn, $transactionId, $session->device());

        if ($challenge->requiresPlanSelection()) {
            throw PlanSelectionRequired::from($challenge);
        }

        $session->rememberOtpRequest($msisdn->international);

        return $challenge;
    }

    /**
     * @throws ConnexException|LoginExpired
     */
    public function confirmOtp(LoginSession $session, string $otp): Authenticatable
    {
        $msisdn = $this->msisdns->normalize($session->msisdn() ?? throw LoginExpired::missing('phone number'));

        $confirmation = $this->connex->confirmLogin(
            $msisdn,
            $otp,
            $session->device(),
            $session->campaignId(),
            $session->trackingId(),
        );

        $user = $this->users->resolve($msisdn, $confirmation);
        $subscription = null;

        if ($this->storesSubscriptions) {
            $subscription = $this->subscriptions->attachOwner($this->subscriptions->recordLogin($confirmation), $user);
        }

        $this->events->dispatch(new SubscriberLoggedIn($user, $confirmation, $session->device(), $subscription));

        return $user;
    }
}
