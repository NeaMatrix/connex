<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Data;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Support\Payload;

/**
 * Money taken from the subscriber's balance.
 */
final readonly class Charge
{
    public function __construct(
        public string $transactionId,
        public float $amount,
        public ?CarbonImmutable $chargedAt,
        public ?string $invoiceNo = null,
        public ?string $status = null,
        public ?int $productId = null,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            transactionId: $payload->string('connex_transaction_id'),
            amount: $payload->optionalFloat('amount') ?? 0.0,
            chargedAt: $payload->optionalDate('transactions_date'),
            invoiceNo: $payload->optionalString('invoice_no'),
            status: $payload->optionalString('status'),
            productId: $payload->optionalInt('product_id'),
        );
    }
}
