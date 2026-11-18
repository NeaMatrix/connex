<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Debug;

use NeaMatrix\Connex\Enums\Endpoint;

/**
 * Collects the raw Connex exchanges made while handling one request, for the
 * debug panel (config "connex.debug.enabled"). Secrets never reach it: OTPs,
 * passwords and tokens are masked, and the anti-fraud script is summarised.
 */
class DebugRecorder
{
    private const array SECRET_FIELDS = ['otp', 'password', 'otp_signature', 'access_token'];

    /** @var list<array{endpoint: string, method: string, url: string, request: array<string, mixed>, status: int|null, response: mixed, error: string|null, duration_ms: int|null, at: string}> */
    private array $exchanges = [];

    public function __construct(
        private readonly bool $enabled = false,
    ) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    public function record(
        Endpoint $endpoint,
        array $request,
        ?int $status,
        ?string $body,
        ?string $error = null,
        string $url = '',
        ?float $startedAt = null,
    ): void {
        if (! $this->enabled) {
            return;
        }

        $this->exchanges[] = [
            'endpoint' => $endpoint->value,
            'method' => $endpoint->method(),
            'url' => $url,
            'request' => $this->mask($request),
            'status' => $status,
            'response' => $body === null ? null : $this->readable($body),
            'error' => $error,
            'duration_ms' => $startedAt === null ? null : (int) round((microtime(true) - $startedAt) * 1000),
            'at' => now()->format('H:i:s.v'),
        ];
    }

    /**
     * @return list<array{endpoint: string, method: string, url: string, request: array<string, mixed>, status: int|null, response: mixed, error: string|null, duration_ms: int|null, at: string}>
     */
    public function all(): array
    {
        return $this->exchanges;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::SECRET_FIELDS, true) && is_scalar($value)) {
                $data[$key] = '•••• (hidden)';
            } elseif ($key === 'dcbprotect' && is_string($value)) {
                $data[$key] = '<anti-fraud script, '.strlen($value).' bytes>';
            } elseif (is_array($value)) {
                $data[$key] = $this->mask($value);
            }
        }

        return $data;
    }

    private function readable(string $body): mixed
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $this->mask($decoded) : mb_substr($body, 0, 2000);
    }
}
