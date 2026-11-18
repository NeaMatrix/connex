<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Client;

use NeaMatrix\Connex\Enums\Endpoint;
use NeaMatrix\Connex\Enums\MessageCode;
use NeaMatrix\Connex\Support\Payload;

/**
 * One decoded Connex response.
 *
 * Connex puts the useful data under "success", "failed", "data" or "errors"
 * depending on the endpoint and outcome; {@see $payload} is whichever one was sent.
 */
final readonly class ConnexResponse
{
    public function __construct(
        public Endpoint $endpoint,
        public int $httpStatus,
        public ?MessageCode $code,
        public string $rawCode,
        public Payload $payload,
        public string $body,
        public ?string $wrapper = null,
    ) {}

    public function isSuccessful(): bool
    {
        // Connex also answers a login with "01" inside "success" when it sends the
        // OTP (seen on Ebtekar's own Tawajah landing page, which treats it as sent).
        if ($this->endpoint === Endpoint::Login && $this->code === MessageCode::Failed && $this->wrapper === 'success') {
            return true;
        }

        return $this->httpStatus < 400 && $this->code?->isSuccessful() === true;
    }

    public function message(): ?string
    {
        return $this->payload->optionalString('message')
            ?? $this->payload->optionalString('password.0');
    }
}
