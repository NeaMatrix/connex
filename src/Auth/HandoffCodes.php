<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;
use NeaMatrix\Connex\Enums\DeviceType;

/**
 * Short-lived, single-use codes that move a login from the webview to the app.
 *
 * The webview redirects to the app's callback URL with ?code=..., and the app
 * exchanges the code for a token over HTTPS. The token itself never appears in
 * a URL, browser history or logs.
 */
class HandoffCodes
{
    private const string PREFIX = 'connex:handoff:';

    public function __construct(
        private readonly Cache $cache,
        private readonly int $ttlSeconds = 120,
    ) {}

    public function issue(int|string $userId, DeviceType $device): string
    {
        $code = Str::random(64);
        $this->cache->put(self::PREFIX.hash('sha256', $code), ['user' => $userId, 'device' => $device->value], $this->ttlSeconds);

        return $code;
    }

    /**
     * @return array{user: int|string, device: DeviceType}|null
     */
    public function redeem(string $code): ?array
    {
        $entry = $this->cache->pull(self::PREFIX.hash('sha256', $code));

        if (! is_array($entry) || ! isset($entry['user'], $entry['device'])) {
            return null;
        }

        return ['user' => $entry['user'], 'device' => DeviceType::from($entry['device'])];
    }
}
