<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Data;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Enums\SubscriberState;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Support\Payload;

/**
 * The subscriber proved they own the number. Connex may also have charged an
 * initial bill, in which case {@see $charge} is set.
 */
final readonly class LoginConfirmation
{
    public function __construct(
        public string $msisdn,
        public SubscriberState $subscriber,
        public ?SubscriptionStatus $status,
        public ?CarbonImmutable $expiresAt,
        public ?string $operator,
        public ?Charge $charge = null,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        $charge = $payload->has('connex_transaction_id') ? Charge::fromPayload($payload) : null;

        return new self(
            msisdn: $payload->string('msisdn'),
            subscriber: $payload->optionalEnum('subscriber', SubscriberState::class)
                ?? ($charge ? SubscriberState::Created : SubscriberState::Existing),
            status: $payload->optionalEnum('status', SubscriptionStatus::class)
                ?? ($charge ? SubscriptionStatus::Active : null),
            expiresAt: $payload->optionalDate('expiration_date'),
            operator: $payload->optionalString('operator'),
            charge: $charge,
        );
    }

    public function isNewSubscriber(): bool
    {
        return $this->subscriber->isNew();
    }
}
