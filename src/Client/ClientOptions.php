<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Client;

/**
 * Connection settings for the Connex API, read once from config("connex").
 */
final readonly class ClientOptions
{
    public function __construct(
        public string $baseUrl,
        public string $email,
        public string $password,
        public int $timeout = 15,
        public int $connectTimeout = 5,
        public ?string $scriptOwner = null,
        public ?string $logChannel = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config  the "connex" config array
     */
    public static function fromConfig(array $config): self
    {
        $string = static fn (mixed $value): string => is_scalar($value) ? (string) $value : '';
        $nullable = static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null;

        return new self(
            baseUrl: rtrim($string($config['base_url'] ?? ''), '/'),
            email: $string(data_get($config, 'credentials.email')),
            password: $string(data_get($config, 'credentials.password')),
            timeout: (int) data_get($config, 'http.timeout', 15),
            connectTimeout: (int) data_get($config, 'http.connect_timeout', 5),
            scriptOwner: $nullable($config['script_owner'] ?? null),
            logChannel: $nullable(data_get($config, 'logging.channel')),
        );
    }
}
