<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Support;

use NeaMatrix\Connex\Exceptions\InvalidMsisdn;

/**
 * Turns whatever the subscriber typed (0920920110, 920920110, +218 92 092 0110,
 * 00218920920110) into the international form Connex expects.
 */
final readonly class MsisdnNormalizer
{
    /**
     * @param  list<string>  $mobilePrefixes  national prefixes accepted after the trunk "0"
     */
    public function __construct(
        private string $countryCode = '218',
        private int $nationalLength = 9,
        private array $mobilePrefixes = ['91', '92', '93', '94', '95'],
    ) {}

    /**
     * @param  array{country_code?: string, national_length?: int, mobile_prefixes?: list<string>}  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            $config['country_code'] ?? '218',
            $config['national_length'] ?? 9,
            $config['mobile_prefixes'] ?? ['91', '92', '93', '94', '95'],
        );
    }

    /**
     * @throws InvalidMsisdn
     */
    public function normalize(Msisdn|string $input): Msisdn
    {
        if ($input instanceof Msisdn) {
            return $input;
        }

        $national = $this->nationalNumber($input);

        if (! $this->isValidNational($national)) {
            throw InvalidMsisdn::for($input);
        }

        return new Msisdn($this->countryCode.$national, $this->countryCode);
    }

    public function isValid(string $input): bool
    {
        return $this->isValidNational($this->nationalNumber($input));
    }

    private function nationalNumber(string $input): string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        foreach (['00'.$this->countryCode, $this->countryCode, '0'] as $prefix) {
            $candidate = substr($digits, strlen($prefix));

            if (str_starts_with($digits, $prefix) && strlen($candidate) === $this->nationalLength) {
                return $candidate;
            }
        }

        return $digits;
    }

    private function isValidNational(string $national): bool
    {
        if (strlen($national) !== $this->nationalLength || ! ctype_digit($national)) {
            return false;
        }

        if ($this->mobilePrefixes === []) {
            return true;
        }

        foreach ($this->mobilePrefixes as $prefix) {
            if (str_starts_with($national, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
