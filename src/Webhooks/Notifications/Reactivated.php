<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Notifications;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Enums\WebhookType;
use NeaMatrix\Connex\Support\Payload;

/**
 * A cancelled subscription was turned back on outside the website.
 */
final readonly class Reactivated implements WebhookNotification
{
    public function __construct(
        public string $msisdn,
        public ?CarbonImmutable $reactivatedAt,
        public ?string $reactivatedBy,
        public ?string $serviceName,
        public ?string $trackingId,
    ) {}

    public static function type(): WebhookType
    {
        return WebhookType::Reactivate;
    }

    public static function rules(): array
    {
        return [
            'msisdn' => ['required', 'string', 'max:20'],
            'reactivate_date' => ['nullable', 'date'],
            'reactivate_by' => ['nullable', 'string', 'max:50'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'tracking_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function fromPayload(Payload $payload): static
    {
        return new self(
            msisdn: $payload->string('msisdn'),
            reactivatedAt: $payload->optionalDate('reactivate_date'),
            reactivatedBy: $payload->optionalString('reactivate_by'),
            serviceName: $payload->optionalString('service_name'),
            trackingId: $payload->optionalString('tracking_id'),
        );
    }

    public function msisdn(): string
    {
        return $this->msisdn;
    }
}
