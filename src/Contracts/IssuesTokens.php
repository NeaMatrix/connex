<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use NeaMatrix\Connex\Auth\IssuedToken;
use NeaMatrix\Connex\Enums\DeviceType;

/**
 * Creates the API token the mobile app receives when it redeems a handoff code.
 */
interface IssuesTokens
{
    public function issue(Authenticatable $user, DeviceType $device): IssuedToken;
}
