<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use NeaMatrix\Connex\Enums\DeviceType;

/**
 * What a login code stands for until a client exchanges it for a token.
 */
final readonly class LoginGrant
{
    public function __construct(
        public int|string $userId,
        public string $clientId,
        public string $redirectUri,
        public DeviceType $device,
        public bool $isNewSubscriber,
        public ?string $codeChallenge = null,
    ) {}

    /**
     * @return array<string, int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'user' => $this->userId,
            'client' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'device' => $this->device->value,
            'new' => $this->isNewSubscriber,
            'challenge' => $this->codeChallenge,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        if (! isset($data['user'], $data['client'], $data['redirect_uri'], $data['device'])
            || ! is_scalar($data['user']) || ! is_string($data['client']) || ! is_string($data['redirect_uri'])) {
            return null;
        }

        return new self(
            is_int($data['user']) ? $data['user'] : (string) $data['user'],
            $data['client'],
            $data['redirect_uri'],
            DeviceType::tryFrom((string) $data['device']) ?? DeviceType::Web,
            (bool) ($data['new'] ?? false),
            is_string($data['challenge'] ?? null) ? $data['challenge'] : null,
        );
    }
}
