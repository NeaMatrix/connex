<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use NeaMatrix\Connex\Enums\Endpoint;

/**
 * Connex did not answer (DNS, TLS, timeout...).
 */
class ConnectionFailed extends ConnexException
{
    public static function during(Endpoint $endpoint, ConnectionException $previous): self
    {
        return new self("Could not reach Connex [{$endpoint->value}]: {$previous->getMessage()}", null, $previous);
    }
}
