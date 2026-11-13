<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Client;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Log\LogManager;
use NeaMatrix\Connex\Enums\Endpoint;
use NeaMatrix\Connex\Exceptions\ConnectionFailed;
use NeaMatrix\Connex\Exceptions\ConnexException;
use NeaMatrix\Connex\Exceptions\ExceptionFactory;

/**
 * Sends authenticated requests to Connex and turns every reply into either a
 * successful {@see ConnexResponse} or a typed {@see ConnexException}.
 */
class Transport
{
    /** Fields that must never reach the logs. */
    private const array SECRET_FIELDS = ['otp', 'password', 'otp_signature'];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly AccessTokenProvider $tokens,
        private readonly ResponseParser $parser,
        private readonly ClientOptions $options,
        private readonly LogManager $logs,
    ) {}

    /**
     * @param  array<string, scalar|null>  $parameters
     *
     * @throws ConnexException
     */
    public function send(Endpoint $endpoint, array $parameters = []): ConnexResponse
    {
        $parameters = array_filter($parameters, static fn (mixed $value): bool => $value !== null);
        $response = $this->dispatch($endpoint, $parameters);

        if ($response->status() === 401) {
            $this->tokens->forget();
            $response = $this->dispatch($endpoint, $parameters);
        }

        $parsed = $this->parser->parse($endpoint, $response->status(), $response->body());
        $this->log($endpoint, $parameters, $parsed);

        if (! $parsed->isSuccessful()) {
            throw ExceptionFactory::fromResponse($parsed);
        }

        return $parsed;
    }

    /**
     * @param  array<string, scalar>  $parameters
     */
    private function dispatch(Endpoint $endpoint, array $parameters): Response
    {
        $request = $this->http
            ->baseUrl($this->options->baseUrl)
            ->withToken($this->tokens->token())
            ->timeout($this->options->timeout)
            ->connectTimeout($this->options->connectTimeout)
            ->acceptJson();

        try {
            return $endpoint->method() === 'GET'
                ? $request->get($endpoint->path(), $parameters)
                : $request->asForm()->post($endpoint->path(), $parameters);
        } catch (ConnectionException $exception) {
            throw ConnectionFailed::during($endpoint, $exception);
        }
    }

    /**
     * @param  array<string, scalar>  $parameters
     */
    private function log(Endpoint $endpoint, array $parameters, ConnexResponse $response): void
    {
        if ($this->options->logChannel === null) {
            return;
        }

        $this->logs->channel($this->options->logChannel)->info("Connex {$endpoint->value}", [
            'request' => array_diff_key($parameters, array_flip(self::SECRET_FIELDS)),
            'http_status' => $response->httpStatus,
            'message_code' => $response->rawCode,
            'message' => $response->message(),
        ]);
    }
}
