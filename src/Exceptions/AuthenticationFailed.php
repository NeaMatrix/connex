<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

/**
 * The service's own Connex credentials were rejected. This is a configuration
 * problem, never the subscriber's fault.
 */
class AuthenticationFailed extends ConnexException
{
    public static function missingCredentials(): self
    {
        return new self('Connex credentials are not configured. Set CONNEX_EMAIL and CONNEX_PASSWORD.');
    }
}
