<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use NeaMatrix\Connex\Contracts\IssuesTokens;
use NeaMatrix\Connex\Enums\DeviceType;

/**
 * Issues a Laravel Sanctum personal access token, named "{name}-{device}".
 * The user model must use Laravel\Sanctum\HasApiTokens.
 */
class SanctumTokenIssuer implements IssuesTokens
{
    /**
     * @param  list<string>  $abilities
     */
    public function __construct(
        private readonly string $name = 'connex',
        private readonly array $abilities = ['*'],
        private readonly ?int $expiresInMinutes = null,
    ) {}

    public function issue(Authenticatable $user, DeviceType $device): IssuedToken
    {
        if (! method_exists($user, 'createToken')) {
            throw new LogicException('Add Laravel\Sanctum\HasApiTokens to the user model so Connex logins can issue tokens.');
        }

        $expiresAt = $this->expiresInMinutes === null ? null : CarbonImmutable::now()->addMinutes($this->expiresInMinutes);
        $token = $user->createToken("{$this->name}-{$device->value}", $this->abilities, $expiresAt);

        return new IssuedToken($token->plainTextToken, 'Bearer', $expiresAt);
    }
}
