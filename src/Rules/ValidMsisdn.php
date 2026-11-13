<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use NeaMatrix\Connex\Support\MsisdnNormalizer;

/**
 * Accepts any common way of writing a mobile number for the configured country.
 */
class ValidMsisdn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(MsisdnNormalizer::class)->isValid($value)) {
            $fail('connex::errors.invalid_msisdn')->translate();
        }
    }
}
