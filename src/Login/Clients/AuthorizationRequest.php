<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Clients;

use Illuminate\Http\Request;

/**
 * Where to send the subscriber after login, as asked for by a client:
 * client, redirect_uri, state, code_challenge (PKCE S256) and response_mode.
 */
final readonly class AuthorizationRequest
{
    public const string RESPONSE_REDIRECT = 'query';

    /** Popup flow: post the code to window.opener instead of navigating. */
    public const string RESPONSE_WEB_MESSAGE = 'web_message';

    public function __construct(
        public string $clientId,
        public string $redirectUri,
        public ?string $state = null,
        public ?string $codeChallenge = null,
        public string $responseMode = self::RESPONSE_REDIRECT,
    ) {}

    /**
     * @return self|null null when the request names no client (session login)
     *
     * @throws InvalidAuthorizationRequest
     */
    public static function fromRequest(Request $request, ClientRegistry $clients): ?self
    {
        $clientId = $request->query('client');

        if ($clientId === null) {
            return null;
        }

        $client = is_string($clientId) ? $clients->find($clientId) : null;
        $redirectUri = (string) $request->query('redirect_uri', '');
        $state = $request->query('state');
        $challenge = $request->query('code_challenge');
        $method = $request->query('code_challenge_method', 'S256');
        $mode = (string) $request->query('response_mode', self::RESPONSE_REDIRECT);

        match (true) {
            $client === null => throw InvalidAuthorizationRequest::because('Unknown client.'),
            ! $client->allowsRedirectTo($redirectUri) => throw InvalidAuthorizationRequest::because('This redirect_uri is not registered for the client.'),
            $state !== null && (! is_string($state) || strlen($state) > 500) => throw InvalidAuthorizationRequest::because('Invalid state.'),
            $challenge !== null && (! is_string($challenge) || ! preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge)) => throw InvalidAuthorizationRequest::because('Invalid code_challenge.'),
            $client->requiresPkce && $challenge === null => throw InvalidAuthorizationRequest::because('This client must send a PKCE code_challenge.'),
            $method !== 'S256' => throw InvalidAuthorizationRequest::because('Only the S256 code_challenge_method is supported.'),
            ! in_array($mode, [self::RESPONSE_REDIRECT, self::RESPONSE_WEB_MESSAGE], true) => throw InvalidAuthorizationRequest::because('Unsupported response_mode.'),
            $mode === self::RESPONSE_WEB_MESSAGE && self::originOf($redirectUri) === null => throw InvalidAuthorizationRequest::because('web_message needs an http(s) redirect_uri.'),
            default => null,
        };

        return new self($client->id, $redirectUri, is_string($state) ? $state : null, is_string($challenge) ? $challenge : null, $mode);
    }

    /**
     * The redirect URI with the given parameters (and state) appended.
     *
     * @param  array<string, string>  $parameters
     */
    public function redirectWith(array $parameters): string
    {
        $query = http_build_query(array_filter($parameters + ['state' => $this->state], fn (?string $value): bool => $value !== null));
        $separator = str_contains($this->redirectUri, '?') ? '&' : '?';

        return $this->redirectUri.$separator.$query;
    }

    /** scheme://host[:port] of the redirect URI, for postMessage. */
    public function targetOrigin(): ?string
    {
        return self::originOf($this->redirectUri);
    }

    /**
     * @return array{client: string, redirect_uri: string, state: string|null, code_challenge: string|null, response_mode: string}
     */
    public function toArray(): array
    {
        return [
            'client' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $this->state,
            'code_challenge' => $this->codeChallenge,
            'response_mode' => $this->responseMode,
        ];
    }

    /**
     * @param  array{client: string, redirect_uri: string, state?: string|null, code_challenge?: string|null, response_mode?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['client'],
            $data['redirect_uri'],
            $data['state'] ?? null,
            $data['code_challenge'] ?? null,
            $data['response_mode'] ?? self::RESPONSE_REDIRECT,
        );
    }

    private static function originOf(string $uri): ?string
    {
        $parts = parse_url($uri);

        if (! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
