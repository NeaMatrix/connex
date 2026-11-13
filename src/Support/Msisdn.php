<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Support;

use Stringable;

/**
 * A subscriber's mobile number in international form without "+", e.g. 218920920110.
 *
 * Build one with {@see MsisdnNormalizer} so every number is validated the same way.
 */
final readonly class Msisdn implements Stringable
{
    public function __construct(
        public string $international,
        public string $countryCode,
    ) {}

    /** The national form with a trunk "0", e.g. 0920920110. */
    public function national(): string
    {
        return '0'.substr($this->international, strlen($this->countryCode));
    }

    /** A display-safe form, e.g. 21892*****10. */
    public function masked(): string
    {
        return substr($this->international, 0, 5)
            .str_repeat('*', max(strlen($this->international) - 7, 0))
            .substr($this->international, -2);
    }

    public function equals(self $other): bool
    {
        return $this->international === $other->international;
    }

    public function __toString(): string
    {
        return $this->international;
    }
}
