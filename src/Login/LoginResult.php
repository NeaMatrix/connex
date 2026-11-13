<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use NeaMatrix\Connex\Data\LoginConfirmation;
use NeaMatrix\Connex\Models\ConnexSubscription;

/**
 * A finished login: the app user, what Connex confirmed, and the stored subscription.
 */
final readonly class LoginResult
{
    public function __construct(
        public Model&Authenticatable $user,
        public LoginConfirmation $confirmation,
        public ?ConnexSubscription $subscription,
    ) {}
}
