<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

use Illuminate\Support\Str;
use NeaMatrix\Connex\Enums\Endpoint;

/**
 * Connex answered with something this package cannot read.
 */
class UnexpectedResponse extends ConnexException
{
    public static function undecodable(Endpoint $endpoint, int $httpStatus, string $body): self
    {
        return new self(sprintf(
            'Connex [%s] returned an unreadable HTTP %d response: %s',
            $endpoint->value,
            $httpStatus,
            Str::limit($body, 200),
        ));
    }

    public static function missingField(string $field): self
    {
        return new self("Connex response is missing the [{$field}] field.");
    }
}
