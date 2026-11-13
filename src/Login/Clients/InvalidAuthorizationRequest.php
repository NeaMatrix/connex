<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Clients;

use RuntimeException;

/**
 * The login link is wrong (unknown client, unlisted redirect URI, missing
 * PKCE...). The page shows an error and never redirects to an unverified URI.
 */
class InvalidAuthorizationRequest extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
