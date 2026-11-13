<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Enums;

use Carbon\CarbonInterface;

/**
 * What a subscriber may do right now, following the Connex content rules:
 *
 * - active and not expired:    full access
 * - active but expired:        blocked, the last charge failed ("insufficient balance")
 * - cancelled and not expired: access until the expiry date, prompt to re-activate
 * - cancelled and expired:     blocked, prompt to renew
 */
enum SubscriptionAccess: string
{
    case Granted = 'granted';
    case InsufficientBalance = 'insufficient_balance';
    case GracePeriod = 'grace_period';
    case Expired = 'expired';
    case None = 'none';

    public static function resolve(
        ?SubscriptionStatus $status,
        ?CarbonInterface $expiresAt,
        ?CarbonInterface $now = null,
    ): self {
        $now ??= now();
        $isExpired = $expiresAt === null || $expiresAt->lessThanOrEqualTo($now);

        return match ($status) {
            SubscriptionStatus::Active => $isExpired ? self::InsufficientBalance : self::Granted,
            SubscriptionStatus::Cancelled => $isExpired ? self::Expired : self::GracePeriod,
            SubscriptionStatus::Pending, null => self::None,
        };
    }

    public function allowsAccess(): bool
    {
        return $this === self::Granted || $this === self::GracePeriod;
    }

    /** Whether the subscriber should be offered to (re-)activate. */
    public function shouldPromptActivation(): bool
    {
        return $this !== self::Granted;
    }
}
