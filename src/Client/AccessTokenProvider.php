<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Client;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use NeaMatrix\Connex\Enums\Endpoint;
use NeaMatrix\Connex\Exceptions\AuthenticationFailed;
use NeaMatrix\Connex\Exceptions\ConnectionFailed;
use NeaMatrix\Connex\Exceptions\ConnexException;

/**
 * Obtains the service's Bearer token from auth-login and caches it until
 * shortly before Connex says it expires.
 */
class AccessTokenProvider
{
    /** Refresh this many seconds before the token's reported expiry. */
    private const int EXPIRY_MARGIN_SECONDS = 60;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly Cache $cache,
        private readonly ResponseParser $parser,
        private readonly ClientOptions $options,
    ) {}

    /**
     * @throws ConnexException
     */
    public function token(): string
    {
        $cached = $this->cache->get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        [$token, $ttl] = $this->requestToken();
        $this->cache->put($this->cacheKey(), $token, max($ttl - self::EXPIRY_MARGIN_SECONDS, 1));

        return $token;
    }

    public function forget(): void
    {
        $this->cache->forget($this->cacheKey());
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function requestToken(): array
    {
        if ($this->options->baseUrl === '' || $this->options->email === '' || $this->options->password === '') {
            throw AuthenticationFailed::missingCredentials();
        }

        try {
            $response = $this->http
                ->baseUrl($this->options->baseUrl)
                ->timeout($this->options->timeout)
                ->connectTimeout($this->options->connectTimeout)
                ->acceptJson()
                ->asForm()
                ->post(Endpoint::AuthLogin->path(), [
                    'email' => $this->options->email,
                    'password' => $this->options->password,
                ]);
        } catch (ConnectionException $exception) {
            throw ConnectionFailed::during(Endpoint::AuthLogin, $exception);
        }

        $parsed = $this->parser->parse(Endpoint::AuthLogin, $response->status(), $response->body());

        if (! $parsed->isSuccessful()) {
            throw AuthenticationFailed::fromResponse($parsed, 'Connex rejected the service credentials.');
        }

        return [
            $parsed->payload->string('access_token'),
            $parsed->payload->optionalInt('expired_after') ?? 3600,
        ];
    }

    private function cacheKey(): string
    {
        return 'connex:access-token:'.sha1($this->options->baseUrl.'|'.$this->options->email);
    }
}
