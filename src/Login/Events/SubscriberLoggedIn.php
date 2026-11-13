<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use NeaMatrix\Connex\Data\LoginConfirmation;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Models\ConnexSubscription;

/**
 * A subscriber confirmed their OTP and was matched to a user.
 * Use $confirmation->isNewSubscriber() to show onboarding.
 */
final readonly class SubscriberLoggedIn
{
    use Dispatchable;

    public function __construct(
        public Authenticatable $user,
        public LoginConfirmation $confirmation,
        public DeviceType $device,
        public ?ConnexSubscription $subscription,
    ) {}
}
