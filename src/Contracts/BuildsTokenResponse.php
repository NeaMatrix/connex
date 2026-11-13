<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use NeaMatrix\Connex\Auth\IssuedToken;
use NeaMatrix\Connex\Auth\LoginGrant;

/**
 * Shapes the JSON a client receives from POST /connex/api/token. Bind your own
 * to return your API's user resource alongside the token.
 */
interface BuildsTokenResponse
{
    /**
     * @return array<string, mixed>
     */
    public function build(Authenticatable $user, IssuedToken $token, LoginGrant $grant): array;
}
