<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Data;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Enums\SubscriberState;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Support\Payload;

/**
 * Connex sent (or will send) an OTP that the subscriber must type to finish an action.
 *
 * When Connex needs the subscriber to choose a package first, no OTP is sent
 * and {@see $plans} lists the choices.
 */
final readonly class OtpChallenge
{
    /**
     * @param  list<SubscriptionPlan>  $plans
     */
    public function __construct(
        public string $msisdn,
        public ?string $message,
        public ?SubscriberState $subscriber = null,
        public ?SubscriptionStatus $status = null,
        public ?CarbonImmutable $expiresAt = null,
        public ?string $operator = null,
        public array $plans = [],
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            msisdn: $payload->string('msisdn'),
            message: $payload->optionalString('message'),
            subscriber: $payload->optionalEnum('subscriber', SubscriberState::class),
            status: $payload->optionalEnum('status', SubscriptionStatus::class),
            expiresAt: $payload->optionalDate('expiration_date'),
            operator: $payload->optionalString('operator'),
            plans: array_map(SubscriptionPlan::fromPayload(...), $payload->list('list')),
        );
    }

    public function requiresPlanSelection(): bool
    {
        return $this->plans !== [];
    }

    /** Two-factor login: the subscriber confirms by replying to an SMS, not by typing an OTP. */
    public function awaitsSmsConfirmation(): bool
    {
        return $this->status === SubscriptionStatus::Pending;
    }
}
