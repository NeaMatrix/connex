<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use NeaMatrix\Connex\Data\LoginConfirmation;
use NeaMatrix\Connex\Support\Msisdn;

/**
 * Finds or creates the app's user for a subscriber who just confirmed their OTP.
 *
 * Bind your own implementation to change how accounts are created:
 * $this->app->bind(ResolvesUsers::class, MyUserResolver::class);
 */
interface ResolvesUsers
{
    public function resolve(Msisdn $msisdn, LoginConfirmation $confirmation): Model&Authenticatable;
}
