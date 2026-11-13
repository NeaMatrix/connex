<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Notifications;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Enums\WebhookType;
use NeaMatrix\Connex\Support\Payload;

/**
 * The subscription was cancelled outside the website (SMS, operator, Connex staff).
 */
final readonly class Unsubscribed implements WebhookNotification
{
    public function __construct(
        public string $msisdn,
        public ?CarbonImmutable $cancelledAt,
        public ?string $cancelledBy,
        public ?string $serviceName,
        public ?string $trackingId,
    ) {}

    public static function type(): WebhookType
    {
        return WebhookType::Unsubscribe;
    }

    public static function rules(): array
    {
        return [
            'msisdn' => ['required', 'string', 'max:20'],
            'cancel_date' => ['nullable', 'date'],
            'cancel_by' => ['nullable', 'string', 'max:50'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'tracking_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function fromPayload(Payload $payload): static
    {
        return new self(
            msisdn: $payload->string('msisdn'),
            cancelledAt: $payload->optionalDate('cancel_date'),
            cancelledBy: $payload->optionalString('cancel_by'),
            serviceName: $payload->optionalString('service_name'),
            trackingId: $payload->optionalString('tracking_id'),
        );
    }

    public function msisdn(): string
    {
        return $this->msisdn;
    }
}
