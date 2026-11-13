<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Notifications;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Enums\WebhookType;
use NeaMatrix\Connex\Support\Payload;

/**
 * The subscriber joined outside the website, usually by SMS.
 */
final readonly class Subscribed implements WebhookNotification
{
    public function __construct(
        public string $msisdn,
        public ?CarbonImmutable $subscribedAt,
        public ?CarbonImmutable $expiresAt,
        public ?string $channel,
        public ?string $serviceName,
        public ?string $planName,
    ) {}

    public static function type(): WebhookType
    {
        return WebhookType::Subscribe;
    }

    public static function rules(): array
    {
        return [
            'msisdn' => ['required', 'string', 'max:20'],
            'created_at' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'subscription' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function fromPayload(Payload $payload): static
    {
        return new self(
            msisdn: $payload->string('msisdn'),
            subscribedAt: $payload->optionalDate('created_at'),
            expiresAt: $payload->optionalDate('expiration_date'),
            channel: $payload->optionalString('device_type'),
            serviceName: $payload->optionalString('service_name'),
            planName: $payload->optionalString('subscription'),
        );
    }

    public function msisdn(): string
    {
        return $this->msisdn;
    }
}
