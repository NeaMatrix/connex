<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

/**
 * RFC 7636 proof key check (S256).
 */
final class Pkce
{
    public static function challengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function verify(string $verifier, string $challenge): bool
    {
        return preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier) === 1
            && hash_equals($challenge, self::challengeFor($verifier));
    }
}
