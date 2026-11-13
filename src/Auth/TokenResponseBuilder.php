<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use NeaMatrix\Connex\Contracts\BuildsTokenResponse;
use NeaMatrix\Connex\Subscriptions\SubscriptionStore;
use NeaMatrix\Connex\Subscriptions\SubscriptionSummary;

/**
 * {"token", "token_type", "expires_at", "is_new_subscriber", "user": {"id"}, "subscription": {...}}
 */
class TokenResponseBuilder implements BuildsTokenResponse
{
    public function __construct(
        private readonly SubscriptionStore $subscriptions,
    ) {}

    public function build(Authenticatable $user, IssuedToken $token, LoginGrant $grant): array
    {
        $subscription = $user instanceof Model ? $this->subscriptions->forOwner($user) : null;

        return $token->toArray() + [
            'is_new_subscriber' => $grant->isNewSubscriber,
            'user' => ['id' => $user->getAuthIdentifier()],
            'subscription' => SubscriptionSummary::of($subscription),
        ];
    }
}
