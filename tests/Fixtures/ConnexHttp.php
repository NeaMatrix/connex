<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Tests\Fixtures;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use NeaMatrix\Connex\Tests\TestCase;

/**
 * Fakes Connex over HTTP with a working auth-login plus the given endpoint replies.
 */
final class ConnexHttp
{
    /**
     * @param  array<string, array{0: array<string, mixed>|string, 1?: int}>  $replies  endpoint => [body, status]
     */
    public static function fake(array $replies = []): Factory
    {
        $fakes = ['*/auth-login' => Http::response(self::token())];

        foreach ($replies as $endpoint => $reply) {
            $fakes[TestCase::BASE_URL.'/'.$endpoint.'*'] = Http::response($reply[0], $reply[1] ?? 200);
        }

        return Http::fake($fakes)->preventStrayRequests();
    }

    /**
     * @return array<string, mixed>
     */
    public static function token(string $token = 'service-token'): array
    {
        return ['messageCode' => '00', 'data' => ['access_token' => $token, 'token_type' => 'Bearer', 'expired_after' => 3600, 'unit' => 'second']];
    }
}
