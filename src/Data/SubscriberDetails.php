<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Data;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Enums\SubscriptionAccess;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Support\Payload;

/**
 * Everything Connex knows about a subscriber's subscription.
 */
final readonly class SubscriberDetails
{
    public function __construct(
        public string $msisdn,
        public bool $exists,
        public ?SubscriptionStatus $status,
        public ?CarbonImmutable $expiresAt,
        public ?int $hoursRemaining = null,
        public ?string $planName = null,
        public ?DeviceType $deviceType = null,
        public ?CarbonImmutable $lastLoginAt = null,
        public ?CarbonImmutable $lastCancelledAt = null,
        public ?string $cancelledBy = null,
        public ?CarbonImmutable $lastRenewedAt = null,
        public ?string $renewedBy = null,
        public ?CarbonImmutable $lastChargedAt = null,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            msisdn: $payload->string('msisdn'),
            exists: $payload->optionalString('subscriber') !== 'new' && $payload->has('details'),
            status: $payload->optionalEnum('details.status', SubscriptionStatus::class),
            expiresAt: $payload->optionalDate('details.expiration_date'),
            hoursRemaining: $payload->optionalInt('details.hoursRemind'),
            planName: $payload->optionalString('details.subscription_name'),
            deviceType: $payload->optionalEnum('details.device_type', DeviceType::class),
            lastLoginAt: $payload->optionalDate('details.last_login'),
            lastCancelledAt: $payload->optionalDate('details.last_cancel'),
            cancelledBy: $payload->optionalString('details.canceled_by'),
            lastRenewedAt: $payload->optionalDate('details.last_renew'),
            renewedBy: $payload->optionalString('details.renew_by'),
            lastChargedAt: $payload->optionalDate('details.last_debit'),
        );
    }

    public function access(): SubscriptionAccess
    {
        return SubscriptionAccess::resolve($this->status, $this->expiresAt);
    }
}
