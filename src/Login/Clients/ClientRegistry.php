<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Clients;

/**
 * The clients declared in config("connex.clients").
 */
final readonly class ClientRegistry
{
    /**
     * @param  array<string, Client>  $clients
     */
    public function __construct(
        private array $clients = [],
    ) {}

    /**
     * @param  array<string, array{redirect_uris?: list<string>, pkce?: bool}>  $config
     */
    public static function fromConfig(array $config): self
    {
        $clients = [];

        foreach ($config as $id => $settings) {
            $clients[$id] = Client::fromConfig($id, $settings);
        }

        return new self($clients);
    }

    public function find(string $id): ?Client
    {
        return $this->clients[$id] ?? null;
    }
}
