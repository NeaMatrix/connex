<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use Carbon\CarbonImmutable;

final readonly class IssuedToken
{
    public function __construct(
        public string $token,
        public string $type = 'Bearer',
        public ?CarbonImmutable $expiresAt = null,
    ) {}

    /**
     * @return array{token: string, token_type: string, expires_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'token_type' => $this->type,
            'expires_at' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
