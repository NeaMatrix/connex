<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Enums;

use Carbon\CarbonInterface;

/**
 * What a subscriber may do right now, from the Connex status and the expiry date:
 *
 * - active:   Connex active, expiry in the future → access, no notice
 * - inactive: Connex cancelled, expiry in the future → access, notice + reactivate
 * - canceled: Connex cancelled, expiry in the past → blocked, notice + reactivate
 * - expired:  Connex active, expiry in the past (renewal charge failed) → blocked, top up the balance
 * - none:     never subscribed or awaiting SMS confirmation → blocked, subscribe
 */
enum SubscriptionAccess: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Canceled = 'canceled';
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
            SubscriptionStatus::Active => $isExpired ? self::Expired : self::Active,
            SubscriptionStatus::Cancelled => $isExpired ? self::Canceled : self::Inactive,
            SubscriptionStatus::Pending, null => self::None,
        };
    }

    public function allowsAccess(): bool
    {
        return $this === self::Active || $this === self::Inactive;
    }

    /** Whether the subscriber should be offered to (re-)activate. */
    public function shouldPromptActivation(): bool
    {
        return in_array($this->action(), ['reactivate', 'subscribe'], true);
    }

    /**
     * The button shown with the notice: "reactivate", "subscribe", "top_up"
     * (no Connex call; the subscriber recharges the phone) or null.
     */
    public function action(): ?string
    {
        return match ($this) {
            self::Active => null,
            self::Inactive, self::Canceled => 'reactivate',
            self::Expired => 'top_up',
            self::None => 'subscribe',
        };
    }

    /** The notice to show the subscriber, or null when there is nothing to say. */
    public function message(): ?string
    {
        if ($this === self::Active) {
            return null;
        }

        $message = __("connex::access.{$this->value}");

        return is_string($message) ? $message : null;
    }
}
