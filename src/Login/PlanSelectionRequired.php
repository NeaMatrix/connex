<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login;

use NeaMatrix\Connex\Data\OtpChallenge;
use NeaMatrix\Connex\Data\SubscriptionPlan;
use RuntimeException;

/**
 * Connex answered the login with a list of packages instead of sending an OTP
 * (messageCode 02). The service has more than one plan configured on Connex.
 */
class PlanSelectionRequired extends RuntimeException
{
    /**
     * @param  list<SubscriptionPlan>  $plans
     */
    public function __construct(public readonly array $plans)
    {
        parent::__construct('Connex requires the subscriber to choose a subscription plan.');
    }

    public static function from(OtpChallenge $challenge): self
    {
        return new self($challenge->plans);
    }
}
