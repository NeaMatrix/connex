<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;
use NeaMatrix\Connex\Contracts\IssuesTokens;
use NeaMatrix\Connex\Enums\DeviceType;

/**
 * Issues a Laravel Sanctum personal access token. The user model must use
 * Laravel\Sanctum\HasApiTokens.
 */
class SanctumTokenIssuer implements IssuesTokens
{
    public function __construct(
        private readonly string $tokenName = 'connex',
    ) {}

    public function issue(Authenticatable $user, DeviceType $device): IssuedToken
    {
        if (! method_exists($user, 'createToken')) {
            throw new LogicException('The user model must use Laravel\Sanctum\HasApiTokens to issue Connex tokens.');
        }

        return new IssuedToken($user->createToken("{$this->tokenName}-{$device->value}")->plainTextToken);
    }
}
