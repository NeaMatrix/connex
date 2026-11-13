<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use NeaMatrix\Connex\Client\ClientOptions;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Exceptions\AuthenticationFailed;
use NeaMatrix\Connex\Exceptions\ConnectionFailed;
use NeaMatrix\Connex\Tests\Fixtures\ConnexHttp;

$details = ['messageCode' => '00', 'success' => ['msisdn' => '218920920110', 'subscriber' => 'exist', 'details' => ['status' => 'active']]];

it('logs in with form fields and reuses the cached token', function () use ($details): void {
    ConnexHttp::fake(['subscriber-details' => [$details]]);
    $connex = app(ConnexClient::class);

    $connex->subscriberDetails('0920920110');
    $connex->subscriberDetails('0920920110');

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/auth-login')
        && $request->isForm()
        && $request['email'] === 'service@example.com'
        && $request['password'] === 'service-password');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'subscriber-details')
        && $request->hasHeader('Authorization', 'Bearer service-token'));
});

it('fetches a new token once when Connex rejects the cached one', function () use ($details): void {
    Http::fake([
        '*/auth-login' => Http::sequence()->push(ConnexHttp::token('old'))->push(ConnexHttp::token('new')),
        '*/subscriber-details*' => Http::sequence()
            ->push(['message' => 'Unauthenticated.', 'messageCode' => '01'], 401)
            ->push($details),
    ]);

    $result = app(ConnexClient::class)->subscriberDetails('0920920110');

    expect($result->msisdn)->toBe('218920920110');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer new'));
});

it('reports wrong service credentials as AuthenticationFailed', function (): void {
    Http::fake(['*/auth-login' => Http::response(['messageCode' => '01', 'errors' => ['password' => ['The provided credentials are incorrect.']]], 401)]);

    app(ConnexClient::class)->subscriberDetails('0920920110');
})->throws(AuthenticationFailed::class, 'The provided credentials are incorrect.');

it('refuses to call Connex without credentials', function (): void {
    config(['connex.credentials.password' => null]);
    app()->forgetInstance(ClientOptions::class);
    Http::fake()->preventStrayRequests();

    app(ConnexClient::class)->subscriberDetails('0920920110');
})->throws(AuthenticationFailed::class, 'CONNEX_PASSWORD');

it('wraps network failures', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

    app(ConnexClient::class)->subscriberDetails('0920920110');
})->throws(ConnectionFailed::class, 'timed out');
