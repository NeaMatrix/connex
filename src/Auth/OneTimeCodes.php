<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * Short-lived, single-use random codes backed by the cache. Only a hash of
 * each code is stored. Redemption is claimed with an atomic Cache::add, so two
 * concurrent requests can never both redeem the same code.
 */
class OneTimeCodes
{
    public function __construct(
        private readonly Cache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function issue(string $purpose, array $payload, int $ttlSeconds): string
    {
        $code = Str::random(64);
        $this->cache->put($this->key($purpose, $code), $payload, $ttlSeconds);

        return $code;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function redeem(string $purpose, string $code): ?array
    {
        $key = $this->key($purpose, $code);

        if (! $this->cache->add($key.':claimed', true, 300)) {
            return null;
        }

        $payload = $this->cache->pull($key);

        /** @var array<string, mixed>|null */
        return is_array($payload) ? $payload : null;
    }

    private function key(string $purpose, string $code): string
    {
        return "connex:{$purpose}:".hash('sha256', $code);
    }
}
