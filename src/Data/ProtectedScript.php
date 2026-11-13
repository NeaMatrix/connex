<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Data;

use NeaMatrix\Connex\Support\Payload;

/**
 * The anti-fraud (DCBProtect) script and the transaction it belongs to.
 * The script must run in the browser; the transaction id is sent back on login.
 */
final readonly class ProtectedScript
{
    public function __construct(
        public string $transactionId,
        public string $script,
    ) {}

    public static function fromPayload(Payload $payload): self
    {
        return new self(
            $payload->string('transaction_identify'),
            $payload->string('dcbprotect'),
        );
    }
}
