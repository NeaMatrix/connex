<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Notifications;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Data\Charge;
use NeaMatrix\Connex\Enums\WebhookType;
use NeaMatrix\Connex\Support\Payload;

/**
 * Connex charged the subscriber for another period.
 */
final readonly class Renewed implements WebhookNotification
{
    public function __construct(
        public string $msisdn,
        public string $transactionId,
        public float $amount,
        public ?CarbonImmutable $chargedAt,
        public ?CarbonImmutable $expiresAt,
        public ?string $conversationId,
        public ?string $serviceName,
    ) {}

    public static function type(): WebhookType
    {
        return WebhookType::Renewal;
    }

    public static function rules(): array
    {
        return [
            'msisdn' => ['required', 'string', 'max:20'],
            'transaction_id' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric'],
            'debit_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date'],
            'conversation_id' => ['nullable', 'string', 'max:255'],
            'service_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public static function fromPayload(Payload $payload): static
    {
        return new self(
            msisdn: $payload->string('msisdn'),
            transactionId: $payload->string('transaction_id'),
            amount: $payload->optionalFloat('amount') ?? 0.0,
            chargedAt: $payload->optionalDate('debit_date'),
            expiresAt: $payload->optionalDate('expiration_date'),
            conversationId: $payload->optionalString('conversation_id'),
            serviceName: $payload->optionalString('service_name'),
        );
    }

    public function msisdn(): string
    {
        return $this->msisdn;
    }

    public function charge(): Charge
    {
        return new Charge($this->transactionId, $this->amount, $this->chargedAt);
    }
}
