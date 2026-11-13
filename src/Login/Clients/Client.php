<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Clients;

/**
 * An app allowed to send subscribers to the login page and receive a token:
 * a mobile app, a separate web frontend, a partner site...
 */
final readonly class Client
{
    /**
     * @param  list<string>  $redirectUris  exact URIs the login may return to
     */
    public function __construct(
        public string $id,
        public array $redirectUris,
        public bool $requiresPkce = true,
    ) {}

    /**
     * @param  array{redirect_uris?: list<string>, pkce?: bool}  $config
     */
    public static function fromConfig(string $id, array $config): self
    {
        return new self($id, $config['redirect_uris'] ?? [], $config['pkce'] ?? true);
    }

    public function allowsRedirectTo(string $uri): bool
    {
        return in_array($uri, $this->redirectUris, true);
    }
}
