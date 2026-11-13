<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

use NeaMatrix\Connex\Client\ConnexResponse;
use RuntimeException;
use Throwable;

/**
 * Base class for every failure talking to Connex. Catch this to handle them all.
 */
class ConnexException extends RuntimeException
{
    final public function __construct(
        string $message,
        public readonly ?ConnexResponse $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $response->httpStatus ?? 0, $previous);
    }

    public static function fromResponse(ConnexResponse $response, ?string $fallback = null): static
    {
        return new static(
            $response->message() ?? $fallback ?? "Connex rejected [{$response->endpoint->value}] with code [{$response->rawCode}].",
            $response,
        );
    }
}
