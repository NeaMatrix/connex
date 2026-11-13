<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

use InvalidArgumentException;

/**
 * The phone number is not a valid mobile number for the configured country.
 */
class InvalidMsisdn extends InvalidArgumentException
{
    public static function for(string $input): self
    {
        return new self("[{$input}] is not a valid mobile number.");
    }
}
