<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Data;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Support\Payload;

/**
 * The subscription state after an activation or cancellation.
 */
final readonly class SubscriptionChange
{
    public function __construct(
        public string $msisdn,
        public SubscriptionStatus $status,
        public ?CarbonImmutable $expiresAt,
    ) {}

    public static function fromPayload(Payload $payload, SubscriptionStatus $expected): self
    {
        return new self(
            $payload->string('msisdn'),
            $payload->optionalEnum('status', SubscriptionStatus::class) ?? $expected,
            $payload->optionalDate('expiration_date'),
        );
    }
}
