<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Enums;

/**
 * Subscription status as reported by Connex.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancel';

    /** Waiting for the subscriber to confirm by SMS (two-factor login). */
    case Pending = 'new';
}
