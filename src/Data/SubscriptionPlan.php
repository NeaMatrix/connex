<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Data;

use NeaMatrix\Connex\Support\Payload;

/**
 * A subscription package offered to the subscriber.
 */
final readonly class SubscriptionPlan
{
    public function __construct(
        public string $uuid,
        public string $name,
        public int $periodHours,
        public int $freePeriodHours,
        public ?int $productId,
        public ?float $cost,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            $payload->string('uuid'),
            $payload->string('name'),
            $payload->optionalInt('period') ?? 0,
            $payload->optionalInt('free_period') ?? 0,
            $payload->optionalInt('product_id') ?? $payload->optionalInt('product.id'),
            $payload->optionalFloat('product.cost'),
        );
    }
}
