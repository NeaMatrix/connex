<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Support;

use BackedEnum;
use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Exceptions\UnexpectedResponse;
use Throwable;

/**
 * Typed, null-safe reads over a decoded Connex payload.
 *
 * Connex mixes strings and numbers for the same field across endpoints
 * (e.g. connex_transaction_id), so every getter coerces deliberately.
 */
final readonly class Payload
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        private array $data,
        private string $timezone = 'Africa/Tripoli',
    ) {}

    public function has(string $key): bool
    {
        return data_get($this->data, $key) !== null;
    }

    public function string(string $key): string
    {
        return $this->optionalString($key) ?? throw UnexpectedResponse::missingField($key);
    }

    public function optionalString(string $key): ?string
    {
        $value = data_get($this->data, $key);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    public function optionalInt(string $key): ?int
    {
        $value = data_get($this->data, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    public function optionalFloat(string $key): ?float
    {
        $value = data_get($this->data, $key);

        return is_numeric($value) ? (float) $value : null;
    }

    public function optionalDate(string $key): ?CarbonImmutable
    {
        $value = $this->optionalString($key);

        if ($value === null) {
            return null;
        }

        try {
            // Connex sends local wall-clock times; convert them to the app's timezone
            // so Eloquent stores the right instant.
            return CarbonImmutable::parse($value, $this->timezone)->setTimezone(date_default_timezone_get());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|null
     */
    public function optionalEnum(string $key, string $enum): ?BackedEnum
    {
        $value = $this->optionalString($key);

        return $value === null ? null : $enum::tryFrom($value);
    }

    /**
     * @return list<self>
     */
    public function list(string $key): array
    {
        $items = data_get($this->data, $key);

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $item): self => new self(is_array($item) ? $item : [], $this->timezone),
            $items,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
