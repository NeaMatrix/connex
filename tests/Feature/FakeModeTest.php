<?php

declare(strict_types=1);

use NeaMatrix\Connex\Client\HttpConnexClient;
use NeaMatrix\Connex\ConnexServiceProvider;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Exceptions\InvalidOtp;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Testing\FakeConnexClient;

function bootWithFakeMode(string $environment): ConnexClient
{
    config(['connex.fake.enabled' => true, 'connex.fake.otp' => '7777']);
    app()->detectEnvironment(fn (): string => $environment);
    (new ConnexServiceProvider(app()))->register();
    $client = app(ConnexClient::class);
    app()->detectEnvironment(fn (): string => 'testing');

    return $client;
}

it('swaps in a fake that knows the stored subscriptions', function (): void {
    ConnexSubscription::factory()->cancelled()->create(['msisdn' => '218920920110']);

    $connex = bootWithFakeMode('staging');

    expect($connex)->toBeInstanceOf(FakeConnexClient::class)
        ->and($connex->subscriberDetails('0920920110')->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($connex->confirmLogin('0910000000', '7777')->isNewSubscriber())->toBeTrue();
    expect(fn () => $connex->confirmLogin('0910000000', '1234'))->toThrow(InvalidOtp::class);
});

it('never fakes Connex in production', function (): void {
    expect(bootWithFakeMode('production'))->toBeInstanceOf(HttpConnexClient::class);
});
