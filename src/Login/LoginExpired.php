<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login;

use RuntimeException;

/**
 * The login step was called out of order or the session ran out; the
 * subscriber must reload the page and start again.
 */
class LoginExpired extends RuntimeException
{
    public static function missing(string $what): self
    {
        return new self("The login session has no {$what}. Start the login again.");
    }
}
