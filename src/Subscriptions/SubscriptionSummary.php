<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Subscriptions;

use NeaMatrix\Connex\Models\ConnexSubscription;

/**
 * The JSON shape of a subscription in API responses.
 */
final class SubscriptionSummary
{
    /**
     * @return array{msisdn: string, status: string|null, plan: string|null, expires_at: string|null, access: string, allows_access: bool}|null
     */
    public static function of(?ConnexSubscription $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }

        $access = $subscription->access();

        return [
            'msisdn' => $subscription->msisdn,
            'status' => $subscription->status?->value,
            'plan' => $subscription->plan_name,
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'access' => $access->value,
            'allows_access' => $access->allowsAccess(),
        ];
    }
}
