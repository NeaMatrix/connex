<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use NeaMatrix\Connex\Auth\IssuedToken;
use NeaMatrix\Connex\Auth\SanctumTokenIssuer;
use NeaMatrix\Connex\Enums\DeviceType;

/**
 * Creates the API token a client receives when it exchanges a login code
 * at POST /connex/api/token. The default is {@see SanctumTokenIssuer}.
 */
interface IssuesTokens
{
    public function issue(Authenticatable $user, DeviceType $device): IssuedToken;
}
